<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/student.php';

requireRole('STUDENT');

$userId = $_SESSION['user_id'];

$profileData = getStudentProfile($pdo, $userId);
$profileComplete = isProfileComplete($profileData['saved']);
$completion = profileCompletion($profileData['saved']);
$savedCount = count(array_filter($profileData['saved']));
$p = $profileData['values'];

$counts = getApplicationCounts($pdo, $userId);
$total = array_sum($counts);
$active = $total - $counts['rejected'] - $counts['withdrawn'];
$reviewed = $total - $counts['submitted'] - $counts['withdrawn'];
$recent = listApplications($pdo, $userId, 5);

$steps = [
    ['label' => 'Complete your profile', 'done' => $profileComplete, 'page' => 'profile'],
    ['label' => 'Submit an application', 'done' => $total > 0, 'page' => 'applications'],
    ['label' => 'Application reviewed', 'done' => $reviewed > 0, 'page' => null],
    ['label' => 'Placement confirmed', 'done' => $counts['accepted'] > 0, 'page' => null],
];
$currentStep = array_key_first(array_filter($steps, static fn ($step) => !$step['done']));

$stats = [
    ['Applications', $total, 'bi-files'],
    ['In progress', $active, 'bi-hourglass-split'],
    ['Accepted', $counts['accepted'], 'bi-check-circle'],
    ['Not successful', $counts['rejected'], 'bi-x-circle'],
];

