<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/audit_viewer.php';
require_once __DIR__ . '/../../includes/dashboard_stats.php';
require_once __DIR__ . '/../../includes/layout.php';

requireAnyRole($pdo, ['ADMIN']);

$stats = adminDashboardStats($pdo);

// What needs a human to look at it. Empty means all clear.
$attention = [];

if ($stats['stale_invites'] > 0) {
    $attention[] = [
        'text' => $stats['stale_invites'] . ($stats['stale_invites'] === 1 ? ' invite' : ' invites') . ' unused after 48 hours',
        'hint' => 'The link may not have reached them',
        'link' => urlWith('users', ['status' => 'invited']),
        'label' => 'Review',
    ];
}

foreach ($stats['new_devices'] as $event) {
    $details = json_decode($event['details'], true) ?: [];
    $attention[] = [
        'text' => 'New device sign-in: ' . $event['actor_email'],
        'hint' => ($details['device'] ?? 'Unknown device') . ' · ' . formatWhen($event['occurred_at'], 'j M, H:i'),
        'link' => urlWith('audit-log', ['action' => 'auth.new_device_login']),
        'label' => 'Inspect',
    ];
}

if ($stats['failed_logins'] >= 5) {
    $attention[] = [
        'text' => $stats['failed_logins'] . ' failed sign-ins from ' . $stats['failed_ips'] . ($stats['failed_ips'] === 1 ? ' address' : ' addresses'),
        'hint' => 'Last: ' . formatWhen($stats['failed_latest'], 'j M, H:i'),
        'link' => urlWith('audit-log', ['action' => 'auth.login_failed']),
        'label' => 'Inspect',
    ];
}

$cards = [
    ['Active staff', $stats['staff']['active'], 'bi-people'],
    ['Pending invites', $stats['staff']['invited'], 'bi-envelope-paper'],
    ['Suspended', $stats['staff']['suspended'], 'bi-person-slash'],
    ['Failed sign-ins, 24h', $stats['failed_logins'], 'bi-shield-exclamation'],
];

staffPageStart('Admin dashboard', 'dashboard');
?>
<?= renderFlash() ?>

<div class="profile-heading mb-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
        <p class="text-uppercase text-primary fw-semibold small mb-2">System admin</p>
        <h1 class="h2 fw-bold mb-2 text-capitalize">Admin dashboard</h1>
        <p class="text-secondary mb-0">Staff accounts, security events and the ministry directory.</p>
    </div>
    <a class="btn btn-profile-save" href="<?= escapeHtml(urlWith('users-invite')) ?>">
        <i class="bi bi-person-plus me-2" aria-hidden="true"></i>Invite user
    </a>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($cards as [$label, $value, $icon]): ?>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi <?= escapeHtml($icon) ?> fs-3 text-secondary" aria-hidden="true"></i>
                    <div>
                        <div class="fs-4 fw-semibold lh-1"><?= (int) $value ?></div>
                        <div class="small text-secondary"><?= escapeHtml($label) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-xl-5">
        <section class="card profile-card h-100" aria-labelledby="attention-heading">
            <div class="card-header profile-card-header">
                <span class="section-icon section-icon-gold"><i class="bi bi-exclamation-diamond" aria-hidden="true"></i></span>
                <div><h2 class="h5 mb-1" id="attention-heading">Needs attention</h2></div>
            </div>
            <?php if (!$attention): ?>
                <p class="text-secondary p-4 mb-0">Nothing needs attention right now.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($attention as $item): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start gap-3 py-3">
                            <div>
                                <div><?= escapeHtml($item['text']) ?></div>
                                <div class="small text-secondary"><?= escapeHtml($item['hint']) ?></div>
                            </div>
                            <a class="small link-gold text-decoration-none flex-shrink-0" href="<?= escapeHtml($item['link']) ?>"><?= escapeHtml($item['label']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <div class="col-xl-7">
        <section class="card profile-card h-100" aria-labelledby="recent-heading">
            <div class="card-header profile-card-header">
                <span class="section-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></span>
                <div><h2 class="h5 mb-1" id="recent-heading">Recent audit events</h2></div>
                <a class="ms-auto small link-gold text-decoration-none" href="<?= escapeHtml(urlWith('audit-log')) ?>">Open log</a>
            </div>
            <?php if (!$stats['recent']): ?>
                <p class="text-secondary p-4 mb-0">No events recorded yet.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($stats['recent'] as $event): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start gap-3 py-3">
                            <div>
                                <div>
                                    <?= escapeHtml(auditActionLabel($event['action'])) ?>
                                    <?php if ($event['target_label'] !== ''): ?>
                                        <span class="text-secondary">: <?= escapeHtml($event['target_label']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-secondary">
                                    <?= escapeHtml($event['actor_email'] !== '' ? $event['actor_email'] : 'Not signed in') ?>
                                    · <?= escapeHtml(formatWhen($event['occurred_at'], 'j M, H:i')) ?>
                                </div>
                            </div>
                            <?= auditOutcomeBadge($event['outcome']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php staffPageEnd(); ?>
