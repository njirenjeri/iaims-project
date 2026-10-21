<?php
$active = 'users'; $pageTitle = 'User details';
require __DIR__ . '/../includes/header.php';

$u = getUser((int)($_GET['id'] ?? 0));
if (!$u): ?>
  <header class="page-head"><h1 class="h4 mb-0">User not found</h1></header>
  <div class="panel"><p class="mb-2">This user doesn't exist or is outside your ministry.</p>
    <a class="link-blue" href="<?= h(adminUrl('admin-users')) ?>"><i class="bi bi-arrow-left"></i> Back to users</a></div>
<?php require __DIR__ . '/../includes/footer.php'; exit; endif;

$statusCls = ['Active' => 'status-ok', 'Invited' => 'status-invited', 'Suspended' => 'status-suspended', 'Archived' => 'status-muted'];
$details = [
    'Ministry'     => $u['ministry'],
    'Phone'        => $u['phone'],
    'Invited by'   => $u['invited_by'] . ', ' . $u['invited_on'],
    'Password set' => $u['password_set'],
    'Last sign-in' => $u['last_full'] ?? $u['last'],
];
$canResend = $u['status'] === 'Invited';
?>
<a class="link-blue small d-inline-block mb-3" href="<?= h(adminUrl('admin-users')) ?>"><i class="bi bi-arrow-left"></i> Back to users</a>

<header class="page-head">
  <div class="d-flex gap-3 align-items-center">
    <button class="btn btn-menu d-lg-none" id="menuBtn" aria-label="Open menu"><i class="bi bi-list"></i></button>
    <div class="avatar avatar-lg"><?= h(initials($u['name'])) ?></div>
    <div>
      <h1 class="h5 mb-0"><?= h($u['name']) ?></h1>
      <div class="text-muted small"><?= h($u['email']) ?></div>
    </div>
  </div>
  <div class="d-flex gap-2">
    <span class="status status-role"><?= h($u['role']) ?></span>
    <span class="status <?= $statusCls[$u['status']] ?? 'status-muted' ?>"><?= h($u['status']) ?></span>
  </div>
</header>

<div class="row g-3">
  <div class="col-lg-5">
    <section class="panel mb-3">
      <h2 class="panel-title">Details</h2>
      <dl class="detail-list mb-0">
        <?php foreach ($details as $k => $v): ?>
          <div><dt><?= h($k) ?></dt><dd><?= h($v) ?></dd></div>
        <?php endforeach; ?>
        <div><dt>Two-factor</dt><dd class="text-muted">Not available yet</dd></div>
      </dl>
    </section>

    <section class="panel" id="accountActions" data-user-id="<?= (int)$u['id'] ?>"
             data-user-name="<?= h($u['name']) ?>" data-user-ministry="<?= h($u['ministry']) ?>"
             data-user-scope="<?= h($u['scope']) ?>"
             data-ministries="<?= h(json_encode(getMinistries(), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG)) ?>">
      <h2 class="panel-title">Account actions</h2>
      <div class="d-grid gap-2">
        <button class="btn btn-action" data-account-action="force_reset"><i class="bi bi-key"></i> Force password reset</button>
        <button class="btn btn-action" data-account-action="resend_invite" <?= $canResend ? '' : 'disabled' ?>
                title="<?= $canResend ? '' : 'Only available while the user is Invited' ?>">
          <i class="bi bi-envelope"></i> Resend invite (only while Invited)</button>
        <button class="btn btn-action" data-account-action="change_role"><i class="bi bi-person-gear"></i> Change role or scope</button>
        <?php if ($u['status'] !== 'Suspended'): ?>
          <button class="btn btn-action btn-action-danger" data-account-action="suspend"><i class="bi bi-slash-circle"></i> Suspend account</button>
        <?php else: ?>
          <button class="btn btn-action" data-account-action="reactivate"><i class="bi bi-arrow-counterclockwise"></i> Reactivate account</button>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="col-lg-7">
    <section class="panel h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="panel-title mb-0">Recent activity</h2>
        <a class="link-blue small" href="<?= h(adminUrl('admin-audit')) ?>">Full audit log</a>
      </div>
      <ul class="timeline list-unstyled mb-0">
        <?php foreach (getUserActivity($u['id']) as $a): ?>
          <li class="<?= $a['current'] ? 'is-current' : '' ?>">
            <div class="fw-medium"><?= h($a['title']) ?></div>
            <div class="text-muted small"><?= h($a['meta']) ?> &middot; <?= h($a['when']) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
</div>

<!-- Confirmation modal for account actions -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="actionForm">
      <div class="modal-header">
        <h2 class="modal-title h5" id="actionTitle"></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="actionText" class="mb-3"></p>
        <div id="roleGroup" hidden>
          <label class="form-label" for="newRole">New role</label>
          <select class="form-select mb-3" id="newRole" name="role">
            <?php foreach (assignableRoles() as $r): ?><option <?= $r === $u['role'] ? 'selected' : '' ?>><?= h($r) ?></option><?php endforeach; ?>
          </select>
          <label class="form-label" for="newScope">Ministry or state department</label>
          <select class="form-select" id="newScope" name="scope" required></select>
        </div>
        <div id="reasonGroup" hidden>
          <label class="form-label" for="reason">Reason (recorded in the audit log)</label>
          <input class="form-control" id="reason" name="reason" maxlength="200">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-blue" id="actionConfirm">Confirm</button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
