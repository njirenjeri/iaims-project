<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/hr_dashboard.php';

$department = requireHrAccess($pdo);
$isAdmin = userHasRole('HR_ADMIN');

$stats = hrDashboardStats($pdo, $department['department_id']);
$queue = $stats['queue'];
$capacity = $stats['capacity'];
$settings = $capacity['settings'];

$usedPercent = $capacity['capacity'] > 0 ? min(100, (int) round($capacity['in_use'] / $capacity['capacity'] * 100)) : 0;

// What needs a human to look at it.
$attention = [];

if (!$settings['accepting_applications']) {
    $attention[] = ['text' => 'Applications are paused', 'hint' => $isAdmin ? 'Turn them back on under Capacity and settings' : 'Your HR admin can turn them back on'];
}

if ($capacity['capacity'] === 0) {
    $attention[] = ['text' => 'No capacity is set for this department', 'hint' => $isAdmin ? 'Set the number of places under Capacity and settings' : 'Your HR admin sets the number of places'];
}

if ($stats['awaiting_review'] > 0) {
    $attention[] = [
        'text' => $stats['awaiting_review'] . ($stats['awaiting_review'] === 1 ? ' application' : ' applications') . ' waiting for review',
        'hint' => $queue['submitted'] > 0 ? 'The oldest has waited ' . $stats['oldest_waiting_days'] . ($stats['oldest_waiting_days'] === 1 ? ' day' : ' days') : 'All are being reviewed',
    ];
}

if ($queue['proposed'] > 0) {
    $attention[] = [
        'text' => $queue['proposed'] . ($queue['proposed'] === 1 ? ' place is' : ' places are') . ' reserved and waiting for you to confirm',
        'hint' => 'Choose the supervisor and dates, then send the offer',
    ];
}

if ($queue['offers_ending_soon'] > 0) {
    $attention[] = [
        'text' => $queue['offers_ending_soon'] . ($queue['offers_ending_soon'] === 1 ? ' offer expires' : ' offers expire') . ' within a day',
        'hint' => 'If there is no answer, the place passes to the next student',
    ];
}

$tiles = [
    ['Waiting for review', $queue['submitted'], 'submitted'],
    ['Under review', $queue['under_review'], 'under_review'],
    ['Waitlisted', $queue['waitlisted'], 'waitlisted'],
    ['Accepted', $queue['accepted'], 'accepted'],
    ['Running now', $queue['running'], 'accepted'],
    ['Rejected', $queue['rejected'], 'rejected'],
];

staffPageStart('Dashboard', 'dashboard');
?>
<?= renderFlash() ?>

<div class="profile-heading mb-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
        <p class="text-uppercase text-primary fw-semibold small mb-2"><?= escapeHtml($department['ministry_name']) ?></p>
        <h1 class="h2 fw-bold mb-2"><?= escapeHtml($department['department_name']) ?></h1>
        <p class="text-secondary mb-0">The review queue, waitlist and capacity for your department.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-profile-save" href="<?= escapeHtml(urlWith('hr-queue')) ?>">
            <i class="bi bi-inboxes me-2" aria-hidden="true"></i>Review applications
        </a>
        <a class="btn btn-outline-secondary" href="<?= escapeHtml(urlWith('hr-capacity')) ?>">
            <i class="bi bi-sliders me-2" aria-hidden="true"></i>Capacity and settings
        </a>
    </div>
</div>

<section class="mb-4" aria-label="Applications and placements">
    <div class="row g-3">
        <?php foreach ($tiles as [$label, $value, $filter]): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <a class="card border-0 shadow-sm h-100 text-decoration-none text-reset" href="<?= escapeHtml(urlWith('hr-queue', ['status' => $filter])) ?>">
                    <div class="card-body">
                        <div class="fs-4 fw-semibold lh-1"><?= (int) $value ?></div>
                        <div class="small text-secondary"><?= escapeHtml($label) ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="row g-4">
    <div class="col-xl-5">
        <section class="card profile-card h-100" aria-labelledby="capacity-heading">
            <div class="card-header profile-card-header">
                <span class="section-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
                <div><h2 class="h5 mb-1" id="capacity-heading">Places</h2><p class="small text-secondary mb-0">In use today, out of the department's capacity</p></div>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <span class="display-6 fw-semibold lh-1"><?= (int) $capacity['free'] ?></span>
                    <span class="text-secondary">free of <?= (int) $capacity['capacity'] ?></span>
                </div>
                <div class="progress mb-2" role="progressbar" aria-label="Places in use" aria-valuenow="<?= $usedPercent ?>" aria-valuemin="0" aria-valuemax="100" style="height: 8px">
                    <div class="progress-bar" style="width: <?= $usedPercent ?>%"></div>
                </div>
                <p class="small text-secondary mb-0">
                    <?= (int) $capacity['in_use'] ?> in use
                    <?php if ($queue['proposed'] > 0): ?>(<?= (int) $queue['proposed'] ?> reserved for the next student)<?php endif; ?>
                    <?php if ($capacity['over'] > 0): ?>· over capacity by <?= (int) $capacity['over'] ?>; no new places open until enough placements end<?php endif; ?>
                </p>
            </div>
        </section>
    </div>

    <div class="col-xl-7">
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
                        <li class="list-group-item py-3">
                            <div><?= escapeHtml($item['text']) ?></div>
                            <div class="small text-secondary"><?= escapeHtml($item['hint']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php staffPageEnd(); ?>
