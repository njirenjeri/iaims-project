<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/student.php';

requireRole('STUDENT');

$lookups = require __DIR__ . '/../../config/lookups.php';
$ministries = $lookups['ministries'];
$documents = $lookups['documents'];
$userId = $_SESSION['user_id'];

// The profile must be complete before any application.
$profileData = getStudentProfile($pdo, $userId);

if (!isProfileComplete($profileData['saved'])) {
    setFlash('warning', 'Please complete your profile before submitting an application.');
    redirect('profile');
}

$profile = $profileData['values']; // flat: used for the summary card and the application snapshot

$errors = [];
$selectedMinistry = '';
$selectedDepartment = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedMinistry = is_string($_POST['ministry'] ?? null) ? $_POST['ministry'] : '';
    $selectedDepartment = is_string($_POST['state_department'] ?? null) ? $_POST['state_department'] : '';

    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // PHP discards everything when the request exceeds post_max_size.
        $errors[] = 'The files you selected are too large. Each PDF must be 10 MB or less.';
    } elseif (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $result = createApplication($pdo, $userId, $profile, $_POST, $_FILES, $lookups);

        if ($result['success']) {
            setFlash('success', $result['message']);
            redirect('applications');
        }

        $errors = $result['errors'];
    }
}

$applications = listApplications($pdo, $userId);
$currentPage = 'applications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Submit your industrial attachment application and required documents.">
    <title>Applications | <?= escapeHtml(APP_NAME) ?></title>
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
                <div class="profile-heading mb-4">
                    <div>
                        <p class="text-uppercase text-primary fw-semibold small mb-2">Industrial attachment</p>
                        <h1 class="h2 fw-bold mb-2">Application form</h1>
                        <!-- <p class="text-secondary mb-0">Choose your preferred government department and attach all required documents. Each application is separate; your profile is reused.</p> -->
                    </div>
                </div>

                <?= renderFlash() ?>
                <?php if ($errors): ?>
                    <?= renderAlert('danger', implode(' ', $errors), 0) ?>
                <?php endif; ?>

                <form id="applicationForm" method="post" action="<?= escapeHtml(BASE_URL) ?>/?page=applications" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">

                    <section class="card profile-card mb-4" aria-label="Applying as">
                        <div class="card-body p-3 p-md-4 d-flex flex-column flex-sm-row justify-content-between gap-3">
                            <div>
                                <p class="small text-secondary mb-1">Applying as</p>
                                <p class="fw-semibold mb-0"><?= escapeHtml(trim($profile['first_name'] . ' ' . $profile['other_names'])) ?></p>
                                <p class="small text-secondary mb-0">
                                    <?= escapeHtml($profile['course_name']) ?> (<?= escapeHtml($profile['academic_qualification']) ?>),
                                    <?= escapeHtml($profile['institution']) ?>
                                </p>
                            </div>
                            <a href="<?= escapeHtml(BASE_URL) ?>/?page=profile" class="btn btn-outline-secondary btn-sm align-self-sm-center">
                                <i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit profile
                            </a>
                        </div>
                    </section>

                    <section class="card profile-card mb-4" aria-labelledby="placement-heading">
                        <div class="card-header profile-card-header">
                            <span class="section-icon section-icon-gold"><i class="bi bi-building" aria-hidden="true"></i></span>
                            <div><h2 class="h5 mb-1" id="placement-heading">Preferred placement</h2><p class="small text-secondary mb-0">Select the ministry and its state department</p></div>
                        </div>
                        <div class="card-body p-3 p-md-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="ministry" class="form-label">Ministry <span class="text-danger">*</span></label>
                                    <select class="form-select" id="ministry" name="ministry" required>
                                        <option value="">Select ministry</option>
                                        <?php foreach (array_keys($ministries) as $ministry): ?>
                                            <option value="<?= escapeHtml($ministry) ?>" <?= $selectedMinistry === $ministry ? 'selected' : '' ?>><?= escapeHtml($ministry) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Select a ministry.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="state_department" class="form-label">State department <span class="text-danger">*</span></label>
                                    <select class="form-select" id="state_department" name="state_department" data-selected="<?= escapeHtml($selectedDepartment) ?>" required>
                                        <option value="">Select a ministry first</option>
                                    </select>
                                    <div class="invalid-feedback">Select a state department.</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="card profile-card mb-4" aria-labelledby="documents-heading">
                        <div class="card-header profile-card-header">
                            <span class="section-icon"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></span>
                            <div><h2 class="h5 mb-1" id="documents-heading">Required documents</h2><p class="small text-secondary mb-0">Upload a separate PDF for each document (maximum 10 MB per file)</p></div>
                        </div>
                        <div class="card-body p-3 p-md-4">
                            <div class="row g-3">
                                <?php foreach ($documents as $field => $label): ?>
                                    <div class="col-md-6">
                                        <label for="<?= escapeHtml($field) ?>" class="form-label"><?= escapeHtml($label) ?> <span class="text-danger">*</span></label>
                                        <input class="form-control application-file" type="file" id="<?= escapeHtml($field) ?>" name="<?= escapeHtml($field) ?>" accept="application/pdf,.pdf" data-label="<?= escapeHtml($label) ?>" required>
                                        <div class="invalid-feedback">Choose a PDF file no larger than 10 MB.</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="form-text mt-3 mb-0">Browsers clear file choices after an error, so you'll need to select the files again.</p>
                        </div>
                    </section>

                    <div class="profile-form-footer d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                        <p class="small text-secondary mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>All fields and documents are required to submit your application.</p>
                        <button type="submit" class="btn btn-profile-save btn-lg px-4">
                            <i class="bi bi-send me-2" aria-hidden="true"></i>Submit application
                        </button>
                    </div>
                </form>

                <section class="card profile-card mt-4" aria-labelledby="history-heading">
                    <div class="card-header profile-card-header">
                        <span class="section-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                        <div><h2 class="h5 mb-1" id="history-heading">My applications</h2></div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!$applications): ?>
                            <p class="text-secondary p-4 mb-0">You haven't submitted any applications yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead><tr><th>Ministry / Department</th><th>Submitted</th><th>Status</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($applications as $application): ?>
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
                        <?php endif; ?>
                    </div>
                </section>

                <p class="text-center text-secondary small mt-4 mb-0">&copy; <?= date('Y') ?> <?= escapeHtml(APP_NAME) ?></p>
            </main>
        </div>
    </div>

    <script>
        window.applicationDepartments = <?= json_encode($ministries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= escapeHtml(BASE_URL) ?>/assets/js/alerts.js"></script>
    <script src="<?= escapeHtml(BASE_URL) ?>/assets/js/applications.js"></script>
</body>
</html>
