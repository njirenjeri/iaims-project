<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/auth.php';

// Invite links for new staff, and the delivery of invite and reset links. Only a SHA-256 hash
// of each token is stored, so a lost link can't be shown again; a new one is generated instead.
//
// Until email is configured (MAIL_ENABLED is false in config/app.php) a link is shown ONCE to
// the admin who created it, who passes it on. Anyone holding the link can set the password, so
// showing it is recorded in the audit log and the device that finishes the activation is too.

/* ---------- Settings ---------- */

function mailEnabled(): bool
{
    return defined('MAIL_ENABLED') && MAIL_ENABLED;
}

function inviteTtlHours(): int
{
    return defined('INVITE_TTL_HOURS') ? max(1, (int) INVITE_TTL_HOURS) : 72;
}

function inviteLink(string $token): string
{
    return APP_URL . '/?page=accept-invite&token=' . urlencode($token);
}

/* ---------- Creating and accepting ---------- */

// Revokes any pending invite for the user and returns the new plain token.
// Call inside auditedTransaction() together with the change it belongs to.
function createInvite(PDO $pdo, string $userId, string $createdBy): string
{
    $token = bin2hex(random_bytes(32));
    $now = new DateTimeImmutable();

    $pdo->prepare("UPDATE user_invites SET status = 'revoked' WHERE user_id = :user_id AND status = 'pending'")
        ->execute(['user_id' => $userId]);

    $pdo->prepare(
        'INSERT INTO user_invites (user_id, token_hash, status, created_by, created_at, expires_at)
         VALUES (:user_id, :token_hash, :status, :created_by, :created_at, :expires_at)'
    )->execute([
        'user_id' => $userId,
        'token_hash' => hash('sha256', $token),
        'status' => 'pending',
        'created_by' => $createdBy,
        'created_at' => $now->format('Y-m-d H:i:s'),
        'expires_at' => $now->modify('+' . inviteTtlHours() . ' hours')->format('Y-m-d H:i:s'),
    ]);

    return $token;
}

// The invite row if the token exists, is pending, hasn't expired and the account is still invited.
function findValidInvite(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $statement = $pdo->prepare(
        "SELECT i.invite_id, i.user_id, u.email, COALESCE(sp.first_name, '') AS first_name
         FROM user_invites i
         INNER JOIN users u ON u.user_id = i.user_id
         LEFT JOIN staff_profiles sp ON sp.user_id = i.user_id
         WHERE i.token_hash = :token_hash
           AND i.status = 'pending'
           AND i.expires_at > :now
           AND u.status = 'invited'
         LIMIT 1"
    );
    $statement->execute(['token_hash' => hash('sha256', $token), 'now' => date('Y-m-d H:i:s')]);

    return $statement->fetch() ?: null;
}

// Sets the password and activates the account. The audit entry records the device used.
function acceptInvite(PDO $pdo, string $token, string $password): array
{
    $passwordErrors = validatePassword($password);

    if ($passwordErrors) {
        return ['success' => false, 'message' => implode(' ', $passwordErrors)];
    }

    $invite = findValidInvite($pdo, $token);

    if ($invite === null) {
        return ['success' => false, 'message' => 'This invitation is invalid or has expired. Ask your administrator for a new one.'];
    }

    $rolesStatement = $pdo->prepare(
        'SELECT r.name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.role_id WHERE ur.user_id = :user_id'
    );
    $rolesStatement->execute(['user_id' => $invite['user_id']]);
    $roles = implode(',', array_column($rolesStatement->fetchAll(), 'name'));

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($invite, $password, $roles) {
            // The status condition makes the link single-use even if it is submitted twice at once.
            $claim = $pdo->prepare("UPDATE user_invites SET status = 'accepted' WHERE invite_id = :id AND status = 'pending'");
            $claim->execute(['id' => $invite['invite_id']]);

            if ($claim->rowCount() !== 1) {
                throw new RuntimeException('Invite already used.');
            }

            $activate = $pdo->prepare(
                "UPDATE users SET password_hash = :hash, status = 'active' WHERE user_id = :user_id AND status = 'invited'"
            );
            $activate->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'user_id' => $invite['user_id']]);

            if ($activate->rowCount() !== 1) {
                throw new RuntimeException('Account is no longer awaiting activation.');
            }

            auditLog($pdo, 'invite.accepted', 'success', [
                'actor' => ['user_id' => $invite['user_id'], 'email' => $invite['email'], 'roles' => $roles],
                'target_type' => 'user',
                'target_id' => $invite['user_id'],
                'target_label' => $invite['email'],
            ]);
        });
    } catch (Throwable $exception) {
        error_log('IAIMS invite accept error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'This invitation is invalid or has already been used.'];
    }

    return ['success' => true, 'message' => 'Your password is set. You can now sign in.'];
}

/* ---------- Delivery ---------- */

function sendInviteEmail(string $email, string $firstName, string $link): bool
{
    $hours = inviteTtlHours();

    // XAMPP/WAMP can't send mail, so in development the link goes to the PHP error log.
    if (defined('APP_ENV') && APP_ENV === 'development') {
        error_log("Invite link for {$email}: {$link}");

        return true;
    }

    $greeting = $firstName !== '' ? "Hello {$firstName}," : 'Hello,';
    $body = "{$greeting}\r\n\r\n"
        . "An account has been created for you on " . APP_NAME . ".\r\n"
        . "Set your password using the link below within {$hours} hours:\r\n\r\n"
        . "{$link}\r\n\r\n"
        . "If you were not expecting this, you can ignore this email.\r\n";
    $headers = 'From: ' . MAIL_FROM . "\r\nContent-Type: text/plain; charset=UTF-8";

    return mail($email, 'Your account invitation', $body, $headers);
}

// Delivers an invite ('invite') or password-reset ('reset') link: by email when mail is enabled,
// otherwise (or if sending fails) by remembering it so the admin sees it once.
// Returns ['mode' => 'email'|'screen', 'email_failed' => bool].
function deliverStaffLink(string $kind, string $email, string $firstName, string $token, string $userId): array
{
    $isInvite = $kind === 'invite';
    $link = $isInvite ? inviteLink($token) : APP_URL . '/?page=reset-password&token=' . urlencode($token);
    $expires = $isInvite
        ? '+' . inviteTtlHours() . ' hours'
        : '+' . (defined('PASSWORD_RESET_TTL_MINUTES') ? (int) PASSWORD_RESET_TTL_MINUTES : 30) . ' minutes';

    if (mailEnabled()) {
        $sent = $isInvite ? sendInviteEmail($email, $firstName, $link) : sendPasswordResetEmail($email, $token);

        if ($sent) {
            return ['mode' => 'email', 'email_failed' => false];
        }
    }

    rememberIssuedLink(
        $userId,
        $isInvite ? 'Invite link' : 'Password reset link',
        $link,
        date('j M Y, H:i', strtotime($expires))
    );

    return ['mode' => 'screen', 'email_failed' => mailEnabled()];
}

// Keeps a link in the session for exactly one display on the user's page.
function rememberIssuedLink(string $userId, string $label, string $url, string $expires): void
{
    $_SESSION['issued_link'] = ['user_id' => $userId, 'label' => $label, 'url' => $url, 'expires' => $expires];
}

function takeIssuedLink(string $userId): ?array
{
    $issued = $_SESSION['issued_link'] ?? null;

    if (!is_array($issued) || ($issued['user_id'] ?? '') !== $userId) {
        return null;
    }

    unset($_SESSION['issued_link']);

    return $issued;
}
