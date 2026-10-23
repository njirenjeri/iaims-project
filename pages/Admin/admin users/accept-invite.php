<?php

require_once __DIR__ . '/../includes/data.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$token = is_string($token) ? $token : '';
$hash = $token !== '' ? hash('sha256', $token) : '';
$message = '';
$errors = [];
$accepted = false;

$state = adminDemoState();
$invite = $hash !== '' ? ($state['invites'][$hash] ?? null) : null;
$userId = is_array($invite) ? (int)($invite['user_id'] ?? 0) : 0;
$user = $state['users'][$userId] ?? null;
$valid = is_array($invite)
    && (int)($invite['expires'] ?? 0) >= time()
    && is_array($user)
    && ($user['status'] ?? '') === 'Invited';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$valid) {
    http_response_code(410);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $message = 'Your session expired. Refresh the page and try again.';
    } elseif (!$valid) {
        http_response_code(410);
        $message = 'This invitation is invalid, expired, or already used.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!is_string($password) || !is_string($confirmPassword)) {
            $errors['password'] = 'Enter a valid password.';
        } else {
            $passwordErrors = validatePassword($password);
            if ($passwordErrors) {
                $errors['password'] = implode(' ', $passwordErrors);
            }
            if ($password !== $confirmPassword) {
                $errors['confirm_password'] = 'The passwords do not match.';
            }
        }

        if (!$errors) {
            require_once __DIR__ . '/../../../config/database.php';

            if (!in_array($user['role'], allRoles(), true)) {
                http_response_code(410);
                $message = 'This invitation has an invalid role. Contact the administrator for a new invite.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $roleQuery = $pdo->prepare('SELECT role_id, name FROM roles');
                    $roleQuery->execute();
                    $roleId = null;
                    foreach ($roleQuery->fetchAll() as $role) {
                        if (normalizedAdminRole((string)$role['name']) === normalizedAdminRole((string)$user['role'])) {
                            $roleId = (int)$role['role_id'];
                            break;
                        }
                    }

                    if ($roleId === null) {
                        $createRole = $pdo->prepare(
                            'INSERT INTO roles (name, description)
                             VALUES (:name, :description)'
                        );
                        $createRole->execute([
                            'name' => $user['role'],
                            'description' => 'Provisioned from a validated administrator invitation.',
                        ]);
                        $roleId = (int)$pdo->lastInsertId();
                    }

                    $inviterQuery = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
                    $inviterQuery->execute(['email' => strtolower(trim((string)($user['invited_by'] ?? '')))]);
                    $assignedBy = $inviterQuery->fetchColumn();
                    $newUserId = generateUuid();

                    $createUser = $pdo->prepare(
                        'INSERT INTO users (user_id, email, password_hash, status)
                         VALUES (:user_id, :email, :password_hash, :status)'
                    );
                    $createUser->execute([
                        'user_id' => $newUserId,
                        'email' => strtolower(trim((string)$user['email'])),
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'status' => 'active',
                    ]);

                    $assignRole = $pdo->prepare(
                        'INSERT INTO user_roles (user_id, role_id, assigned_by)
                         VALUES (:user_id, :role_id, :assigned_by)'
                    );
                    $assignRole->execute([
                        'user_id' => $newUserId,
                        'role_id' => $roleId,
                        'assigned_by' => $assignedBy === false ? null : $assignedBy,
                    ]);

                    $pdo->commit();
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    if ((int)($exception->errorInfo[1] ?? 0) === 1062) {
                        http_response_code(409);
                        $message = 'An account already exists for this email, or the role was created concurrently. Please try again or contact the administrator.';
                    } else {
                        error_log('Admin invitation acceptance failed: ' . $exception->getMessage());
                        http_response_code(500);
                        $message = 'The account could not be created. Please try again later.';
                    }
                }

                if ($message === '') {
                    $state['users'][$userId]['status'] = 'Active';
                    $state['users'][$userId]['note'] = '';
                    $state['users'][$userId]['password_set'] = date('j M');
                    unset($state['invites'][$hash]);
                    array_unshift($state['audit'], [
                        'date' => date('Y-m-d'),
                        'time' => date('d M H:i'),
                        'actor' => $user['email'],
                        'actor_role' => $user['role'],
                        'action' => 'user.invite_accepted',
                        'target' => $user['name'],
                        'outcome' => 'Success',
                        'changes' => ['Status' => 'Invited to Active', 'Password' => 'Set'],
                        'device' => [
                            'Browser' => 'Local browser',
                            'System' => PHP_OS_FAMILY,
                            'Screen' => 'Not collected',
                            'Timezone, language' => date_default_timezone_get(),
                            'IP' => 'local',
                            'Device ID' => 'not collected',
                        ],
                    ]);
                    adminSaveDemoState($state);
                    $accepted = true;
                    $valid = false;
                    $message = 'Invitation accepted. Your account is ready; you can now sign in.';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Accept invitation · Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= h(BASE_URL) ?>/pages/Admin/includes/style.css" rel="stylesheet">
</head>
<body class="d-flex min-vh-100 align-items-center justify-content-center p-3">
  <main class="panel w-100" style="max-width: 32rem">
    <h1 class="h4">Accept admin invitation</h1>
    <?php if ($message !== ''): ?>
      <div class="alert <?= $accepted ? 'alert-success' : 'alert-warning' ?>" role="status"><?= h($message) ?></div>
    <?php elseif ($valid): ?>
      <p>Set a password to activate the invited account for <strong><?= h((string)$user['name']) ?></strong> (<?= h((string)$user['role']) ?>).</p>
      <form method="post" action="<?= h(BASE_URL) ?>/?page=admin-demo-invite">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <div class="mb-3">
          <label class="form-label" for="password">Password</label>
          <input class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                 type="password" id="password" name="password" autocomplete="new-password" required>
          <?php if (isset($errors['password'])): ?>
            <div class="invalid-feedback"><?= h($errors['password']) ?></div>
          <?php else: ?>
            <div class="form-text">Use 8–72 characters with uppercase, lowercase, and a special character.</div>
          <?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label" for="confirm_password">Confirm password</label>
          <input class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                 type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
          <?php if (isset($errors['confirm_password'])): ?>
            <div class="invalid-feedback"><?= h($errors['confirm_password']) ?></div>
          <?php endif; ?>
        </div>
        <button class="btn btn-blue" type="submit">Set password and accept invite</button>
      </form>
    <?php else: ?>
      <div class="alert alert-warning" role="alert">This invitation is invalid, expired, or already used.</div>
    <?php endif; ?>
    <a class="link-blue d-inline-block mt-3" href="<?= h(BASE_URL) ?>/?page=login">Return to sign in</a>
  </main>
</body>
</html>
