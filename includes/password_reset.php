<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

// Forgotten-password flow. Only a SHA-256 hash of each token is stored, so a database
// leak can't be used to reset accounts.

// Returns a plain token to email to the user, or null if nothing should be sent.
function createPasswordResetToken(PDO $pdo, string $email): ?string
{
    $email = strtolower(trim($email));

    try {
        $statement = $pdo->prepare(
            'SELECT user_id FROM users WHERE email = :email AND status = :status LIMIT 1'
        );
        $statement->execute(['email' => $email, 'status' => 'active']);
        $user = $statement->fetch();

        if (!$user) {
            return null;
        }

        $now = new DateTimeImmutable();

        // Cooldown: ignore repeat requests made within a short window.
        $recent = $pdo->prepare(
            'SELECT 1 FROM password_resets WHERE user_id = :user_id AND created_at > :cutoff LIMIT 1'
        );
        $recent->execute([
            'user_id' => $user['user_id'],
            'cutoff' => $now->modify('-' . PASSWORD_RESET_COOLDOWN_SECONDS . ' seconds')
                ->format('Y-m-d H:i:s'),
        ]);

        if ($recent->fetch()) {
            return null;
        }

        $token = bin2hex(random_bytes(32));

        $pdo->beginTransaction();

        // Only one active reset link per user.
        $delete = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
        $delete->execute(['user_id' => $user['user_id']]);

        $insert = $pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, :expires_at, :created_at)'
        );
        $insert->execute([
            'user_id' => $user['user_id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => $now->modify('+' . PASSWORD_RESET_TTL_MINUTES . ' minutes')
                ->format('Y-m-d H:i:s'),
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);

        $pdo->commit();

        return $token;
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('IAIMS reset token error: ' . $exception->getMessage());

        return null;
    }
}

// Returns the reset row if the token exists, is unused, unexpired and the account is active.
function findValidPasswordReset(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT pr.reset_id, pr.user_id
         FROM password_resets AS pr
         INNER JOIN users AS u ON u.user_id = pr.user_id
         WHERE pr.token_hash = :token_hash
           AND pr.used_at IS NULL
           AND pr.expires_at > :now
           AND u.status = :status
         LIMIT 1'
    );
    $statement->execute([
        'token_hash' => hash('sha256', $token),
        'now' => date('Y-m-d H:i:s'),
        'status' => 'active',
    ]);

    return $statement->fetch() ?: null;
}

function resetPassword(PDO $pdo, string $token, string $password): array
{
    $invalid = [
        'success' => false,
        'message' => 'This reset link is invalid or has expired. Please request a new one.',
    ];

    $passwordErrors = validatePassword($password);

    if ($passwordErrors) {
        return ['success' => false, 'message' => implode(' ', $passwordErrors)];
    }

    $reset = findValidPasswordReset($pdo, $token);

    if (!$reset) {
        return $invalid;
    }

    try {
        $pdo->beginTransaction();

        // Claim the token. The "used_at IS NULL" condition makes it single-use even under races.
        $claim = $pdo->prepare(
            'UPDATE password_resets SET used_at = :now WHERE reset_id = :reset_id AND used_at IS NULL'
        );
        $claim->execute(['now' => date('Y-m-d H:i:s'), 'reset_id' => $reset['reset_id']]);

        if ($claim->rowCount() !== 1) {
            $pdo->rollBack();

            return $invalid;
        }

        $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :user_id');
        $update->execute([
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'user_id' => $reset['user_id'],
        ]);

        // Remove any other outstanding tokens for this user.
        $cleanup = $pdo->prepare(
            'DELETE FROM password_resets WHERE user_id = :user_id AND reset_id <> :reset_id'
        );
        $cleanup->execute(['user_id' => $reset['user_id'], 'reset_id' => $reset['reset_id']]);

        $pdo->commit();

        return ['success' => true, 'message' => 'Your password has been reset. You can now sign in.'];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('IAIMS password reset error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Password reset failed. Please try again.'];
    }
}

function sendPasswordResetEmail(string $email, string $token): bool
{
    $link = APP_URL . '/?page=reset-password&token=' . urlencode($token);
    $minutes = PASSWORD_RESET_TTL_MINUTES;

    // XAMPP/WAMP can't send mail, so in development the link goes to the PHP error log.
    if (APP_ENV === 'development') {
        error_log("Password reset link for {$email}: {$link}");

        return true;
    }

    $subject = 'Reset your password';
    $body = "Hello,\r\n\r\n"
        . "We received a request to reset your password. Use the link below within {$minutes} minutes:\r\n\r\n"
        . "{$link}\r\n\r\n"
        . "If you did not request this, you can ignore this email.\r\n";
    $headers = 'From: ' . MAIL_FROM . "\r\nContent-Type: text/plain; charset=UTF-8";

    return mail($email, $subject, $body, $headers);
}
