<?php
require_once __DIR__ . '/../includes/data.php';

$token = $_GET['token'] ?? '';
$hash = is_string($token) ? hash('sha256', $token) : '';
$state = adminDemoState();
$invite = $hash !== '' ? ($state['invites'][$hash] ?? null) : null;
$userId = is_array($invite) ? (int)($invite['user_id'] ?? 0) : 0;
$user = $state['users'][$userId] ?? null;
$valid = is_array($invite) && (int)($invite['expires'] ?? 0) >= time() && is_array($user) && $user['status'] === 'Invited';
$message = '';
$accepted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $message = 'Your session expired. Refresh and try again.';
    } elseif (!$valid) {
        http_response_code(410);
        $message = 'This invitation is invalid, expired, or already used.';
    } else {
        $state['users'][$userId]['status'] = 'Active';
        $state['users'][$userId]['note'] = '';
        $state['users'][$userId]['password_set'] = date('j M');
        unset($state['invites'][$hash]);
        array_unshift($state['audit'], [
            'date' => date('Y-m-d'), 'time' => date('d M H:i'), 'actor' => $user['email'],
            'actor_role' => $user['role'], 'action' => 'user.invite_accepted', 'target' => $user['name'],
            'outcome' => 'Success', 'changes' => ['Status' => 'Invited to Active'],
            'device' => ['Browser' => 'Local browser', 'System' => PHP_OS_FAMILY, 'Screen' => 'Not collected',
                'Timezone, language' => date_default_timezone_get(), 'IP' => 'local', 'Device ID' => 'not collected'],
        ]);
        adminSaveDemoState($state);
        $valid = false;
        $accepted = true;
        $message = 'Invitation accepted. This demo account is now marked Active; it is not connected to sign-in credentials.';
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
      <p>You are accepting an invitation for <strong><?= h($user['name']) ?></strong> (<?= h($user['role']) ?>).</p>
      <p class="text-muted small">This localhost demo records the account as active. It does not create a production password or sign-in account.</p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <button class="btn btn-blue" type="submit">Accept invitation</button>
      </form>
    <?php else: ?>
      <div class="alert alert-warning" role="alert">This invitation is invalid, expired, or already used.</div>
    <?php endif; ?>
    <a class="link-blue d-inline-block mt-3" href="<?= h(BASE_URL) ?>/">Return to sign in</a>
  </main>
</body>
</html>
