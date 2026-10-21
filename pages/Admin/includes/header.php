<?php
require_once __DIR__ . '/data.php';
if (!defined('IAIMS_BOOTSTRAPPED')) {
    http_response_code(404);
    exit('Not found.');
}
adminRequireAccess();
$active    = $active    ?? '';
$pageTitle = $pageTitle ?? 'Admin dashboard';

$pending = 0;
foreach (getStats() as $s) { if ($s['id'] === 'pending') $pending = (int)$s['value']; }

$nav = [
    'dashboard' => [adminUrl('admin-dashboard'), 'bi-grid-1x2', 'Dashboard'],
    'users'     => [adminUrl('admin-users'), 'bi-people', 'Users'],
    'invite'    => [adminUrl('admin-invite-page'), 'bi-envelope-plus', 'Invite user'],
    'audit'     => [adminUrl('admin-audit'), 'bi-journal-text', 'Audit log'],
    'security'  => [adminUrl('admin-security'), 'bi-shield-lock', 'Security'],
    'settings'  => [adminUrl('admin-settings'), 'bi-gear', 'Settings'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle) ?> · Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= h(BASE_URL) ?>/pages/Admin/includes/style.css" rel="stylesheet">
</head>
<body>

<aside class="sidebar" id="sidebar" aria-label="Main navigation">
  <div class="sidebar-brand">
    <i class="bi bi-shield-check"></i>
    <span>Admin</span>
  </div>
  <nav class="nav flex-column">
    <?php foreach ($nav as $key => [$href, $icon, $label]): ?>
      <a class="nav-link <?= $key === $active ? 'active' : '' ?>" href="<?= h($href) ?>"
         <?= $key === $active ? 'aria-current="page"' : '' ?>>
        <i class="bi <?= h($icon) ?>"></i> <?= h($label) ?>
        <?php if ($key === 'users' && $pending): ?><span class="badge-gold" title="Pending invites"><?= $pending ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="avatar"><?= h(initials(currentRole())) ?></div>
    <div>
    <div class="fw-semibold"><?= h(currentRole()) ?></div>
    <form method="post" action="<?= h(adminUrl('logout')) ?>">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <button type="submit" class="btn btn-link p-0 small text-decoration-none">Sign out</button>
    </form>
    </div>
  </div>
</aside>
<div class="sidebar-backdrop" id="backdrop"></div>

<main class="content" data-admin-api="<?= h(adminUrl('admin-api')) ?>" data-csrf-token="<?= h(csrfToken()) ?>">
