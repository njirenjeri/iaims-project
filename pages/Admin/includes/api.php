<?php

require_once __DIR__ . '/data.php';

if (!defined('IAIMS_BOOTSTRAPPED')) {
    http_response_code(404);
    exit('Not found.');
}

header('Content-Type: application/json; charset=utf-8');

function adminJsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    adminJsonResponse(['ok' => false, 'message' => 'Use POST.'], 405);
}

adminRequireAccess();
if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
    adminJsonResponse(['ok' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 403);
}

$state = adminDemoState();
$action = $_POST['action'] ?? '';
$actor = $_SESSION['user_email'] ?? currentRole();
$today = date('Y-m-d');
$time = date('d M H:i');

$recordAudit = static function (string $event, string $target, array $changes, string $outcome = 'Success') use (&$state, $actor, $today, $time): void {
    array_unshift($state['audit'], [
        'date' => $today,
        'time' => $time,
        'actor' => (string)$actor,
        'actor_role' => currentRole(),
        'action' => $event,
        'target' => $target,
        'outcome' => $outcome,
        'changes' => $changes,
        'device' => [
            'Browser' => 'Local browser',
            'System' => PHP_OS_FAMILY,
            'Screen' => 'Not collected',
            'Timezone, language' => date_default_timezone_get(),
            'IP' => 'local',
            'Device ID' => 'not collected',
        ],
    ]);
};

