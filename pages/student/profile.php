<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/student.php';

requireRole('STUDENT');

$lookups = require __DIR__ . '/../../config/lookups.php';
$counties = $lookups['counties'];
$userId = $_SESSION['user_id'];

$profileData = getStudentProfile($pdo, $userId);
$saved = $profileData['saved'];
$wasComplete = isProfileComplete($saved);
$profile = array_merge(profileDefaults(), $profileData['values']);

$errors = [];
$errorSection = ''; // which section the errors belong to ('' = page-level)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = is_string($_POST['section'] ?? null) ? $_POST['section'] : '';

    if (!isset(PROFILE_SECTIONS[$section])) {
        $errors[] = 'Invalid form submission.';
    } elseif (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $errorSection = $section;
        [$clean, $fieldErrors] = validateProfileSection($section, $_POST, $lookups);
        $profile = array_merge($profile, $clean); // keep what the student typed

        if ($fieldErrors) {
            $errors = array_values($fieldErrors);
        } else {
            $result = saveProfileSection($pdo, $userId, $section, $clean);

            if ($result['success']) {
                $saved[$section] = true;

                if (!$wasComplete && isProfileComplete($saved)) {
                    setFlash('success', 'Your profile is complete. You can now submit an application.');
                    redirect('applications');
                }

                setFlash('success', PROFILE_SECTIONS[$section]['label'] . ' saved.');
                redirect('profile', $section);
            }

            $errors[] = $result['message'];
        }
    }
}

