<?php
$active = 'dashboard'; $pageTitle = 'Admin dashboard';
require __DIR__ . '/../includes/header.php';
?>
<header class="page-head">
  <div class="d-flex gap-3 align-items-start">
    <button class="btn btn-menu d-lg-none" id="menuBtn" aria-label="Open menu"><i class="bi bi-list"></i></button>
    <div>
      <h1 class="h4 mb-0">Admin dashboard</h1>
      <p class="text-muted small mb-0">Signed in as <?= h(currentRole()) ?></p>
    </div>
  </div>
  <a class="btn btn-outline-gold" href="<?= h(adminUrl('admin-invite-page')) ?>"><i class="bi bi-person-plus"></i> Invite user</a>
</header>

<section class="row g-3 mb-4">
  <?php foreach (getStats() as $s): ?>
    <div class="col-6 col-lg-3">
      <div class="stat-card <?= $s['id'] === 'failed' ? 'stat-warn' : '' ?>">
        <div class="stat-label"><?= h($s['label']) ?></div>
        <div class="stat-value"><?= (int)$s['value'] ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<div class="row g-3">
  <section class="col-lg-5">
    <div class="panel h-100">
      <h2 class="panel-title">Needs attention</h2>
      <ul class="list-unstyled mb-0">
        <?php foreach (getAttention() as $a): ?>
          <li class="row-item">
            <div>
              <div><?= h($a['title']) ?></div>
              <div class="text-muted small"><?= h($a['meta']) ?></div>
            </div>
            <a class="link-blue" href="<?= h($a['link']) ?>"><?= h($a['action']) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="col-lg-7">
    <div class="panel h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="panel-title mb-0">Recent audit events</h2>
        <a class="link-blue small" href="<?= h(adminUrl('admin-audit')) ?>">Open log</a>
      </div>
      <ul class="list-unstyled mb-0">
        <?php foreach (getAuditEvents() as $e): ?>
          <li class="row-item">
            <div>
              <div><?= h($e['event']) ?></div>
              <div class="text-muted small"><?= h($e['by']) ?> &middot; <?= h($e['when']) ?></div>
            </div>
            <span class="status <?= $e['status'] === 'Success' ? 'status-ok' : 'status-denied' ?>"><?= h($e['status']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
