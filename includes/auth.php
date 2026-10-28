<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/device.php';

// Accounts and sign-in. Pages call the process*() functions and only decide what to show.

/* ---------- Passwords ---------- */

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

/* ---------- Accounts ---------- */

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

        return [
            'success' => true,
            'message' => 'Your account has been created. You can now sign in.',
            'user_id' => $userId,
        ];
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

// Returns the user (with roles) or null. $failureReason says why, for the audit log only.
function authenticateUser(PDO $pdo, string $email, string $password, string &$failureReason = ''): ?array
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
        $failureReason = !$user ? 'unknown_account' : (!$passwordOk ? 'bad_password' : 'inactive');

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

/* ---------- Page-level actions (verify, record, start the session) ---------- */

// Returns ['success' => bool, 'message' => string].
function processRegistration(PDO $pdo, string $email, string $password, string $confirmPassword): array
{
    if ($password !== $confirmPassword) {
        return ['success' => false, 'message' => 'The passwords do not match.'];
    }

    // registerStudent() always assigns the STUDENT role.
    $result = registerStudent($pdo, $email, $password);

    if ($result['success']) {
        auditLogSafe($pdo, 'auth.registered', 'success', [
            'actor' => ['user_id' => $result['user_id'], 'email' => auditEmailLabel($email), 'roles' => 'STUDENT'],
            'target_type' => 'user',
            'target_id' => $result['user_id'],
            'target_label' => auditEmailLabel($email),
        ]);
    } else {
        auditLogSafe($pdo, 'auth.register_failed', 'failure', [
            'target_type' => 'user',
            'target_label' => auditEmailLabel($email),
            'details' => ['reason' => $result['message']],
        ]);
    }

    return ['success' => $result['success'], 'message' => $result['message']];
}

// Verifies the password, records the sign-in and starts the session.
// Returns ['success' => bool, 'message' => string].
function processLogin(PDO $pdo, string $email, string $password, string $deviceInfo): array
{
    $failureReason = '';
    $user = authenticateUser($pdo, $email, $password, $failureReason);

    if ($user === null) {
        // The internal reason (unknown_account, bad_password, inactive) goes to the log only.
        auditLogSafe($pdo, 'auth.login_failed', 'failure', [
            'target_type' => 'user',
            'target_label' => auditEmailLabel($email),
            'details' => ['reason' => $failureReason],
        ]);

        return ['success' => false, 'message' => 'Invalid email or password, or the account is inactive.'];
    }

    $actor = [
        'user_id' => $user['user_id'],
        'email' => $user['email'],
        'roles' => implode(',', $user['roles']),
    ];

    if (empty($user['roles'])) {
        auditLogSafe($pdo, 'auth.login_failed', 'failure', [
            'actor' => $actor,
            'target_type' => 'user',
            'target_id' => $user['user_id'],
            'target_label' => $user['email'],
            'details' => ['reason' => 'no_role'],
        ]);

        return ['success' => false, 'message' => 'Your account has no assigned role. Please contact the administrator.'];
    }

    try {
        $session = startSessionRecord($pdo, $user['user_id'], $deviceInfo);
    } catch (Throwable $exception) {
        error_log('IAIMS session start failed: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'We could not start your session. Please try again.'];
    }

    // Replace the PHP session ID after successful authentication.
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_roles'] = $user['roles'];
    $_SESSION['session_token'] = $session['token'];
    $_SESSION['session_record_id'] = $session['session_record_id'];

    $context = [
        'actor' => $actor,
        'session_record_id' => $session['session_record_id'],
        'target_type' => 'user',
        'target_id' => $user['user_id'],
        'target_label' => $user['email'],
    ];

    auditLogSafe($pdo, 'auth.login_success', 'success', $context + [
        'details' => [
            'device' => $session['summary'],
            'new_device' => $session['is_new_device'],
            'first_session' => $session['first_session'],
        ],
    ]);

    // A known account signing in from a device it has never used before.
    if ($session['is_new_device'] && !$session['first_session']) {
        auditLogSafe($pdo, 'auth.new_device_login', 'success', $context + [
            'details' => ['device' => $session['summary']],
        ]);
    }

    return ['success' => true, 'message' => ''];
}

// Records the sign-out, ends the session record and clears the PHP session.
function processLogout(PDO $pdo): void
{
    if (!empty($_SESSION['user_id'])) {
        // Record it while the session still knows who the actor is.
        auditLogSafe($pdo, 'auth.logout');
        endSessionRecord($pdo, 'logged_out');
    }

    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['auth_success'] = 'You have been signed out.';
}