$completion = profileCompletion($saved);
$savedCount = count(array_filter($saved));
$currentPage = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Enter and update your student personal details.">
    <title>My Profile | <?= escapeHtml(APP_NAME) ?></title>
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
                        <p class="text-uppercase text-primary fw-semibold small mb-2">Student information</p>
                        <h1 class="h2 fw-bold mb-2 text-capitalize">Personal profile</h1>
                        <!-- <p class="text-secondary mb-0">Provide accurate personal, academic and residential details for your attachment record.</p> -->
                    </div>
                </div>

                <?= renderFlash() ?>
                <?php if ($errors && $errorSection === ''): ?>
                    <?= renderAlert('danger', implode(' ', $errors), 0) ?>
                <?php endif; ?>

                <div class="mb-4">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold">Profile completion</span>
                        <span class="text-secondary"><?= $savedCount ?> of <?= count(PROFILE_SECTIONS) ?> sections saved</span>
                    </div>
                    <div class="progress" role="progressbar" aria-label="Profile completion"
                         aria-valuenow="<?= $completion ?>" aria-valuemin="0" aria-valuemax="100" style="height: 8px">
                        <div class="progress-bar <?= $completion === 100 ? 'bg-success' : '' ?>" style="width: <?= $completion ?>%"></div>
                    </div>
                    <p class="form-text mb-0">Each section is saved on its own. Save a section before moving to the next.</p>
                </div>

                <!-- Personal details -->
                <form method="post" action="<?= escapeHtml(BASE_URL) ?>/?page=profile#personal" id="personal" class="profile-section-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                    <input type="hidden" name="section" value="personal">

                    <section class="card profile-card mb-4" aria-labelledby="personal-heading">
                        <div class="card-header profile-card-header">
                            <span class="section-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
                            <div><h2 class="h5 mb-1" id="personal-heading">Personal details</h2><p class="small text-secondary mb-0">Your name and contact information</p></div>
                            <?= sectionBadge($saved['personal']) ?>
                        </div>
                        <div class="card-body p-3 p-md-4">
                            <?= $errors && $errorSection === 'personal' ? renderAlert('danger', implode(' ', $errors), 0) : '' ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">First name <span class="text-danger">*</span></label>
                                    <input class="form-control" id="first_name" name="first_name" value="<?= escapeHtml($profile['first_name']) ?>" autocomplete="given-name" maxlength="100" required>
                                    <div class="invalid-feedback">Enter your first name.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="other_names" class="form-label">Other name(s)</label>
                                    <input class="form-control" id="other_names" name="other_names" value="<?= escapeHtml($profile['other_names']) ?>" autocomplete="additional-name" maxlength="150">
                                </div>
                                <div class="col-md-6">
                                    <label for="identity_type" class="form-label">Identity document <span class="text-danger">*</span></label>
                                    <select class="form-select" id="identity_type" name="identity_type" required>
                                        <option value="national_id" <?= $profile['identity_type'] === 'national_id' ? 'selected' : '' ?>>National ID</option>
                                        <option value="passport" <?= $profile['identity_type'] === 'passport' ? 'selected' : '' ?>>Passport</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="identity_number" class="form-label" id="identityNumberLabel">National ID number <span class="text-danger">*</span></label>
                                    <input class="form-control" id="identity_number" name="identity_number" value="<?= escapeHtml($profile['identity_number']) ?>" autocomplete="off" inputmode="numeric" pattern="[0-9]{7,8}" maxlength="50" placeholder="e.g. 12345678" required>
                                    <div class="invalid-feedback">Enter a valid identity number.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone_number" class="form-label">Phone number <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?= escapeHtml($profile['phone_number']) ?>" placeholder="e.g. +254 712 345 678" autocomplete="tel" maxlength="30" pattern="(?:[+0-9. ]|\(|\)|-){7,30}" required>
                                    <div class="form-text">Include your country code where applicable.</div>
                                    <div class="invalid-feedback">Enter a valid phone number.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="alternate_phone_number" class="form-label">Alternate phone number</label>
                                    <input type="tel" class="form-control" id="alternate_phone_number" name="alternate_phone_number" value="<?= escapeHtml($profile['alternate_phone_number']) ?>" placeholder="Optional" autocomplete="tel" maxlength="30" pattern="(?:[+0-9. ]|\(|\)|-){7,30}">
                                    <div class="form-text">Optional. A second number HR can reach you on.</div>
                                    <div class="invalid-feedback">Enter a valid number that differs from your main number, or leave it blank.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="nationality" class="form-label">Nationality <span class="text-danger">*</span></label>
                                    <input class="form-control" id="nationality" name="nationality" value="<?= escapeHtml($profile['nationality']) ?>" autocomplete="country-name" maxlength="100" required>
                                    <div class="invalid-feedback">Enter your nationality.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select gender</option>
                                        <?php foreach (['Male', 'Female'] as $gender): ?>
                                            <option value="<?= escapeHtml($gender) ?>" <?= $profile['gender'] === $gender ? 'selected' : '' ?>><?= escapeHtml($gender) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Select a gender option.</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent text-end py-3">
                            <button type="submit" class="btn btn-profile-save px-4"><i class="bi bi-check2-circle me-2" aria-hidden="true"></i>Save personal details</button>
                        </div>
                    </section>
                </form>

                <!-- Academic information -->
                <form method="post" action="<?= escapeHtml(BASE_URL) ?>/?page=profile#academic" id="academic" class="profile-section-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                    <input type="hidden" name="section" value="academic">

                    <section class="card profile-card mb-4" aria-labelledby="academic-heading">
                        <div class="card-header profile-card-header">
                            <span class="section-icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
                            <div><h2 class="h5 mb-1" id="academic-heading">Academic information</h2><p class="small text-secondary mb-0">Your current education and course details</p></div>
                            <?= sectionBadge($saved['academic']) ?>
                        </div>
                        <div class="card-body p-3 p-md-4">
                            <?= $errors && $errorSection === 'academic' ? renderAlert('danger', implode(' ', $errors), 0) : '' ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="institution" class="form-label">Student institution <span class="text-danger">*</span></label>
                                    <div class="institution-lookup position-relative">
                                        <input class="form-control" id="institution" name="institution" value="<?= escapeHtml($profile['institution']) ?>" maxlength="180" autocomplete="off" aria-autocomplete="list" aria-controls="institutionSuggestions" aria-expanded="false" required>
                                        <div id="institutionSuggestions" class="institution-suggestions list-group shadow-sm" role="listbox" hidden></div>
                                    </div>
                                    <div class="form-text" id="institutionLookupStatus" aria-live="polite">Start typing your institution's name and choose a match.</div>
                                    <div class="invalid-feedback">Search for your institution and choose a result.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="course_name" class="form-label">Course name <span class="text-danger">*</span></label>
                                    <input class="form-control" id="course_name" name="course_name" value="<?= escapeHtml($profile['course_name']) ?>" maxlength="180" required>
                                    <div class="invalid-feedback">Enter your course name.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="academic_qualification" class="form-label">Academic qualification <span class="text-danger">*</span></label>
                                    <select class="form-select" id="academic_qualification" name="academic_qualification" required>
                                        <option value="">Select qualification</option>
                                        <?php foreach ($lookups['qualifications'] as $qualification): ?>
                                            <option value="<?= escapeHtml($qualification) ?>" <?= $profile['academic_qualification'] === $qualification ? 'selected' : '' ?>><?= escapeHtml($qualification) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Select Degree, Diploma, or Certificate.</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent text-end py-3">
                            <button type="submit" class="btn btn-profile-save px-4"><i class="bi bi-check2-circle me-2" aria-hidden="true"></i>Save academic information</button>
                        </div>
                    </section>
                </form>

                <!-- Residential details -->
                <form method="post" action="<?= escapeHtml(BASE_URL) ?>/?page=profile#residence" id="residence" class="profile-section-form"
                      data-locations-url="<?= escapeHtml(BASE_URL) ?>/assets/data/kenya-locations.json" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                    <input type="hidden" name="section" value="residence">

                    <section class="card profile-card mb-4" aria-labelledby="residential-heading">
                        <div class="card-header profile-card-header">
                            <span class="section-icon section-icon-gold"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                            <div><h2 class="h5 mb-1" id="residential-heading">Residential details</h2><p class="small text-secondary mb-0">Where you currently reside</p></div>
                            <?= sectionBadge($saved['residence']) ?>
                        </div>
                        <div class="card-body p-3 p-md-4">
                            <?= $errors && $errorSection === 'residence' ? renderAlert('danger', implode(' ', $errors), 0) : '' ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="county" class="form-label">County <span class="text-danger">*</span></label>
                                    <select class="form-select" id="county" name="county" autocomplete="address-level1" required>
                                        <option value="">Select county</option>
                                        <?php foreach ($counties as $county): ?>
                                            <option value="<?= escapeHtml($county) ?>" <?= $profile['county'] === $county ? 'selected' : '' ?>><?= escapeHtml($county) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Select your county.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="constituency" class="form-label">Constituency <span class="text-danger">*</span></label>
                                    <select class="form-select" id="constituency" name="constituency" data-selected="<?= escapeHtml($profile['constituency']) ?>" autocomplete="address-level2" required disabled>
                                        <option value="">Select a county first</option>
                                    </select>
                                    <div class="invalid-feedback">Select your constituency.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="ward" class="form-label">Ward <span class="text-danger">*</span></label>
                                    <select class="form-select" id="ward" name="ward" data-selected="<?= escapeHtml($profile['ward']) ?>" required disabled>
                                        <option value="">Select a constituency first</option>
                                    </select>
                                    <div class="invalid-feedback">Select your ward.</div>
                                </div>
                                <div class="col-12"><div class="form-text" id="locationLookupStatus" role="status" aria-live="polite">Loading Kenya's constituency and ward list…</div></div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent text-end py-3">
                            <button type="submit" class="btn btn-profile-save px-4"><i class="bi bi-check2-circle me-2" aria-hidden="true"></i>Save residential details</button>
                        </div>
                    </section>
                </form>

                <p class="text-center text-secondary small mt-4 mb-0">&copy; <?= date('Y') ?> <?= escapeHtml(APP_NAME) ?></p>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= escapeHtml(BASE_URL) ?>/assets/js/alerts.js"></script>
    <script src="<?= escapeHtml(BASE_URL) ?>/assets/js/profile.js"></script>
</body>
</html>
