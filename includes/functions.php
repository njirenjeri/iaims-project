<?php

// Escape values before displaying them in HTML (prevents XSS).
function escapeHtml($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// CSRF helpers.
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf($token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// Generate a version 4 UUID.
function generateUuid(): string
{
    $data = random_bytes(16);

    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// Must stay in sync with the rules in assets/js/app.js.
function validatePassword(string $password): array
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }

    // bcrypt ignores everything after 72 bytes.
    if (strlen($password) > 72) {
        $errors[] = 'Password must not exceed 72 characters.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }

    if (!preg_match('/[^A-Za-z0-9\s]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }

    return $errors;
}

function registerStudent(PDO $pdo, string $email, string $password): array
{
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }

    $passwordErrors = validatePassword($password);

    if ($passwordErrors) {
        return ['success' => false, 'message' => implode(' ', $passwordErrors)];
    }

    try {
        $roleStatement = $pdo->prepare('SELECT role_id FROM roles WHERE name = :name LIMIT 1');
        $roleStatement->execute(['name' => 'STUDENT']);
        $role = $roleStatement->fetch();

        if (!$role) {
            error_log('IAIMS setup error: STUDENT role is missing.');

            return ['success' => false, 'message' => 'Registration is temporarily unavailable.'];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $userId = generateUuid();

        // Create the account and role assignment atomically.
        $pdo->beginTransaction();

        $userStatement = $pdo->prepare(
            'INSERT INTO users (user_id, email, password_hash, status)
             VALUES (:user_id, :email, :password_hash, :status)'
        );
        $userStatement->execute([
            'user_id' => $userId,
            'email' => $email,
            'password_hash' => $passwordHash,
            'status' => 'active',
        ]);

        $assignStatement = $pdo->prepare(
            'INSERT INTO user_roles (user_id, role_id, assigned_by)
             VALUES (:user_id, :role_id, NULL)'
        );
        $assignStatement->execute([
            'user_id' => $userId,
            'role_id' => $role['role_id'],
        ]);

        $pdo->commit();

        return ['success' => true, 'message' => 'Your account has been created. You can now sign in.'];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // MySQL error 1062 = duplicate unique value.
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            return ['success' => false, 'message' => 'An account with that email already exists.'];
        }

        error_log('IAIMS registration error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

function authenticateUser(PDO $pdo, string $email, string $password): ?array
{
    $email = strtolower(trim($email));

    $statement = $pdo->prepare(
        'SELECT user_id, email, password_hash, status
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    // Always run a verification so unknown emails take as long as wrong passwords.
    $hash = $user['password_hash'] ?? ('$2y$10$' . str_repeat('.', 53));
    $passwordOk = password_verify($password, $hash);

    if (!$user || !$passwordOk || $user['status'] !== 'active') {
        return null;
    }

    // Upgrade the stored hash if PHP's default algorithm/cost has changed.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $rehash = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :user_id');
        $rehash->execute([
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'user_id' => $user['user_id'],
        ]);
    }

    $roleStatement = $pdo->prepare(
        'SELECT r.name
         FROM roles AS r
         INNER JOIN user_roles AS ur ON ur.role_id = r.role_id
         WHERE ur.user_id = :user_id'
    );
    $roleStatement->execute(['user_id' => $user['user_id']]);

    $user['roles'] = array_column($roleStatement->fetchAll(), 'name');

    unset($user['password_hash']);

    return $user;
}

/* ---------- Password reset ---------- */

// Returns a plain token to email to the user, or null if nothing should be sent.
// Only a SHA-256 hash of the token is stored, so a database leak can't be used to reset accounts.
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

// Render a dismissible Bootstrap alert that closes itself after a timeout.
function renderAlert(string $type, string $message): string
{
    $allowed = ['success', 'danger', 'warning', 'info'];
    $type = in_array($type, $allowed, true) ? $type : 'info';


    $timeout = $type === 'danger' ? 8000 : 5000;

    return sprintf(
        '<div class="alert alert-%s alert-dismissible fade show" role="alert" data-timeout="%d">
            %s
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>',
        $type,
        $timeout,
        escapeHtml($message)
    );
}
