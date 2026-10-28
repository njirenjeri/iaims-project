<?php

require_once __DIR__ . '/staff.php';
require_once __DIR__ . '/invites.php';
require_once __DIR__ . '/device.php';

// Everything that changes a staff account. Each action:
//   1. checks the person is allowed (and logs a refusal),
//   2. makes the change and writes its audit entry in one transaction, so there is never a
//      change without a record, and
//   3. returns ['success' => bool, 'message' => string, ...] for the page to show.
// Accounts are never deleted: archiving is the soft delete, and the record stays linked.

// Actions available on the user detail page, with the rule each follows.
const STAFF_STATUS_ACTIONS = [
    'suspend' => ['from' => ['active'], 'to' => 'suspended', 'audit' => 'user.suspended', 'reason' => 'required', 'revoke' => true],
    'reactivate' => ['from' => ['suspended'], 'to' => 'active', 'audit' => 'user.reactivated', 'reason' => 'optional', 'revoke' => false],
    'archive' => ['from' => ['active', 'suspended', 'invited'], 'to' => 'archived', 'audit' => 'user.archived', 'reason' => 'required', 'revoke' => true],
    'restore' => ['from' => ['archived'], 'to' => 'suspended', 'audit' => 'user.restored', 'reason' => 'optional', 'revoke' => false],
];

/* ---------- Who is acting ---------- */

function staffActor(PDO $pdo): array
{
    return [
        'user_id' => (string) ($_SESSION['user_id'] ?? ''),
        'email' => (string) ($_SESSION['user_email'] ?? ''),
        'roles' => (array) ($_SESSION['user_roles'] ?? []),
        'scope' => currentStaffScope($pdo),
    ];
}

// May the actor change this account? A system admin may change anyone; an HR admin only the
// HR officers and supervisors of their own ministry.
function canManageStaffTarget(array $actor, array $target): bool
{
    if ($actor['scope']['all']) {
        return true;
    }

    return in_array('HR_ADMIN', $actor['roles'], true)
        && $actor['scope']['ministry_id'] > 0
        && $target['ministry_id'] === $actor['scope']['ministry_id']
        && in_array($target['role_key'], ['HR', 'SUPERVISOR'], true);
}

// The buttons the user detail page shows for this account.
function availableStaffActions(array $actor, array $target): array
{
    if ($target['user_id'] === $actor['user_id'] || !canManageStaffTarget($actor, $target)) {
        return [];
    }

    $actions = [];

    switch ($target['status']) {
        case 'active':
            $actions[] = ['key' => 'force_reset', 'label' => 'Force password reset', 'icon' => 'bi-key', 'reason' => 'none', 'danger' => false,
                'body' => 'Their current password stops working and they are signed out everywhere. They will need a reset link to get back in.'];
            $actions[] = ['key' => 'change_role', 'label' => 'Change role or scope', 'icon' => 'bi-person-gear', 'reason' => 'none', 'danger' => false, 'body' => ''];
            $actions[] = ['key' => 'suspend', 'label' => 'Suspend account', 'icon' => 'bi-person-slash', 'reason' => 'required', 'danger' => true,
                'body' => 'They are signed out immediately and cannot sign in until you reactivate the account.'];
            $actions[] = ['key' => 'archive', 'label' => 'Archive', 'icon' => 'bi-archive', 'reason' => 'required', 'danger' => false,
                'body' => 'Hides the account from normal lists and signs them out. Their history is kept.'];
            break;
        case 'suspended':
            $actions[] = ['key' => 'reactivate', 'label' => 'Reactivate account', 'icon' => 'bi-person-check', 'reason' => 'optional', 'danger' => false,
                'body' => 'They will be able to sign in again.'];
            $actions[] = ['key' => 'change_role', 'label' => 'Change role or scope', 'icon' => 'bi-person-gear', 'reason' => 'none', 'danger' => false, 'body' => ''];
            $actions[] = ['key' => 'archive', 'label' => 'Archive', 'icon' => 'bi-archive', 'reason' => 'required', 'danger' => false,
                'body' => 'Hides the account from normal lists. Their history is kept.'];
            break;
        case 'invited':
            $actions[] = ['key' => 'resend_invite', 'label' => 'Generate a new invite link', 'icon' => 'bi-envelope-arrow-up', 'reason' => 'none', 'danger' => false,
                'body' => 'The previous link stops working.'];
            $actions[] = ['key' => 'archive', 'label' => 'Cancel invite (archive)', 'icon' => 'bi-archive', 'reason' => 'required', 'danger' => false,
                'body' => 'The invite stops working and the account is archived.'];
            break;
        case 'archived':
            $actions[] = ['key' => 'restore', 'label' => 'Restore (as suspended)', 'icon' => 'bi-arrow-counterclockwise', 'reason' => 'optional', 'danger' => false,
                'body' => 'The account comes back suspended. Reactivate it afterwards when you are ready.'];
            break;
    }

    return $actions;
}

