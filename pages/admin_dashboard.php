<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');

$applicationCounts = [
    'submitted' => 0,
    'under_review' => 0,
    'accepted' => 0,
    'rejected' => 0,
    'withdrawn' => 0,
];
$dashboardError = false;
$studentCount = 0;
$userCount = 0;
$recentApplications = [];

try {
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    $studentCount = (int) $pdo->query(
        "SELECT COUNT(DISTINCT ur.user_id)
         FROM user_roles AS ur
         INNER JOIN roles AS r ON r.role_id = ur.role_id
         WHERE r.name = 'STUDENT'"
    )->fetchColumn();

    $statusStatement = $pdo->query(
        'SELECT status, COUNT(*) AS total FROM applications GROUP BY status'
    );
    foreach ($statusStatement->fetchAll() as $row) {
        if (array_key_exists($row['status'], $applicationCounts)) {
            $applicationCounts[$row['status']] = (int) $row['total'];
        }
    }

    $recentStatement = $pdo->query(
        'SELECT a.application_id, a.ministry, a.state_department, a.status,
                a.submitted_at, u.email
         FROM applications AS a
         INNER JOIN users AS u ON u.user_id = a.user_id
         ORDER BY a.submitted_at DESC
         LIMIT 6'
    );
    $recentApplications = $recentStatement->fetchAll();
} catch (PDOException $exception) {
    error_log('IAIMS admin dashboard error: ' . $exception->getMessage());
    $dashboardError = true;
}

$totalApplications = array_sum($applicationCounts);
$awaitingReview = $applicationCounts['submitted'] + $applicationCounts['under_review'];
$metrics = [
    ['Students', $studentCount, 'bi-mortarboard'],
    ['All accounts', $userCount, 'bi-people'],
    ['Applications', $totalApplications, 'bi-files'],
    ['Awaiting review', $awaitingReview, 'bi-hourglass-split'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="IAIMS administration dashboard and application overview.">
    <title>Admin dashboard | <?= escapeHtml(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= escapeHtml(BASE_URL) ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-dashboard">
    <header class="admin-topbar">
        <a class="admin-brand" href="<?= escapeHtml(BASE_URL) ?>/?page=dashboard" aria-label="IAIMS Admin dashboard">
            <span class="admin-brand-mark"><i class="bi bi-building-gear" aria-hidden="true"></i></span>
            <span>IAIMS <small>Administration</small></span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="admin-user d-none d-sm-inline"><?= escapeHtml($_SESSION['user_email'] ?? 'Administrator') ?></span>
            <form method="post" action="<?= escapeHtml(BASE_URL) ?>/?page=logout" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                <button class="btn btn-sm btn-outline-light btn-admin-logout" type="submit">
                    <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Sign out
                </button>
            </form>
        </div>
    </header>

    <main class="admin-content">
        <?= renderFlash() ?>

        <section class="admin-heading" aria-labelledby="dashboard-title">
            <div>
                <p class="text-uppercase fw-semibold small text-primary mb-2">Administration</p>
                <h1 id="dashboard-title" class="h2 fw-bold mb-2">Dashboard</h1>
                <p class="text-secondary mb-0">A current overview of student accounts and attachment applications.</p>
            </div>
            <a class="btn btn-outline-primary" href="#recent-applications">
                <i class="bi bi-list-ul me-1" aria-hidden="true"></i>Recent applications
            </a>
        </section>

        <?php if ($dashboardError): ?>
            <div class="alert alert-warning" role="alert">
                Some dashboard information could not be loaded. Please check the database setup and refresh this page.
            </div>
        <?php endif; ?>

        <section class="row g-3 g-xl-4 mb-4" aria-label="System summary">
            <?php foreach ($metrics as [$label, $value, $icon]): ?>
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="admin-metric">
                        <span class="admin-metric-icon"><i class="bi <?= escapeHtml($icon) ?>" aria-hidden="true"></i></span>
                        <div>
                            <p class="admin-metric-label"><?= escapeHtml($label) ?></p>
                            <p class="admin-metric-value"><?= number_format((int) $value) ?></p>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="row g-4">
            <div class="col-lg-8">
                <section class="admin-card" id="recent-applications" aria-labelledby="recent-heading">
                    <div class="admin-card-header">
                        <div>
                            <h2 id="recent-heading">Recent applications</h2>
                            <p class="small text-secondary mb-0 mt-1">Latest student submissions</p>
                        </div>
                        <span class="badge text-bg-light"><?= number_format($totalApplications) ?> total</span>
                    </div>
                    <?php if (!$recentApplications): ?>
                        <div class="admin-empty">
                            <i class="bi bi-inbox" aria-hidden="true"></i>
                            No applications have been submitted yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" class="ps-3">Student</th>
                                        <th scope="col">Department</th>
                                        <th scope="col">Submitted</th>
                                        <th scope="col" class="pe-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentApplications as $application): ?>
                                        <tr>
                                            <td class="ps-3 text-break"><?= escapeHtml($application['email']) ?></td>
                                            <td>
                                                <div class="fw-semibold"><?= escapeHtml($application['state_department']) ?></div>
                                                <div class="small text-secondary"><?= escapeHtml($application['ministry']) ?></div>
                                            </td>
                                            <td class="text-nowrap"><?= escapeHtml(date('j M Y', strtotime($application['submitted_at']))) ?></td>
                                            <td class="pe-3"><span class="badge text-bg-<?= $application['status'] === 'accepted' ? 'success' : ($application['status'] === 'rejected' ? 'danger' : ($application['status'] === 'under_review' ? 'warning' : 'secondary')) ?>"><?= escapeHtml(ucwords(str_replace('_', ' ', $application['status']))) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <div class="col-lg-4">
                <section class="admin-card h-100" aria-labelledby="status-heading">
                    <div class="admin-card-header">
                        <div>
                            <h2 id="status-heading">Application status</h2>
                            <p class="small text-secondary mb-0 mt-1">Breakdown across all submissions</p>
                        </div>
                    </div>
                    <div class="admin-card-body py-2">
                        <?php foreach ([
                            'submitted' => 'Submitted',
                            'under_review' => 'Under review',
                            'accepted' => 'Accepted',
                            'rejected' => 'Rejected',
                            'withdrawn' => 'Withdrawn',
                        ] as $status => $label): ?>
                            <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                                <span><?= escapeHtml($label) ?></span>
                                <span class="fw-semibold"><?= number_format($applicationCounts[$status]) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </div>

        <p class="text-center text-secondary small mt-4 mb-0">&copy; <?= date('Y') ?> <?= escapeHtml(APP_NAME) ?></p>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= escapeHtml(BASE_URL) ?>/assets/js/alerts.js"></script>
</body>
</html>
