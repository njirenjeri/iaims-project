<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/review.php';

$department = requireHrAccess($pdo);
$actor = hrActor($pdo);
$isAdmin = userHasRole('HR_ADMIN');

$applicationId = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
$application = getApplicationForHr($pdo, $applicationId, $department['department_id']);

// Unknown ids and other departments' applications look the same.
if ($application === null) {
    http_response_code(404);
    exit('Application not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $text = static fn (string $key): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : '';

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $result = ['success' => false, 'message' => 'Your session has expired. Please refresh the page and try again.'];
    } else {
        switch ($action) {
            case 'accept':
            case 'reject':
                $result = decideApplication($pdo, $actor, $applicationId, $action, $text('reason_code'), $text('note'));
                break;
            case 'take':
                $result = takeApplication($pdo, $actor, $applicationId, $text('reason'));
                break;
            case 'release':
                $result = releaseReview($pdo, $actor, $applicationId);
                break;
            case 'note':
                $result = addApplicationNote($pdo, $actor, $applicationId, $text('note'));
                break;
            default:
                $result = ['success' => false, 'message' => 'Invalid request.'];
        }
    }

    setFlash($result['success'] ? 'success' : 'danger', $result['message']);
    redirect('hr-application&id=' . $applicationId);
}

$documents = applicationDocuments($pdo, $applicationId);
$events = getApplicationEvents($pdo, $applicationId);
$documentLabels = lookupList('documents');
$reasons = rejectionReasons();
$status = $application['status'];
$mine = $status === 'under_review' && $application['reviewer_user_id'] === $actor['user_id'];
$waitingDays = (int) floor((time() - strtotime($application['submitted_at'])) / 86400);

$eventLabels = [
    'submitted' => 'Application submitted', 'review_started' => 'Review started', 'review_released' => 'Returned to the queue',
    'review_reassigned' => 'Review taken over', 'decision' => 'Decision', 'waitlisted' => 'Waitlisted',
    'place_reserved' => 'Place reserved', 'note' => 'Note',
];

$details = [
    'Email' => $application['student_email'],
    'Phone' => $application['phone_number'] . ($application['alternate_phone_number'] !== '' ? ' / ' . $application['alternate_phone_number'] : ''),
    ($application['identity_type'] === 'passport' ? 'Passport' : 'National ID') => $application['identity_number'],
    'Nationality, gender' => trim($application['nationality'] . ', ' . $application['gender'], ', '),
    'Lives in' => trim($application['ward'] . ', ' . $application['constituency'] . ', ' . $application['county'] . ' County', ', '),
    'Institution' => $application['institution'],
    'Course' => $application['course_name'] . ' (' . $application['academic_qualification'] . ')',
];

$request = [
    'Queue number' => (string) (int) $application['queue_no'],
    'Submitted' => formatWhen($application['submitted_at']) . ($waitingDays > 0 ? " ({$waitingDays} days ago)" : ' (today)'),
    'Length requested' => (int) $application['requested_weeks'] . ' weeks',
    'Earliest start' => formatWhen($application['earliest_start'], 'j M Y'),
];

staffPageStart($application['student_name'], 'applications');
?>
<a class="small link-gold text-decoration-none" href="<?= escapeHtml(urlWith('hr-queue')) ?>">
    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to applications
</a>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 my-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?= escapeHtml($application['student_name']) ?></h1>
        <div class="text-secondary small"><?= escapeHtml($department['department_name']) ?></div>
    </div>
    <div><?= statusBadge($status) ?></div>
</div>

<?= renderFlash() ?>