/* ---------- Building blocks ---------- */

function unusablePasswordHash(): string
{
    // Nobody knows this password, so the account cannot be signed into until a link is used.
    return password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
}

function ensureRole(PDO $pdo, string $name): int
{
    $select = $pdo->prepare('SELECT role_id FROM roles WHERE name = :name LIMIT 1');
    $select->execute(['name' => $name]);
    $id = $select->fetchColumn();

    if ($id !== false) {
        return (int) $id;
    }

    $pdo->prepare('INSERT INTO roles (name) VALUES (:name)')->execute(['name' => $name]);

    return (int) $pdo->lastInsertId();
}

// Gives the account its role rows and, for HR and supervisors, the ministry or department row.
function assignStaffRole(PDO $pdo, string $userId, string $role, int $ministryId, int $departmentId, string $assignedBy): void
{
    foreach (staffRoleNames($role) as $name) {
        $pdo->prepare('INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (:user_id, :role_id, :assigned_by)')
            ->execute(['user_id' => $userId, 'role_id' => ensureRole($pdo, $name), 'assigned_by' => $assignedBy]);
    }

    if ($role === 'HR' || $role === 'HR_ADMIN') {
        $pdo->prepare('INSERT INTO hr_assignments (user_id, ministry_id) VALUES (:user_id, :ministry_id)')
            ->execute(['user_id' => $userId, 'ministry_id' => $ministryId]);
    } elseif ($role === 'SUPERVISOR') {
        $pdo->prepare('INSERT INTO supervisor_assignments (user_id, department_id) VALUES (:user_id, :department_id)')
            ->execute(['user_id' => $userId, 'department_id' => $departmentId]);
    }
}

// Removes the staff role rows and scope rows so new ones can be assigned. These are links, not
// records with a history of their own: the audit entry keeps the before and after.
function clearStaffRoles(PDO $pdo, string $userId): void
{
    $pdo->prepare(
        "DELETE ur FROM user_roles ur
         INNER JOIN roles r ON r.role_id = ur.role_id
         WHERE ur.user_id = :user_id AND r.name IN ('ADMIN', 'HR_ADMIN', 'HR', 'SUPERVISOR')"
    )->execute(['user_id' => $userId]);

    $pdo->prepare('DELETE FROM hr_assignments WHERE user_id = :user_id')->execute(['user_id' => $userId]);
    $pdo->prepare('DELETE FROM supervisor_assignments WHERE user_id = :user_id')->execute(['user_id' => $userId]);
}

function countActiveAdmins(PDO $pdo): int
{
    return (int) $pdo->query(
        "SELECT COUNT(DISTINCT u.user_id)
         FROM users u
         INNER JOIN user_roles ur ON ur.user_id = u.user_id
         INNER JOIN roles r ON r.role_id = ur.role_id
         WHERE r.name = 'ADMIN' AND u.status = 'active'"
    )->fetchColumn();
}

// A fresh password-reset token that skips the usual cooldown (an admin is asking). Call inside
// auditedTransaction().
function issueResetTokenFor(PDO $pdo, string $userId): string
{
    $token = bin2hex(random_bytes(32));
    $now = new DateTimeImmutable();
    $minutes = defined('PASSWORD_RESET_TTL_MINUTES') ? (int) PASSWORD_RESET_TTL_MINUTES : 30;

    $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id')->execute(['user_id' => $userId]);
    $pdo->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
         VALUES (:user_id, :token_hash, :expires_at, :created_at)'
    )->execute([
        'user_id' => $userId,
        'token_hash' => hash('sha256', $token),
        'expires_at' => $now->modify("+{$minutes} minutes")->format('Y-m-d H:i:s'),
        'created_at' => $now->format('Y-m-d H:i:s'),
    ]);

    return $token;
}

