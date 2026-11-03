<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/capacity.php';

// Capacity and settings for the signed-in user's department. Officers can read; HR admins edit.
$department = requireHrAccess($pdo);
$isAdmin = userHasRole('HR_ADMIN');
$actor = hrActor($pdo);

$errors = [];
$old = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $result = saveDepartmentSettings($pdo, $actor, $_POST);

        if ($result['success']) {
            setFlash('success', $result['message']);
            redirect('hr-capacity');
        }

        $errors = $result['errors'];
    }
}

$capacity = departmentCapacity($pdo, $department['department_id']);
$queue = departmentQueueCounts($pdo, $department['department_id']);
$settings = $capacity['settings'];

// Show what was just submitted if it was refused, otherwise what is saved.
$value = static fn (string $key) => $old !== null && isset($old[$key]) && is_string($old[$key]) ? $old[$key] : (string) $settings[$key];
$accepting = $old !== null ? !empty($old['accepting_applications']) : (bool) $settings['accepting_applications'];

staffPageStart('Capacity and settings', 'capacity');
?>
<?= renderFlash() ?>

<div class="profile-heading mb-4">
    <div>
        <p class="text-uppercase text-primary fw-semibold small mb-2"><?= escapeHtml($department['ministry_name']) ?></p>
        <h1 class="h2 fw-bold mb-2"><?= escapeHtml($department['department_name']) ?></h1>
        <p class="text-secondary mb-0">How many students the department can host at once, and how the queue behaves.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([['Capacity', $capacity['capacity']], ['In use today', $capacity['in_use']], ['Free', $capacity['free']], ['Waitlisted', $queue['waitlisted']]] as [$label, $number]): ?>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-4 fw-semibold lh-1"><?= (int) $number ?></div>
                    <div class="small text-secondary"><?= escapeHtml($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?= $errors ? renderAlert('danger', implode(' ', $errors), 0) : '' ?>

<form method="post" action="<?= escapeHtml(urlWith('hr-capacity')) ?>" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">

    <section class="card profile-card mb-4" aria-labelledby="settings-heading">
        <div class="card-header profile-card-header">
            <span class="section-icon"><i class="bi bi-sliders" aria-hidden="true"></i></span>
            <div>
                <h2 class="h5 mb-1" id="settings-heading">Capacity and settings</h2>
                <p class="small text-secondary mb-0"><?= $isAdmin ? 'Changes are recorded in the audit log.' : 'Your HR admin changes these.' ?></p>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <fieldset <?= $isAdmin ? '' : 'disabled' ?>>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="capacity" class="form-label">Capacity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="capacity" name="capacity" value="<?= escapeHtml($value('capacity')) ?>" min="0" max="<?= CAPACITY_MAX ?>" required>
                        <div class="form-text">Students the department can host at the same time. A place opens when a placement ends or a student leaves.</div>
                        <div class="invalid-feedback">Enter a number from 0 to <?= CAPACITY_MAX ?>.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="max_open_reviews" class="form-label">Open reviews per officer <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="max_open_reviews" name="max_open_reviews" value="<?= escapeHtml($value('max_open_reviews')) ?>" min="1" max="20" required>
                        <div class="form-text">How many applications one officer can hold at the same time, so nobody hoards the queue.</div>
                        <div class="invalid-feedback">Enter a number from 1 to 20.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="offer_response_days" class="form-label">Days to answer an offer <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="offer_response_days" name="offer_response_days" value="<?= escapeHtml($value('offer_response_days')) ?>" min="1" max="14" required>
                        <div class="form-text">If a student does not answer in time, the place passes to the next student and their application closes.</div>
                        <div class="invalid-feedback">Enter a number from 1 to 14.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="interest_check_days" class="form-label">Waitlist check-in every (days) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="interest_check_days" name="interest_check_days" value="<?= escapeHtml($value('interest_check_days')) ?>" min="7" max="180" required>
                        <div class="form-text">Waitlisted students confirm they still want a place this often, or their application closes.</div>
                        <div class="invalid-feedback">Enter a number from 7 to 180.</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="accepting_applications" name="accepting_applications" value="1" <?= $accepting ? 'checked' : '' ?>>
                            <label class="form-check-label" for="accepting_applications">Accepting applications</label>
                        </div>
                        <div class="form-text">Turn this off to pause new applications. Students already in the queue are not affected.</div>
                    </div>
                </div>
            </fieldset>
        </div>
        <?php if ($isAdmin): ?>
            <div class="card-footer bg-transparent text-end py-3">
                <button type="submit" class="btn btn-profile-save px-4"><i class="bi bi-check2-circle me-2" aria-hidden="true"></i>Save</button>
            </div>
        <?php endif; ?>
    </section>
</form>

<p class="small text-secondary">
    Attachments run <?= attachmentMinWeeks() ?> to <?= attachmentMaxWeeks() ?> weeks. Lowering the capacity never removes anyone: new places open only after enough placements have ended.
</p>
<?php staffPageEnd(['admin-users.js']); ?>
