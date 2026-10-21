<?php
$active = 'audit'; $pageTitle = 'Audit log';
require __DIR__ . '/../includes/header.php';

$log     = getAuditLog();
$actions = array_values(array_unique(array_column($log, 'action')));
$actors  = array_values(array_unique(array_column($log, 'actor')));
sort($actions);
sort($actors);
?>
<header class="page-head">
  <div class="d-flex gap-3 align-items-start">
    <button class="btn btn-menu d-lg-none" id="menuBtn" aria-label="Open menu"><i class="bi bi-list"></i></button>
    <div>
      <h1 class="h4 mb-0">Audit log</h1>
      <p class="text-muted small mb-0">Read-only. Viewing and exporting are recorded.</p>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="status status-ok"><i class="bi bi-patch-check"></i> Chain verified</span>
    <button class="btn btn-outline-gold" id="exportCsv"><i class="bi bi-download"></i> Export CSV</button>
  </div>
</header>

<div class="row g-2 mb-3">
  <div class="col-12 col-md-6 col-lg-2">
    <label class="form-label small mb-1" for="actorFilter">Actor</label>
    <select class="form-select" id="actorFilter" aria-label="Filter by actor">
      <option value="">All actors</option>
      <?php foreach ($actors as $actor): ?><option><?= h($actor) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-12 col-md-6 col-lg-2">
    <label class="form-label small mb-1" for="targetFilter">Target</label>
    <input class="form-control" id="targetFilter" type="search" placeholder="Search target" aria-label="Filter by target">
  </div>
  <div class="col-6 col-lg-2">
    <label class="form-label small mb-1" for="actionFilter">Action</label>
    <select class="form-select" id="actionFilter" aria-label="Filter by action">
      <option value="">All actions</option>
      <?php foreach ($actions as $a): ?><option><?= h($a) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-lg-2">
    <label class="form-label small mb-1" for="outcomeFilter">Outcome</label>
    <select class="form-select" id="outcomeFilter" aria-label="Filter by outcome">
      <option value="">All outcomes</option><option>Success</option><option>Denied</option>
    </select>
  </div>
  <div class="col-6 col-lg-2">
    <label class="form-label small mb-1" for="dateFrom">From</label>
    <input class="form-control" id="dateFrom" type="date" aria-label="From date">
  </div>
  <div class="col-6 col-lg-2">
    <label class="form-label small mb-1" for="dateTo">To</label>
    <input class="form-control" id="dateTo" type="date" aria-label="To date">
  </div>
</div>

<div class="panel p-0">
  <div class="table-responsive">
    <table class="table align-middle mb-0 data-table" id="auditTable">
      <thead>
        <tr><th>Time</th><th>Actor</th><th>Action</th><th>Target</th><th>Outcome</th></tr>
      </thead>
      <tbody>
        <?php foreach ($log as $i => $r): ?>
          <tr class="audit-row" tabindex="0" role="button" aria-expanded="false" aria-controls="detail-<?= $i ?>"
              data-date="<?= h($r['date']) ?>" data-action="<?= h($r['action']) ?>" data-outcome="<?= h($r['outcome']) ?>"
              data-time="<?= h($r['time']) ?>" data-actor="<?= h($r['actor']) ?>" data-target="<?= h($r['target']) ?>"
              data-ip="<?= h($r['device']['IP']) ?>">
            <td><?= h($r['time']) ?></td>
            <td><?= h($r['actor']) ?><?php if ($r['actor_role']): ?><div class="text-muted small"><?= h($r['actor_role']) ?></div><?php endif; ?></td>
            <td><?= h($r['action']) ?></td>
            <td><?= h($r['target']) ?></td>
            <td><span class="status <?= $r['outcome'] === 'Success' ? 'status-ok' : 'status-denied' ?>"><?= h($r['outcome']) ?></span></td>
          </tr>
          <tr class="audit-detail" id="detail-<?= $i ?>" hidden>
            <td colspan="5">
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="detail-card">
                    <h3>Changes</h3>
                    <dl class="detail-list mb-2">
                      <?php foreach ($r['changes'] as $k => $v): ?><div><dt><?= h($k) ?></dt><dd><?= h($v) ?></dd></div><?php endforeach; ?>
                    </dl>
                    <p class="text-muted small mb-0">Passwords, hashes and tokens are never recorded.</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="detail-card">
                    <h3>Device and network</h3>
                    <dl class="detail-list mb-0">
                      <?php foreach ($r['device'] as $k => $v): ?><div><dt><?= h($k) ?></dt><dd><?= h($v) ?></dd></div><?php endforeach; ?>
                    </dl>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <tr id="noResults" hidden><td colspan="5" class="text-center text-muted py-4">No events match these filters. Widen the date range or clear the search.</td></tr>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
