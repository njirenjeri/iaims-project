<?php
$active = 'security';
$pageTitle = 'Security';
require __DIR__ . '/../includes/header.php';
if (!isSuperAdmin()) {
    http_response_code(403);
    exit('Only a super administrator can change security settings.');
}
$security = adminDemoState()['security'];
?>
<header class="page-head">
  <div class="d-flex gap-3 align-items-start">
    <button class="btn btn-menu d-lg-none" id="menuBtn" aria-label="Open menu"><i class="bi bi-list"></i></button>
    <div><h1 class="h4 mb-0">Security policy</h1><p class="text-muted small mb-0">Demo controls only; these values do not change production authentication.</p></div>
  </div>
</header>
<form class="panel" id="securityForm">
  <input type="hidden" name="action" value="security_save">
  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label" for="lockout_threshold">Failed sign-ins before lockout</label>
      <input class="form-control" type="number" id="lockout_threshold" name="lockout_threshold" min="3" max="20" value="<?= (int)$security['lockout_threshold'] ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="lockout_minutes">Lockout duration (minutes)</label>
      <input class="form-control" type="number" id="lockout_minutes" name="lockout_minutes" min="1" max="120" value="<?= (int)$security['lockout_minutes'] ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="password_min_length">Minimum password length</label>
      <input class="form-control" type="number" id="password_min_length" name="password_min_length" min="8" max="64" value="<?= (int)$security['password_min_length'] ?>" required>
    </div>
  </div>
  <button type="submit" class="btn btn-blue mt-3">Save security policy</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
