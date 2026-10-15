<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

$counties = [
    'Baringo', 'Bomet', 'Bungoma', 'Busia', 'Elgeyo-Marakwet', 'Embu',
    'Garissa', 'Homa Bay', 'Isiolo', 'Kajiado', 'Kakamega', 'Kericho',
    'Kiambu', 'Kilifi', 'Kirinyaga', 'Kisii', 'Kisumu', 'Kitui', 'Kwale',
    'Laikipia', 'Lamu', 'Machakos', 'Makueni', 'Mandera', 'Marsabit',
    'Meru', 'Migori', 'Mombasa', "Murang'a", 'Nairobi', 'Nakuru', 'Nandi',
    'Narok', 'Nyamira', 'Nyandarua', 'Nyeri', 'Samburu', 'Siaya',
    'Taita-Taveta', 'Tana River', 'Tharaka-Nithi', 'Trans Nzoia',
    'Turkana', 'Uasin Gishu', 'Vihiga', 'Wajir', 'West Pokot',
];

$profile = [
    'first_name' => '',
    'last_name' => '',
    'other_names' => '',
    'email' => '',
    'date_of_birth' => '',
    'identity_type' => 'national_id',
    'identity_number' => '',
    'phone_number' => '',
    'nationality' => 'Kenyan',
    'gender' => '',
    'county' => '',
    'constituency' => '',
    'ward' => '',
];

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
</head>
<body class="profile-page">
    <nav class="navbar navbar-expand-lg profile-navbar">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="<?= escapeHtml(BASE_URL) ?>/index.php?page=dashboard">
                <span class="brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                <span>IAIMS <small class="d-block fw-normal">Student Portal</small></span>
            </a>
            <a class="btn btn-outline-light btn-sm" href="<?= escapeHtml(BASE_URL) ?>/index.php?page=dashboard">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Dashboard
            </a>
        </div>
    </nav>

    <main class="container profile-container py-4 py-lg-5">
        <div class="profile-heading mb-4">
            <div>
                <p class="text-uppercase text-primary fw-semibold small mb-2">Student information</p>
                <h1 class="h2 fw-bold mb-2">Personal profile</h1>
                <p class="text-secondary mb-0">Provide accurate personal and residential details for your attachment record.</p>
            </div>
            <div class="profile-progress d-none d-md-flex" aria-label="Profile completion">
                <span class="progress-icon"><i class="bi bi-person-check" aria-hidden="true"></i></span>
                <span><strong id="completionCount">0%</strong><small class="d-block text-secondary">Profile completion</small></span>
            </div>
        </div>

        <div class="alert alert-info" id="profileFormStatus" role="status" hidden></div>

        <form id="profileForm" novalidate>
            <section class="card profile-card mb-4" aria-labelledby="personal-heading">
                <div class="card-header profile-card-header">
                    <span class="section-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
                    <div><h2 class="h5 mb-1" id="personal-heading">Personal details</h2><p class="small text-secondary mb-0">Your name and contact information</p></div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="first_name" class="form-label">First name <span class="text-danger">*</span></label>
                            <input class="form-control" id="first_name" name="first_name" value="<?= escapeHtml($profile['first_name']) ?>" autocomplete="given-name" maxlength="100" required>
                            <div class="invalid-feedback">Enter your first name.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="last_name" class="form-label">Last name <span class="text-danger">*</span></label>
                            <input class="form-control" id="last_name" name="last_name" value="<?= escapeHtml($profile['last_name']) ?>" autocomplete="family-name" maxlength="100" required>
                            <div class="invalid-feedback">Enter your last name.</div>
                        </div>
                        <div class="col-md-4">
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
                            <input class="form-control" id="identity_number" name="identity_number" value="<?= escapeHtml($profile['identity_number']) ?>" autocomplete="off" inputmode="numeric" pattern="[1-9][0-9]{7,}" maxlength="50" placeholder="e.g. 12345678" required>
                            <div class="invalid-feedback">Enter a valid National ID number starting with a digit.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="phone_number" class="form-label">Phone number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?= escapeHtml($profile['phone_number']) ?>" placeholder="e.g. +254 712 345 678" autocomplete="tel" maxlength="30" pattern="(?:[+0-9. ]|\(|\)|-){7,30}" required>
                            <div class="form-text">Include your country code where applicable.</div>
                            <div class="invalid-feedback">Enter a valid phone number.</div>
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
            </section>

            <section class="card profile-card mb-4" aria-labelledby="residential-heading">
                <div class="card-header profile-card-header bg-primary-subtle border-primary-subtle">
                    <span class="section-icon section-icon-gold"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                    <div>
                        <h2 class="h5 mb-1 fw-bold text-primary-emphasis" id="residential-heading">Residential details</h2>
                        <p class="small text-primary-emphasis mb-0">Where you currently reside</p>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
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
            </section>

            <div class="profile-form-footer d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                <p class="small text-secondary mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Frontend preview only: your profile is not submitted or saved.</p>
                <button type="submit" class="btn btn-profile-save btn-lg px-4">
                    <i class="bi bi-check2-circle me-2" aria-hidden="true"></i>Save profile
                </button>
            </div>
        </form>
        <p class="text-center text-secondary small mt-4 mb-0">&copy; <?= date('Y') ?> <?= escapeHtml(APP_NAME) ?></p>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= escapeHtml(BASE_URL) ?>/assets/js/profile.js"></script>
</body>
</html>