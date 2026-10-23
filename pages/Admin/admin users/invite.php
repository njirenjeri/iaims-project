<?php
$active = 'invite'; $pageTitle = 'Invite a user';
require __DIR__ . '/../includes/header.php';

$ministries = getMinistries();
$allowed    = assignableRoles();
?>
<header class="page-head">
  <div class="d-flex gap-3 align-items-start">
    <button class="btn btn-menu d-lg-none" id="menuBtn" aria-label="Open menu"><i class="bi bi-list"></i></button>
    <div>
      <h1 class="h4 mb-0">Invite a user</h1>
      <p class="text-muted small mb-0">The person gets a link to set their own password.</p>
    </div>
  </div>
</header>

<form class="panel" id="inviteForm" novalidate data-ministries='<?= h(json_encode($ministries)) ?>'>
  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label" for="first_name">First name</label>
      <input class="form-control" id="first_name" name="first_name" autocomplete="off" required>
      <div class="invalid-feedback"></div>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="other_names">Other name(s)</label>
      <input class="form-control" id="other_names" name="other_names" autocomplete="off" required>
      <div class="invalid-feedback"></div>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="email">Email</label>
      <input class="form-control" id="email" name="email" type="email" autocomplete="off" required>
      <div class="invalid-feedback"></div>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="phone">Phone</label>
      <input class="form-control" id="phone" name="phone" type="tel" placeholder="+254 7XX XXX XXX" required>
      <div class="invalid-feedback"></div>
    </div>
  </div>

  <fieldset class="mt-3">
    <legend class="form-label fs-6">Role</legend>
    <div class="row g-2" id="roleCards">
      <?php foreach (allRoles() as $r): $ok = in_array($r, $allowed, true); ?>
        <div class="col-6 col-lg-3">
          <input class="role-input" type="radio" name="role" id="role-<?= h(strtolower(str_replace(' ', '-', $r))) ?>"
                 value="<?= h($r) ?>" <?= $ok ? '' : 'disabled' ?>>
          <label class="role-card" for="role-<?= h(strtolower(str_replace(' ', '-', $r))) ?>">
            <span class="role-name"><?= h($r) ?></span>
            <?php if (!$ok): ?><span class="role-sub">Super admin only</span><?php endif; ?>
          </label>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="text-danger small mt-1" id="roleError" hidden></div>
    <?php if (!isSuperAdmin()): ?>
      <p class="text-muted small mt-2 mb-0">An HR admin creating a user sees only HR officer and Supervisor, and only for their own ministry.</p>
    <?php endif; ?>
  </fieldset>

  <div class="row g-3 mt-1">
    <div class="col-md-6">
      <label class="form-label" for="ministry">Ministry</label>
      <select class="form-select" id="ministry" name="ministry" required>
        <?php if (count($ministries) > 1): ?><option value="">Choose a ministry</option><?php endif; ?>
        <?php foreach (array_keys($ministries) as $m): ?><option><?= h($m) ?></option><?php endforeach; ?>
      </select>
      <div class="invalid-feedback"></div>
    </div>
    <div class="col-md-6" id="deptGroup" hidden>
      <label class="form-label" for="department">State department (supervisors only)</label>
      <select class="form-select" id="department" name="department"></select>
      <div class="invalid-feedback"></div>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4">
    <span class="text-muted small">Delivery: link shown on screen (email not configured)</span>
    <button type="submit" class="btn btn-blue">Create and generate invite link</button>
  </div>
</form>

<section class="panel mt-3 invite-result" id="inviteResult" hidden aria-live="polite">
  <h2 class="panel-title">Invite created</h2>
  <p class="mb-2" id="inviteSummary"></p>
  <div class="input-group">
    <input class="form-control" id="inviteLink" readonly aria-label="Invite link">
    <button class="btn btn-outline-gold" type="button" id="copyLink"><i class="bi bi-clipboard"></i> Copy link</button>
  </div>
  <p class="text-muted small mt-2 mb-0">Share this link only with the invited person. It expires in 72 hours.</p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