// Loads the account an action is aimed at and applies the rules every action shares.
// Returns [target, null] or [null, failureResult].
function loadTargetForAction(PDO $pdo, array $actor, string $targetId, string $attempted): array
{
    $target = preg_match('/^[0-9a-f-]{36}$/i', $targetId) ? getStaffUser($pdo, $targetId) : null;

    if ($target === null || !canViewStaffTarget($actor, $target)) {
        return [null, ['success' => false, 'message' => 'That user was not found.']];
    }

    $denied = static function (string $reason, string $message) use ($pdo, $target, $targetId, $attempted): array {
        auditLogSafe($pdo, 'user.action_denied', 'denied', [
            'target_type' => 'user',
            'target_id' => $targetId,
            'target_label' => $target['email'],
            'details' => ['attempted' => $attempted, 'reason' => $reason],
        ]);

        return [null, ['success' => false, 'message' => $message]];
    };

    if (!canManageStaffTarget($actor, $target)) {
        return $denied('outside_permissions', 'You are not allowed to do that.');
    }

    if ($target['user_id'] === $actor['user_id']) {
        return $denied('own_account', 'You cannot do that to your own account.');
    }

    return [$target, null];
}

// What to tell the admin about a link that was just issued (appended to the success message).
function describeDelivery(array $result): string
{
    $delivery = $result['delivery'] ?? null;

    if (!$delivery) {
        return '';
    }

    if ($delivery['mode'] === 'email') {
        return ' The link was emailed to them.';
    }

    return $delivery['email_failed']
        ? ' The email could not be sent, so the link is shown below. Share it yourself.'
        : ' Copy the link below and share it with them.';
}

function cleanReason($value): string
{
    return is_string($value) ? mb_substr(trim(preg_replace('/\s+/', ' ', $value)), 0, 200) : '';
}

/* ---------- Actions ---------- */

// Creates the account in the "invited" state and the invite link. Success includes user_id.
function createStaffUser(PDO $pdo, array $actor, array $input): array
{
    [$clean, $errors] = validateStaffInput($pdo, $input, $actor['scope']);

    if ($errors) {
        return ['success' => false, 'errors' => array_values($errors)];
    }

    $userId = generateUuid();
    $token = '';

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($actor, $clean, $userId, &$token) {
            $now = date('Y-m-d H:i:s');

            $pdo->prepare(
                'INSERT INTO users (user_id, email, password_hash, status)
                 VALUES (:user_id, :email, :password_hash, :status)'
            )->execute([
                'user_id' => $userId,
                'email' => $clean['email'],
                'password_hash' => unusablePasswordHash(),
                'status' => 'invited',
            ]);

            $pdo->prepare(
                'INSERT INTO staff_profiles (user_id, first_name, other_names, phone_number, created_at, updated_at)
                 VALUES (:user_id, :first_name, :other_names, :phone_number, :created_at, :updated_at)'
            )->execute([
                'user_id' => $userId,
                'first_name' => $clean['first_name'],
                'other_names' => $clean['other_names'],
                'phone_number' => $clean['phone_number'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            assignStaffRole($pdo, $userId, $clean['role'], $clean['ministry_id'], $clean['department_id'], $actor['user_id']);
            $token = createInvite($pdo, $userId, $actor['user_id']);

            auditLog($pdo, 'user.invited', 'success', [
                'target_type' => 'user',
                'target_id' => $userId,
                'target_label' => $clean['email'],
                'details' => [
                    'role' => $clean['role'],
                    'ministry' => $clean['ministry_name'],
                    'department' => $clean['department_name'],
                    'delivery' => mailEnabled() ? 'email' : 'shown_to_admin',
                ],
            ]);
        });
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            return ['success' => false, 'errors' => ['An account with that email already exists.']];
        }

        error_log('IAIMS staff create error: ' . $exception->getMessage());

        return ['success' => false, 'errors' => ['The account could not be created. Please try again.']];
    } catch (Throwable $exception) {
        error_log('IAIMS staff create error: ' . $exception->getMessage());

        return ['success' => false, 'errors' => ['The account could not be created. Please try again.']];
    }

    $delivery = deliverStaffLink('invite', $clean['email'], $clean['first_name'], $token, $userId);

    return ['success' => true, 'user_id' => $userId, 'delivery' => $delivery, 'message' => 'Account created.'];
}

