<?php
$active = 'settings';
$pageTitle = 'Admin settings';
require __DIR__ . '/../includes/header.php';
if (!isSuperAdmin()) {
    http_response_code(403);
    ?>
    <header class="page-head"><h1 class="h4 mb-0">Access denied</h1></header>
    <section class="panel" role="alert">
      <p>Only a super administrator can change these settings.</p>
      <a class="link-blue" href="<?= h(adminUrl('admin-dashboard')) ?>">Return to the admin dashboard</a>
    </section>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}
$settings = adminDemoState()['settings'];
?>
<header class="page-head">
  <div class="d-flex gap-3 align-items-start">
    <button class="btn btn-menu d-lg-none" id="menuBtn" aria-label="Open menu"><i class="bi bi-list"></i></button>
    <div><h1 class="h4 mb-0">Admin settings</h1><p class="text-muted small mb-0">Saved locally for this demo.</p></div>
  </div>
</header>
<form class="panel" id="settingsForm">
  <input type="hidden" name="action" value="settings_save">
  <div class="mb-3">
    <label class="form-label" for="session_timeout">Session timeout (minutes)</label>
    <input class="form-control" type="number" id="session_timeout" name="session_timeout" min="5" max="240" value="<?= (int)$settings['session_timeout'] ?>" required>
  </div>
  <div class="form-check mb-2">
    <input class="form-check-input" type="checkbox" id="require_mfa" name="require_mfa" <?= !empty($settings['require_mfa']) ? 'checked' : '' ?>>
    <label class="form-check-label" for="require_mfa">Require multi-factor authentication (demo setting)</label>
  </div>
  <div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" <?= !empty($settings['maintenance_mode']) ? 'checked' : '' ?>>
    <label class="form-check-label" for="maintenance_mode">Enable maintenance mode (demo setting)</label>
  </div>
  <button type="submit" class="btn btn-blue">Save settings</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