$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Track your profile and industrial attachment applications.">
    <title>Dashboard | <?= escapeHtml(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= escapeHtml(BASE_URL) ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= escapeHtml(BASE_URL) ?>/assets/css/student-layout.css" rel="stylesheet">
</head>
<body class="profile-page">
    <div class="student-shell">
        <?php require __DIR__ . '/../../includes/student_nav.php'; ?>

        <div class="student-main">
            <main class="student-container">
                <?= renderFlash() ?>

                <div class="profile-heading mb-4">
                    <div>
                        <p class="text-uppercase text-primary fw-semibold small mb-2">Student portal</p>
                        <h1 class="h2 fw-bold mb-2">
                            <?php if (!empty($p['first_name'])): ?>Welcome back, <?= escapeHtml($p['first_name']) ?><?php else: ?>Welcome<?php endif; ?>
                        </h1>
                        <p class="text-secondary mb-0">Track your profile and industrial attachment applications.</p>
                    </div>
                </div>

                <?php if (!$profileComplete): ?>
                    <div class="alert alert-warning mb-4" role="status">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-3">
                            <div>
                                <h2 class="h6 fw-semibold mb-1">Next step: complete your profile</h2>
                                <p class="small mb-0">You can't apply until all three sections are saved.</p>
                            </div>
                            <a class="btn btn-profile-save btn-sm px-3" href="<?= escapeHtml(BASE_URL) ?>/?page=profile">Continue profile</a>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Profile completion"
                             aria-valuenow="<?= $completion ?>" aria-valuemin="0" aria-valuemax="100" style="height: 8px">
                            <div class="progress-bar" style="width: <?= $completion ?>%"></div>
                        </div>
                        <div class="small mt-1"><?= $savedCount ?> of <?= count(PROFILE_SECTIONS) ?> sections saved</div>
                    </div>
                <?php elseif ($total === 0): ?>
                    <div class="alert alert-info d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4" role="status">
                        <div>
                            <h2 class="h6 fw-semibold mb-1">You're ready to apply</h2>
                            <p class="small mb-0">Your profile is complete. Choose a department and upload your documents to start your first application.</p>
                        </div>
                        <a class="btn btn-profile-save btn-sm px-3" href="<?= escapeHtml(BASE_URL) ?>/?page=applications">Start application</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light border d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4" role="status">
                        <div>
                            <p class="small text-secondary mb-1">Latest application</p>
                            <p class="mb-0">
                                <span class="fw-semibold"><?= escapeHtml($recent[0]['state_department']) ?></span>
                                <span class="ms-2"><?= statusBadge($recent[0]['status']) ?></span>
                            </p>
                        </div>
                        <a class="btn btn-outline-secondary btn-sm px-3" href="<?= escapeHtml(BASE_URL) ?>/?page=applications">New application</a>
                    </div>

                    <div class="row g-3 mb-4">
                        <?php foreach ($stats as [$label, $value, $icon]): ?>
                            <div class="col-6 col-xl-3">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body d-flex align-items-center gap-3">
                                        <i class="bi <?= $icon ?> fs-3 text-secondary" aria-hidden="true"></i>
                                        <div>
                                            <div class="fs-4 fw-semibold lh-1"><?= (int) $value ?></div>
                                            <div class="small text-secondary"><?= escapeHtml($label) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-xl-8">
                        <section class="card profile-card" aria-labelledby="recent-heading">
                            <div class="card-header profile-card-header">
                                <span class="section-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                                <div><h2 class="h5 mb-1" id="recent-heading">Recent applications</h2><p class="small text-secondary mb-0">Your five most recent submissions</p></div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!$recent): ?>
                                    <div class="text-center p-4 p-md-5">
                                        <h3 class="h6 fw-semibold">Start your first application</h3>
                                        <p class="text-secondary small mb-3">Applications you submit will appear here with their review status.</p>
                                        <a class="btn btn-outline-secondary btn-sm" href="<?= escapeHtml(BASE_URL) ?>/?page=<?= $profileComplete ? 'applications' : 'profile' ?>">
                                            <?= $profileComplete ? 'Start application' : 'Complete profile first' ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table align-middle mb-0">
                                            <thead><tr><th>Ministry / Department</th><th>Submitted</th><th>Status</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($recent as $application): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?= escapeHtml($application['state_department']) ?></div>
                                                        <div class="small text-secondary"><?= escapeHtml($application['ministry']) ?></div>
                                                    </td>
                                                    <td class="text-nowrap"><?= escapeHtml(date('j M Y', strtotime($application['submitted_at']))) ?></td>
                                                    <td><?= statusBadge($application['status']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="p-3 border-top text-end">
                                        <a class="small text-decoration-none link-gold" href="<?= escapeHtml(BASE_URL) ?>/?page=applications">
                                            View all applications <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>

                    <div class="col-xl-4">
                        <section class="card profile-card mb-4" aria-labelledby="progress-heading">
                            <div class="card-header profile-card-header">
                                <span class="section-icon section-icon-gold"><i class="bi bi-signpost-split" aria-hidden="true"></i></span>
                                <div><h2 class="h5 mb-1" id="progress-heading">Your progress</h2></div>
                            </div>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($steps as $index => $step): ?>
                                    <li class="list-group-item d-flex align-items-center gap-3 py-3">
                                        <?php if ($step['done']): ?>
                                            <i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
                                            <span class="visually-hidden">Done:</span>
                                        <?php else: ?>
                                            <i class="bi bi-circle text-secondary" aria-hidden="true"></i>
                                        <?php endif; ?>

                                        <span class="<?= $index === $currentStep ? 'fw-semibold' : 'text-secondary' ?>">
                                            <?= escapeHtml($step['label']) ?>
                                        </span>

                                        <?php if ($index === $currentStep && $step['page']): ?>
                                            <a class="ms-auto small link-gold text-decoration-none" href="<?= escapeHtml(BASE_URL) ?>/?page=<?= $step['page'] ?>">Go</a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>

                        <?php if ($profileComplete): ?>
                            <section class="card profile-card" aria-labelledby="summary-heading">
                                <div class="card-header profile-card-header">
                                    <span class="section-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
                                    <div>
                                        <h2 class="h5 mb-1" id="summary-heading">Your profile</h2>
                                        <p class="small text-secondary mb-0">Updated <?= escapeHtml(date('j M Y', strtotime($profileData['updated_at']))) ?></p>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="fw-semibold mb-1"><?= escapeHtml(trim($p['first_name'] . ' ' . $p['other_names'])) ?></p>
                                    <p class="small text-secondary mb-3">
                                        <?= escapeHtml($p['course_name']) ?> (<?= escapeHtml($p['academic_qualification']) ?>)<br>
                                        <?= escapeHtml($p['institution']) ?>
                                    </p>
                                    <p class="small mb-1"><i class="bi bi-telephone me-2 text-secondary" aria-hidden="true"></i><?= escapeHtml($p['phone_number']) ?></p>
                                    <p class="small mb-3"><i class="bi bi-geo-alt me-2 text-secondary" aria-hidden="true"></i><?= escapeHtml($p['county']) ?> County</p>
                                    <a class="btn btn-outline-secondary btn-sm w-100" href="<?= escapeHtml(BASE_URL) ?>/?page=profile">Edit profile</a>
                                </div>
                            </section>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="text-center text-secondary small mt-4 mb-0">&copy; <?= date('Y') ?> <?= escapeHtml(APP_NAME) ?></p>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= escapeHtml(BASE_URL) ?>/assets/js/alerts.js"></script>
</body>
</html>
