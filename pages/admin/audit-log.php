<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/audit_viewer.php';
require_once __DIR__ . '/../../includes/layout.php';

requireAnyRole($pdo, ['ADMIN']);

$filters = auditFiltersFromRequest($_GET);

// CSV download of the filtered log. The export itself is recorded first; if that fails, nothing is sent.
if (($_GET['export'] ?? '') === 'csv') {
    try {
        auditLog($pdo, 'audit.exported', 'success', ['details' => ['filters' => $filters, 'row_limit' => AUDIT_EXPORT_LIMIT]]);
    } catch (Throwable $exception) {
        error_log('IAIMS audit export refused: ' . $exception->getMessage());
        http_response_code(500);
        exit('The export could not be recorded, so it was not run.');
    }

    streamAuditCsv($pdo, $filters);
    exit;
}

$perPage = AUDIT_PAGE_SIZE;
$page = max(1, (int) ($_GET['p'] ?? 1));

$chain = auditChainStatus($pdo, isset($_GET['verify']));
$result = searchAuditLog($pdo, $filters, $page, $perPage);
$sessions = auditSessionDetails($pdo, array_column($result['rows'], 'session_record_id'));
$actions = auditActionOptions($pdo);

// Opening the log is itself recorded (after the query, so it shows up next time).
auditLogSafe($pdo, 'audit.viewed', 'success', ['details' => ['filters' => $filters, 'page' => $page]]);

$roleOptions = ['STUDENT' => 'Student'] + STAFF_ROLES;
$linkFilters = $filters;
$chainValid = $chain['result']['valid'];

staffPageStart('Audit log', 'audit');
?>
<?= renderFlash() ?>

<div class="profile-heading mb-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
        <p class="text-uppercase text-primary fw-semibold small mb-2">Security</p>
        <h1 class="h2 fw-bold mb-2 text-capitalize">Audit log</h1>
        <p class="text-secondary mb-0">Read-only. Opening and exporting the log are recorded too.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= escapeHtml(urlWith('audit-log', $linkFilters + ['export' => 'csv'])) ?>">
        <i class="bi bi-download me-2" aria-hidden="true"></i>Export CSV
    </a>
</div>

<div class="alert <?= $chainValid ? 'alert-success' : 'alert-danger' ?> d-flex flex-column flex-sm-row justify-content-between gap-2" role="status">
    <div>
        <?php if ($chainValid): ?>
            <i class="bi bi-shield-check me-1" aria-hidden="true"></i>
            <strong>Chain verified.</strong> <?= (int) $chain['result']['checked'] ?> entries checked, none altered or removed.
        <?php else: ?>
            <i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>
            <strong>The log has been altered.</strong> The chain breaks at entry <?= (int) $chain['result']['broken_at'] ?>.
            Preserve the database and investigate before making any changes.
        <?php endif; ?>
        <div class="small mt-1">
            Newest entry <?= (int) $chain['anchor']['audit_id'] ?>, hash <span class="font-monospace"><?= escapeHtml(substr($chain['anchor']['row_hash'], 0, 16)) ?>…</span>
            · checked <?= escapeHtml(date('H:i', (int) $chain['at'])) ?>
        </div>
    </div>
    <div><a class="alert-link small" href="<?= escapeHtml(urlWith('audit-log', $linkFilters + ['verify' => 1])) ?>">Verify now</a></div>
</div>