// $action is one of the keys of STAFF_STATUS_ACTIONS.
function setStaffStatus(PDO $pdo, array $actor, string $targetId, string $action, string $reason): array
{
    $rule = STAFF_STATUS_ACTIONS[$action] ?? null;

    if ($rule === null) {
        return ['success' => false, 'message' => 'Invalid request.'];
    }

    [$target, $failure] = loadTargetForAction($pdo, $actor, $targetId, $action);

    if ($failure !== null) {
        return $failure;
    }

    if (!in_array($target['status'], $rule['from'], true)) {
        return ['success' => false, 'message' => "That action is not available for an account that is {$target['status']}."];
    }

    $reason = cleanReason($reason);

    if ($rule['reason'] === 'required' && mb_strlen($reason) < 3) {
        return ['success' => false, 'message' => 'Please give a reason (at least 3 characters).'];
    }

    // The last active system admin can't lock the system's owners out.
    $removesAdmin = $target['role_key'] === 'ADMIN' && $target['status'] === 'active' && $rule['to'] !== 'active';

    if ($removesAdmin && countActiveAdmins($pdo) <= 1) {
        auditLogSafe($pdo, 'user.action_denied', 'denied', [
            'target_type' => 'user',
            'target_id' => $targetId,
            'target_label' => $target['email'],
            'details' => ['attempted' => $action, 'reason' => 'last_active_admin'],
        ]);

        return ['success' => false, 'message' => 'This is the last active system admin, so it cannot be suspended or archived.'];
    }

    try {
        $sessionsEnded = 0;

        auditedTransaction($pdo, function (PDO $pdo) use ($rule, $target, $reason, $action, &$sessionsEnded) {
            // The status condition guards against someone else changing the account at the same moment.
            $update = $pdo->prepare('UPDATE users SET status = :to_status WHERE user_id = :user_id AND status = :from_status');
            $update->execute(['to_status' => $rule['to'], 'user_id' => $target['user_id'], 'from_status' => $target['status']]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The account changed while you were working.');
            }

            if ($rule['revoke']) {
                $sessionsEnded = revokeUserSessions($pdo, $target['user_id']);
            }

            if ($rule['to'] === 'archived') {
                $pdo->prepare("UPDATE user_invites SET status = 'revoked' WHERE user_id = :user_id AND status = 'pending'")
                    ->execute(['user_id' => $target['user_id']]);
            }

            auditLog($pdo, $rule['audit'], 'success', [
                'target_type' => 'user',
                'target_id' => $target['user_id'],
                'target_label' => $target['email'],
                'details' => [
                    'status' => ['from' => $target['status'], 'to' => $rule['to']],
                    'reason' => $reason,
                    'sessions_ended' => $sessionsEnded,
                ],
            ]);
        });
    } catch (Throwable $exception) {
        error_log('IAIMS staff status error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'The account could not be updated. Reload the page and try again.'];
    }

    $messages = ['suspend' => 'Account suspended.', 'reactivate' => 'Account reactivated.', 'archive' => 'Account archived.', 'restore' => 'Account restored as suspended.'];

    return ['success' => true, 'message' => $messages[$action]];
}

function changeStaffRole(PDO $pdo, array $actor, string $targetId, string $newRole, int $ministryId, int $departmentId, string $reason): array
{
    [$target, $failure] = loadTargetForAction($pdo, $actor, $targetId, 'change_role');

    if ($failure !== null) {
        return $failure;
    }

    if (!in_array($target['status'], ['active', 'suspended'], true)) {
        return ['success' => false, 'message' => 'The role can only be changed on an active or suspended account.'];
    }

    if (!in_array($newRole, creatableRoles($actor['scope']), true)) {
        auditLogSafe($pdo, 'user.action_denied', 'denied', [
            'target_type' => 'user',
            'target_id' => $targetId,
            'target_label' => $target['email'],
            'details' => ['attempted' => 'change_role', 'reason' => 'role_not_assignable', 'role' => $newRole],
        ]);

        return ['success' => false, 'message' => 'You are not allowed to assign that role.'];
    }

    [$newMinistryId, $newDepartmentId, $newMinistryName, $newDepartmentName, $scopeError] =
        resolveStaffScope($pdo, $actor['scope'], $newRole, $ministryId, $departmentId);

    if ($scopeError !== '') {
        return ['success' => false, 'message' => $scopeError];
    }

    if ($newRole === $target['role_key'] && $newMinistryId === $target['ministry_id'] && $newDepartmentId === $target['department_id']) {
        return ['success' => false, 'message' => 'Nothing to change.'];
    }

    if ($target['role_key'] === 'ADMIN' && $newRole !== 'ADMIN' && $target['status'] === 'active' && countActiveAdmins($pdo) <= 1) {
        auditLogSafe($pdo, 'user.action_denied', 'denied', [
            'target_type' => 'user',
            'target_id' => $targetId,
            'target_label' => $target['email'],
            'details' => ['attempted' => 'change_role', 'reason' => 'last_active_admin'],
        ]);

        return ['success' => false, 'message' => 'This is the last active system admin, so the role cannot be changed.'];
    }

    $reason = cleanReason($reason);

    try {
        $sessionsEnded = 0;

        auditedTransaction($pdo, function (PDO $pdo) use ($actor, $target, $newRole, $newMinistryId, $newDepartmentId, $newMinistryName, $newDepartmentName, $reason, &$sessionsEnded) {
            clearStaffRoles($pdo, $target['user_id']);
            assignStaffRole($pdo, $target['user_id'], $newRole, $newMinistryId, $newDepartmentId, $actor['user_id']);

            // Their sessions carry the old roles, so they must sign in again to get the new ones.
            $sessionsEnded = revokeUserSessions($pdo, $target['user_id']);

            auditLog($pdo, 'user.role_changed', 'success', [
                'target_type' => 'user',
                'target_id' => $target['user_id'],
                'target_label' => $target['email'],
                'details' => [
                    'role' => ['from' => $target['role_key'], 'to' => $newRole],
                    'ministry' => ['from' => $target['ministry_name'], 'to' => $newMinistryName],
                    'department' => ['from' => $target['department_name'], 'to' => $newDepartmentName],
                    'reason' => $reason,
                    'sessions_ended' => $sessionsEnded,
                ],
            ]);
        });
    } catch (Throwable $exception) {
        error_log('IAIMS staff role change error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'The role could not be changed. Please try again.'];
    }

    return ['success' => true, 'message' => 'Role updated. They will need to sign in again.'];
}

// Their current password stops working at once and a reset link is issued.
function forceStaffPasswordReset(PDO $pdo, array $actor, string $targetId): array
{
    [$target, $failure] = loadTargetForAction($pdo, $actor, $targetId, 'force_reset');

    if ($failure !== null) {
        return $failure;
    }

    if ($target['status'] !== 'active') {
        return ['success' => false, 'message' => 'A password reset can only be forced on an active account.'];
    }

    $token = '';

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($target, &$token) {
            $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :user_id')
                ->execute(['hash' => unusablePasswordHash(), 'user_id' => $target['user_id']]);

            $sessionsEnded = revokeUserSessions($pdo, $target['user_id']);
            $token = issueResetTokenFor($pdo, $target['user_id']);

            auditLog($pdo, 'user.password_reset_forced', 'success', [
                'target_type' => 'user',
                'target_id' => $target['user_id'],
                'target_label' => $target['email'],
                'details' => ['sessions_ended' => $sessionsEnded, 'delivery' => mailEnabled() ? 'email' : 'shown_to_admin'],
            ]);
        });
    } catch (Throwable $exception) {
        error_log('IAIMS forced reset error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'The reset could not be started. Please try again.'];
    }

    $delivery = deliverStaffLink('reset', $target['email'], $target['first_name'], $token, $target['user_id']);

    return ['success' => true, 'message' => 'Password reset forced.', 'delivery' => $delivery];
}

function resendStaffInvite(PDO $pdo, array $actor, string $targetId): array
{
    [$target, $failure] = loadTargetForAction($pdo, $actor, $targetId, 'resend_invite');

    if ($failure !== null) {
        return $failure;
    }

    if ($target['status'] !== 'invited') {
        return ['success' => false, 'message' => 'Only an account that has not accepted its invite can be sent a new one.'];
    }

    $token = '';

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($actor, $target, &$token) {
            $token = createInvite($pdo, $target['user_id'], $actor['user_id']);

            auditLog($pdo, 'user.invite_resent', 'success', [
                'target_type' => 'user',
                'target_id' => $target['user_id'],
                'target_label' => $target['email'],
                'details' => ['delivery' => mailEnabled() ? 'email' : 'shown_to_admin'],
            ]);
        });
    } catch (Throwable $exception) {
        error_log('IAIMS invite resend error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'A new invite could not be created. Please try again.'];
    }

    $delivery = deliverStaffLink('invite', $target['email'], $target['first_name'], $token, $target['user_id']);

    return ['success' => true, 'message' => 'New invite created.', 'delivery' => $delivery];
}
