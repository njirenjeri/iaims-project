<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/review.php';

$department = requireHrAccess($pdo);
$actor = hrActor($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('danger', 'Your session has expired. Please refresh the page and try again.');
        redirect('hr-queue');
    }

    $result = startNextReview($pdo, $actor);

    if ($result['success']) {
        setFlash('success', $result['message']);
        redirect('hr-application&id=' . $result['application_id']);
    }

    setFlash(!empty($result['empty']) ? 'info' : 'danger', $result['message']);
    redirect('hr-queue');
}

$statuses = ['submitted' => 'Waiting', 'under_review' => 'Under review', 'waitlisted' => 'Waitlist', 'accepted' => 'Accepted', 'rejected' => 'Rejected'];
$status = is_string($_GET['status'] ?? null) && array_key_exists($_GET['status'], $statuses) ? $_GET['status'] : '';
$status = isset($_GET['status']) ? $status : 'submitted'; // opening the queue shows who is waiting
$perPage = 25;
$page = max(1, (int) ($_GET['p'] ?? 1));

$counts = departmentQueueCounts($pdo, $department['department_id']);
$result = listDepartmentApplications($pdo, $department['department_id'], $status, $page, $perPage);
$settings = getDepartmentSettings($pdo, $department['department_id']);
$open = countOpenReviews($pdo, $actor['user_id'], $department['department_id']);

$chips = [['submitted', 'Waiting', $counts['submitted']], ['under_review', 'Under review', $counts['under_review']],
    ['waitlisted', 'Waitlist', $counts['waitlisted']], ['accepted', 'Accepted', $counts['accepted']],
    ['rejected', 'Rejected', $counts['rejected']], ['', 'All', array_sum([$counts['submitted'], $counts['under_review'], $counts['waitlisted'], $counts['accepted'], $counts['rejected'], $counts['withdrawn']])]];

staffPageStart('Applications', 'applications');
?>
<?= renderFlash() ?>

<div class="profile-heading mb-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
        <p class="text-uppercase text-primary fw-semibold small mb-2"><?= escapeHtml($department['department_name']) ?></p>
        <h1 class="h2 fw-bold mb-2">Applications</h1>
        <p class="text-secondary mb-0">Oldest first. Take the next one to review it; you hold <?= $open ?> of <?= (int) $settings['max_open_reviews'] ?> open reviews.</p>
    </div>
    <form method="post" action="<?= escapeHtml(urlWith('hr-queue')) ?>">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
        <button type="submit" class="btn btn-profile-save" <?= $counts['submitted'] === 0 ? 'disabled' : '' ?>>
            <i class="bi bi-arrow-right-circle me-2" aria-hidden="true"></i>Review next
        </button>
    </form>
</div>

<div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Filter by status">
    <?php foreach ($chips as [$value, $label, $count]): ?>
        <a class="btn btn-sm <?= $status === $value ? 'btn-primary' : 'btn-outline-secondary' ?>"
           href="<?= escapeHtml($value === '' ? urlWith('hr-queue', ['status' => 'all']) : urlWith('hr-queue', ['status' => $value])) ?>">
            <?= escapeHtml($label) ?> <span class="opacity-75"><?= (int) $count ?></span>
        </a>
    <?php endforeach; ?>
</div>

<section class="card profile-card" aria-label="Applications">
    <?php if (!$result['rows']): ?>
        <div class="text-center p-4 p-md-5">
            <h2 class="h6 fw-semibold">Nothing here</h2>
            <p class="text-secondary small mb-0">
                <?= $status === 'submitted' ? 'No applications are waiting for review.' : 'No applications match this filter.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= $status === 'waitlisted' ? 'Position' : 'No.' ?></th>
                        <th>Student</th><th>Course</th><th>Submitted</th><th>Status</th><th>With</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $index => $row): ?>
                    <?php $waitingDays = (int) floor((time() - strtotime($row['submitted_at'])) / 86400); ?>
                    <tr>
                        <td class="text-secondary"><?= $status === 'waitlisted' ? (($page - 1) * $perPage + $index + 1) : (int) $row['queue_no'] ?></td>
                        <td>
                            <a class="fw-semibold text-decoration-none" href="<?= escapeHtml(urlWith('hr-application', ['id' => $row['application_id']])) ?>">
                                <?= escapeHtml($row['student_name']) ?>
                            </a>
                            <div class="small text-secondary"><?= escapeHtml($row['student_email']) ?></div>
                        </td>
                        <td class="small"><?= escapeHtml($row['course_name']) ?><div class="text-secondary"><?= escapeHtml($row['institution']) ?></div></td>
                        <td class="small text-nowrap">
                            <?= escapeHtml(formatWhen($row['submitted_at'], 'j M Y')) ?>
                            <div class="text-secondary"><?= $waitingDays === 0 ? 'today' : $waitingDays . ($waitingDays === 1 ? ' day ago' : ' days ago') ?></div>
                        </td>
                        <td><?= statusBadge($row['status']) ?></td>
                        <td class="small"><?= $row['status'] === 'under_review' ? escapeHtml($row['reviewer_first'] ?: 'An officer') : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-top">
            <span class="small text-secondary">
                Showing <?= ($page - 1) * $perPage + 1 ?> to <?= min($page * $perPage, $result['total']) ?> of <?= (int) $result['total'] ?>
            </span>
            <?= renderPagination('hr-queue', ['status' => $status === '' ? 'all' : $status], $page, $result['total'], $perPage) ?>
        </div>
    <?php endif; ?>
</section>
<?php staffPageEnd(); ?>