<form method="get" action="<?= escapeHtml(BASE_URL) ?>/" class="row g-2 mb-3">
    <input type="hidden" name="page" value="audit-log">
    <div class="col-12 col-lg-4">
        <input type="search" class="form-control" name="q" value="<?= escapeHtml($filters['q']) ?>"
               placeholder="Search email, target, IP or user id" aria-label="Search">
    </div>
    <div class="col-6 col-lg-3">
        <select class="form-select" name="action" aria-label="Action">
            <option value="">All actions</option>
            <?php foreach ($actions as $action): ?>
                <option value="<?= escapeHtml($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>><?= escapeHtml(auditActionLabel($action)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-lg-2">
        <select class="form-select" name="outcome" aria-label="Outcome">
            <option value="">All outcomes</option>
            <?php foreach (AUDIT_OUTCOMES as $outcome): ?>
                <option value="<?= escapeHtml($outcome) ?>" <?= $filters['outcome'] === $outcome ? 'selected' : '' ?>><?= escapeHtml(ucfirst($outcome)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12 col-lg-3">
        <select class="form-select" name="role" aria-label="Actor role">
            <option value="">All actor roles</option>
            <?php foreach ($roleOptions as $key => $label): ?>
                <option value="<?= escapeHtml($key) ?>" <?= $filters['role'] === $key ? 'selected' : '' ?>><?= escapeHtml($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-lg-3">
        <input type="date" class="form-control" name="from" value="<?= escapeHtml($filters['from']) ?>" aria-label="From date">
    </div>
    <div class="col-6 col-lg-3">
        <input type="date" class="form-control" name="to" value="<?= escapeHtml($filters['to']) ?>" aria-label="To date">
    </div>
    <div class="col-12 col-lg-3 d-flex gap-2">
        <button type="submit" class="btn btn-outline-secondary flex-fill">Filter</button>
        <a class="btn btn-link link-gold text-decoration-none" href="<?= escapeHtml(urlWith('audit-log')) ?>">Clear</a>
    </div>
</form>

<section class="card profile-card" aria-label="Audit entries">
    <?php if (!$result['rows']): ?>
        <div class="text-center p-4 p-md-5">
            <h2 class="h6 fw-semibold">No entries match</h2>
            <p class="text-secondary small mb-0">Try widening the dates or clearing the filters.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Time</th><th>Actor</th><th>Action</th><th>Target</th><th>Outcome</th><th><span class="visually-hidden">Details</span></th></tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $row): ?>
                    <?php
                    $entryId = (int) $row['audit_id'];
                    $device = $sessions[(int) $row['session_record_id']] ?? null;
                    $details = json_decode($row['details'], true);
                    ?>
                    <tr>
                        <td class="small text-nowrap"><?= escapeHtml(formatWhen($row['occurred_at'], 'j M Y, H:i:s')) ?></td>
                        <td class="small">
                            <?= escapeHtml($row['actor_email'] !== '' ? $row['actor_email'] : 'Not signed in') ?>
                            <?php if ($row['actor_roles'] !== ''): ?><div class="text-secondary"><?= escapeHtml($row['actor_roles']) ?></div><?php endif; ?>
                        </td>
                        <td>
                            <?= escapeHtml(auditActionLabel($row['action'])) ?>
                            <div class="small text-secondary font-monospace"><?= escapeHtml($row['action']) ?></div>
                        </td>
                        <td class="small"><?= escapeHtml($row['target_label']) ?></td>
                        <td><?= auditOutcomeBadge($row['outcome']) ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                                    data-bs-target="#entry-<?= $entryId ?>" aria-expanded="false" aria-controls="entry-<?= $entryId ?>">
                                Details
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="6" class="p-0 border-0">
                            <div class="collapse" id="entry-<?= $entryId ?>">
                                <div class="row g-3 p-3 bg-body-tertiary">
                                    <div class="col-md-6">
                                        <h3 class="h6">What changed</h3>
                                        <?php if (is_array($details) && $details): ?>
                                            <pre class="small bg-body border rounded p-2 mb-1"><?= escapeHtml(json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                                        <?php else: ?>
                                            <p class="small text-secondary mb-1">No extra details.</p>
                                        <?php endif; ?>
                                        <p class="small text-secondary mb-0">Passwords, tokens and hashes are never recorded.</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h3 class="h6">Device and network</h3>
                                        <dl class="row small mb-1">
                                            <dt class="col-5 text-secondary fw-normal">IP address</dt><dd class="col-7 font-monospace"><?= escapeHtml($row['ip_address'] ?: 'Unknown') ?></dd>
                                            <?php if ($device): ?>
                                                <dt class="col-5 text-secondary fw-normal">Browser</dt><dd class="col-7"><?= escapeHtml(trim($device['browser'] . ' ' . $device['browser_version'])) ?></dd>
                                                <dt class="col-5 text-secondary fw-normal">System</dt><dd class="col-7"><?= escapeHtml($device['os'] . ', ' . $device['device_type']) ?></dd>
                                                <dt class="col-5 text-secondary fw-normal">Screen</dt><dd class="col-7"><?= escapeHtml($device['screen'] ?: 'Unknown') ?></dd>
                                                <dt class="col-5 text-secondary fw-normal">Timezone, language</dt><dd class="col-7"><?= escapeHtml(($device['timezone'] ?: 'Unknown') . ', ' . ($device['language'] ?: 'Unknown')) ?></dd>
                                                <dt class="col-5 text-secondary fw-normal">Device ID</dt><dd class="col-7 font-monospace"><?= escapeHtml(substr($device['device_hash'], 0, 8)) ?>… <?= $device['is_new_device'] ? '(new at sign-in)' : '' ?></dd>
                                                <dt class="col-5 text-secondary fw-normal">Session started</dt><dd class="col-7"><?= escapeHtml(formatWhen($device['started_at'])) ?></dd>
                                            <?php endif; ?>
                                            <dt class="col-5 text-secondary fw-normal">User agent</dt><dd class="col-7 text-break"><?= escapeHtml($row['user_agent'] ?: 'Unknown') ?></dd>
                                            <dt class="col-5 text-secondary fw-normal">Request</dt><dd class="col-7 font-monospace text-break"><?= escapeHtml(trim($row['request_method'] . ' ' . $row['request_path'])) ?></dd>
                                        </dl>
                                    </div>
                                    <div class="col-12 small text-secondary font-monospace text-break">
                                        Entry <?= $entryId ?> · hash <?= escapeHtml(substr($row['row_hash'], 0, 16)) ?>… · previous <?= escapeHtml(substr($row['prev_hash'], 0, 16)) ?>…
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-top">
            <span class="small text-secondary">
                Showing <?= ($page - 1) * $perPage + 1 ?> to <?= min($page * $perPage, $result['total']) ?> of <?= (int) $result['total'] ?>
            </span>
            <?= renderPagination('audit-log', $linkFilters, $page, $result['total'], $perPage) ?>
        </div>
    <?php endif; ?>
</section>
<?php staffPageEnd(); ?>