if ($action === 'invite') {
    $first = trim((string)($_POST['first_name'] ?? ''));
    $other = trim((string)($_POST['other_names'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $role = trim((string)($_POST['role'] ?? ''));
    $ministry = trim((string)($_POST['ministry'] ?? ''));
    $department = trim((string)($_POST['department'] ?? ''));
    $ministries = getMinistries();
    $errors = [];

    if ($first === '') $errors['first_name'] = 'Enter the first name.';
    if ($other === '') $errors['other_names'] = 'Enter at least one other name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
    if (!preg_match('/^\+?[0-9 ]{9,16}$/', $phone)) $errors['phone'] = 'Enter a valid phone number.';
    if (!in_array($role, assignableRoles(), true)) $errors['role'] = 'Choose a role you are allowed to assign.';
    if (!isset($ministries[$ministry])) $errors['ministry'] = 'Choose a ministry you manage.';
    if ($role === 'Supervisor' && !in_array($department, $ministries[$ministry] ?? [], true)) {
        $errors['department'] = 'Choose a department for this supervisor.';
    }
    foreach ($state['users'] as $user) {
        if (strtolower((string)($user['email'] ?? '')) === $email) {
            $errors['email'] = 'A user with this email already exists.';
            break;
        }
    }
    if ($errors) adminJsonResponse(['ok' => false, 'errors' => $errors], 422);

    $id = max(array_map('intval', array_keys($state['users']) ?: [0])) + 1;
    $name = trim($first . ' ' . $other);
    $token = bin2hex(random_bytes(24));
    $state['users'][$id] = [
        'id' => $id, 'name' => $name, 'email' => $email, 'role' => $role,
        'ministry' => $ministry, 'scope' => $role === 'Supervisor' ? $department : $ministry,
        'status' => 'Invited', 'note' => 'Invite link expires in 72 hours', 'last' => 'Never',
        'phone' => $phone, 'invited_by' => (string)$actor, 'invited_on' => date('j M'),
        'password_set' => '—',
    ];
    $state['invites'][hash('sha256', $token)] = ['user_id' => $id, 'expires' => time() + 72 * 3600];
    $recordAudit('user.invited', $name, ['Role' => $role, 'Scope' => $state['users'][$id]['scope']]);
    adminSaveDemoState($state);

    adminJsonResponse([
        'ok' => true,
        'message' => "Invite created for $name.",
        'summary' => "$name · $role · " . $state['users'][$id]['scope'],
        'link' => APP_URL . '/?page=admin-demo-invite&token=' . rawurlencode($token),
    ]);
}

if ($action === 'account_action') {
    $labels = [
        'force_reset' => ['Password reset required', 'user.password_reset_issued'],
        'resend_invite' => ['Invite link refreshed', 'user.invite_resent'],
        'change_role' => ['Role updated', 'user.role_changed'],
        'suspend' => ['Account suspended', 'user.suspended'],
        'reactivate' => ['Account reactivated', 'user.reactivated'],
    ];
    $type = (string)($_POST['type'] ?? '');
    $id = (int)($_POST['user_id'] ?? 0);
    $user = getUser($id);
    if (!isset($labels[$type]) || !is_array($user)) {
        adminJsonResponse(['ok' => false, 'message' => 'That action is not available for this user.'], 422);
    }
    if ($type === 'resend_invite' && $user['status'] !== 'Invited') {
        adminJsonResponse(['ok' => false, 'message' => 'Invites can only be resent while the user is Invited.'], 422);
    }
    if ($type === 'suspend' && $user['status'] === 'Suspended') {
        adminJsonResponse(['ok' => false, 'message' => 'This account is already suspended.'], 422);
    }
    if ($type === 'reactivate' && $user['status'] !== 'Suspended') {
        adminJsonResponse(['ok' => false, 'message' => 'Only suspended accounts can be reactivated.'], 422);
    }
    if ($type === 'change_role') {
        $newRole = (string)($_POST['role'] ?? '');
        if (!in_array($newRole, assignableRoles(), true)) {
            adminJsonResponse(['ok' => false, 'message' => 'Choose a role you are allowed to assign.'], 422);
        }
        $newScope = trim((string)($_POST['scope'] ?? ''));
        $ministryOptions = getMinistries();
        $allowedScopes = $newRole === 'Supervisor'
            ? ($ministryOptions[$user['ministry']] ?? [])
            : [$user['ministry']];
        if (!in_array($newScope, $allowedScopes, true)) {
            adminJsonResponse(['ok' => false, 'message' => 'Choose a valid scope for this user and role.'], 422);
        }
        $oldRole = $user['role'];
        $oldScope = $user['scope'];
        $state['users'][$id]['role'] = $newRole;
        $state['users'][$id]['scope'] = $newScope;
        $recordAudit($labels[$type][1], $user['name'], [
            'Role' => "$oldRole to $newRole",
            'Scope' => "$oldScope to $newScope",
            'Reason' => trim((string)($_POST['reason'] ?? '')),
        ]);
    } elseif ($type === 'suspend' || $type === 'reactivate') {
        $oldStatus = $user['status'];
        $state['users'][$id]['status'] = $type === 'suspend' ? 'Suspended' : 'Active';
        $state['users'][$id]['note'] = '';
        $recordAudit($labels[$type][1], $user['name'], ['Status' => "$oldStatus to " . $state['users'][$id]['status'], 'Reason' => trim((string)($_POST['reason'] ?? ''))]);
    } elseif ($type === 'force_reset') {
        $state['users'][$id]['reset_required'] = true;
        $recordAudit($labels[$type][1], $user['name'], ['Password reset' => 'required at next sign-in']);
    } else {
        $token = bin2hex(random_bytes(24));
        $state['invites'][hash('sha256', $token)] = ['user_id' => $id, 'expires' => time() + 72 * 3600];
        $recordAudit($labels[$type][1], $user['name'], ['Invite' => 'refreshed']);
    }
    adminSaveDemoState($state);
    adminJsonResponse(['ok' => true, 'message' => $labels[$type][0] . ': ' . $user['name'] . '.']);
}

if ($action === 'log_export') {
    $recordAudit('audit.exported', 'Audit log', ['Exported at' => $time]);
    adminSaveDemoState($state);
    adminJsonResponse(['ok' => true]);
}

if ($action === 'settings_save' || $action === 'security_save') {
    if (!isSuperAdmin()) adminJsonResponse(['ok' => false, 'message' => 'Only a super administrator can change these controls.'], 403);
    if ($action === 'settings_save') {
        $timeout = filter_var($_POST['session_timeout'] ?? null, FILTER_VALIDATE_INT);
        if ($timeout === false || $timeout < 5 || $timeout > 240) {
            adminJsonResponse(['ok' => false, 'message' => 'Session timeout must be between 5 and 240 minutes.'], 422);
        }
        $state['settings'] = [
            'session_timeout' => $timeout,
            'require_mfa' => isset($_POST['require_mfa']),
            'maintenance_mode' => isset($_POST['maintenance_mode']),
        ];
        $recordAudit('settings.updated', 'Admin settings', $state['settings']);
    } else {
        $threshold = filter_var($_POST['lockout_threshold'] ?? null, FILTER_VALIDATE_INT);
        $minutes = filter_var($_POST['lockout_minutes'] ?? null, FILTER_VALIDATE_INT);
        $passwordLength = filter_var($_POST['password_min_length'] ?? null, FILTER_VALIDATE_INT);
        if ($threshold === false || $threshold < 3 || $threshold > 20
            || $minutes === false || $minutes < 1 || $minutes > 120
            || $passwordLength === false || $passwordLength < 8 || $passwordLength > 64) {
            adminJsonResponse(['ok' => false, 'message' => 'Check the allowed ranges for the security values.'], 422);
        }
        $state['security'] = [
            'lockout_threshold' => $threshold,
            'lockout_minutes' => $minutes,
            'password_min_length' => $passwordLength,
        ];
        $recordAudit('security.updated', 'Security policy', $state['security']);
    }
    adminSaveDemoState($state);
    adminJsonResponse(['ok' => true, 'message' => 'Changes saved to local demo storage.']);
}

adminJsonResponse(['ok' => false, 'message' => 'Unknown action.'], 400);