<div class="row g-4">
    <div class="col-lg-5">
        <section class="card profile-card mb-4" aria-labelledby="applicant-heading">
            <div class="card-header profile-card-header">
                <span class="section-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
                <div><h2 class="h5 mb-1" id="applicant-heading">Applicant</h2></div>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($details as $label => $value): ?>
                    <li class="list-group-item d-flex justify-content-between gap-3 small">
                        <span class="text-secondary"><?= escapeHtml($label) ?></span>
                        <span class="text-end text-break"><?= escapeHtml($value) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card profile-card mb-4" aria-labelledby="request-heading">
            <div class="card-header profile-card-header">
                <span class="section-icon section-icon-gold"><i class="bi bi-calendar-week" aria-hidden="true"></i></span>
                <div><h2 class="h5 mb-1" id="request-heading">Application</h2></div>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($request as $label => $value): ?>
                    <li class="list-group-item d-flex justify-content-between gap-3 small">
                        <span class="text-secondary"><?= escapeHtml($label) ?></span>
                        <span class="text-end"><?= escapeHtml($value) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card profile-card" aria-labelledby="documents-heading">
            <div class="card-header profile-card-header">
                <span class="section-icon"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></span>
                <div><h2 class="h5 mb-1" id="documents-heading">Documents</h2><p class="small text-secondary mb-0">Opening one is recorded in the audit log</p></div>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($documents as $document): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <div><?= escapeHtml($documentLabels[$document['document_type']] ?? $document['document_type']) ?></div>
                            <div class="small text-secondary text-break"><?= escapeHtml($document['original_name']) ?> · <?= escapeHtml(number_format($document['size_bytes'] / 1024 / 1024, 1)) ?> MB</div>
                        </div>
                        <a class="btn btn-sm btn-outline-secondary flex-shrink-0" target="_blank" rel="noopener"
                           href="<?= escapeHtml(urlWith('hr-document', ['id' => $document['document_id']])) ?>">View</a>
                    </li>
                <?php endforeach; ?>
                <?php if (!$documents): ?><li class="list-group-item text-secondary small">No documents on file.</li><?php endif; ?>
            </ul>
        </section>
    </div>

    <div class="col-lg-7">
        <section class="card profile-card mb-4" aria-labelledby="decision-heading">
            <div class="card-header profile-card-header">
                <span class="section-icon section-icon-gold"><i class="bi bi-check2-square" aria-hidden="true"></i></span>
                <div><h2 class="h5 mb-1" id="decision-heading">Decision</h2></div>
            </div>
            <div class="card-body">
                <?php if ($status === 'submitted'): ?>
                    <p class="mb-3">
                        Waiting for review.
                        <?= $application['ahead_in_review'] === 0 ? 'This is the oldest waiting application.' : (int) $application['ahead_in_review'] . ' older application(s) are waiting ahead of it.' ?>
                    </p>
                    <?php if ($isAdmin): ?>
                        <form method="post" action="<?= escapeHtml(urlWith('hr-application', ['id' => $applicationId])) ?>" class="d-grid gap-2">
                            <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                            <input type="hidden" name="action" value="take">
                            <?php if ($application['ahead_in_review'] > 0): ?>
                                <label for="takeReason" class="form-label small mb-0">Reason for reviewing it before the older ones</label>
                                <input type="text" class="form-control" id="takeReason" name="reason" maxlength="200" required>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-outline-secondary">Review this application</button>
                        </form>
                    <?php else: ?>
                        <p class="small text-secondary mb-0">Use Review next on the Applications page. Applications are taken oldest first.</p>
                    <?php endif; ?>

                <?php elseif ($status === 'under_review' && $mine): ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <form method="post" action="<?= escapeHtml(urlWith('hr-application', ['id' => $applicationId])) ?>" data-confirm="Accept this application? It joins the waitlist and gets the next free place, oldest first.">
                                <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                                <input type="hidden" name="action" value="accept">
                                <label for="acceptNote" class="form-label small">Note to the student (optional)</label>
                                <textarea class="form-control mb-2" id="acceptNote" name="note" rows="2" maxlength="500"></textarea>
                                <button type="submit" class="btn btn-success w-100"><i class="bi bi-check2-circle me-2" aria-hidden="true"></i>Accept</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="post" action="<?= escapeHtml(urlWith('hr-application', ['id' => $applicationId])) ?>" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                                <input type="hidden" name="action" value="reject">
                                <label for="reasonCode" class="form-label small">Reason for rejecting <span class="text-danger">*</span></label>
                                <select class="form-select mb-2" id="reasonCode" name="reason_code" required>
                                    <option value="">Choose a reason</option>
                                    <?php foreach ($reasons as $code => $label): ?>
                                        <option value="<?= escapeHtml($code) ?>"><?= escapeHtml($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Choose a reason.</div>
                                <label for="rejectNote" class="form-label small">Note to the student</label>
                                <textarea class="form-control mb-2" id="rejectNote" name="note" rows="2" maxlength="500"></textarea>
                                <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-x-circle me-2" aria-hidden="true"></i>Reject</button>
                            </form>
                        </div>
                    </div>
                    <form method="post" action="<?= escapeHtml(urlWith('hr-application', ['id' => $applicationId])) ?>" class="mt-3" data-confirm="Return this application to the queue?">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                        <input type="hidden" name="action" value="release">
                        <button type="submit" class="btn btn-link link-gold p-0 text-decoration-none">Return it to the queue</button>
                    </form>

                <?php elseif ($status === 'under_review'): ?>
                    <p class="mb-3">Under review by <strong><?= escapeHtml($application['reviewer_name'] ?: 'another officer') ?></strong>.</p>
                    <?php if ($isAdmin): ?>
                        <form method="post" action="<?= escapeHtml(urlWith('hr-application', ['id' => $applicationId])) ?>" class="d-grid gap-2">
                            <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                            <input type="hidden" name="action" value="take">
                            <label for="takeoverReason" class="form-label small mb-0">Reason for taking it over</label>
                            <input type="text" class="form-control" id="takeoverReason" name="reason" maxlength="200" required>
                            <button type="submit" class="btn btn-outline-secondary">Take over this review</button>
                        </form>
                    <?php endif; ?>

                <?php elseif ($status === 'waitlisted'): ?>
                    <p class="mb-0">Approved. On the waitlist at position <strong><?= (int) $application['ahead_on_waitlist'] + 1 ?></strong>: the next free place goes to the oldest waitlisted student.</p>

                <?php elseif ($status === 'accepted'): ?>
                    <p class="mb-0">Approved, and a place is reserved. Confirming the supervisor and dates, and sending the offer, comes with the next step.</p>

                <?php else: ?>
                    <p class="mb-0">This application is <?= escapeHtml(str_replace('_', ' ', $status)) ?>. No further decision is needed.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="card profile-card" aria-labelledby="history-heading">
            <div class="card-header profile-card-header">
                <span class="section-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                <div><h2 class="h5 mb-1" id="history-heading">History</h2></div>
            </div>
            <div class="card-body">
                <form method="post" action="<?= escapeHtml(urlWith('hr-application', ['id' => $applicationId])) ?>" class="mb-4">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                    <input type="hidden" name="action" value="note">
                    <label for="internalNote" class="form-label small">Add a note (visible to HR only)</label>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control" id="internalNote" name="note" maxlength="1000" required>
                        <button type="submit" class="btn btn-outline-secondary flex-shrink-0">Add note</button>
                    </div>
                </form>

                <?php foreach ($events as $event): ?>
                    <div class="d-flex gap-3 mb-3">
                        <span class="rounded-circle bg-secondary-subtle flex-shrink-0 mt-1" style="width: 10px; height: 10px" aria-hidden="true"></span>
                        <div>
                            <div>
                                <?= escapeHtml($eventLabels[$event['event_type']] ?? ucfirst(str_replace('_', ' ', $event['event_type']))) ?>
                                <?php if ($event['reason_code'] !== ''): ?>
                                    <span class="text-secondary">: <?= escapeHtml($reasons[$event['reason_code']] ?? $event['reason_code']) ?></span>
                                <?php endif; ?>
                                <?php if ($event['visibility'] === 'student'): ?><span class="badge text-bg-light border ms-1">Shown to student</span><?php endif; ?>
                            </div>
                            <?php if ($event['note'] !== ''): ?><div class="small"><?= nl2br(escapeHtml($event['note'])) ?></div><?php endif; ?>
                            <div class="small text-secondary"><?= escapeHtml($event['actor_name'] ?: 'System') ?> · <?= escapeHtml(formatWhen($event['created_at'], 'j M Y, H:i')) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
<?php staffPageEnd(['admin-users.js']); ?>
