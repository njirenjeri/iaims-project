<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/staff_actions.php';

requireAnyRole($pdo, ['ADMIN', 'HR_ADMIN']);

$actor = staffActor($pdo);
$scope = $actor['scope'];
$creatable = creatableRoles($scope);

if (!$creatable) {
    http_response_code(403);
    exit('Your account has no ministry assigned. Please contact the system administrator.');
}

$directory = directoryForScope($pdo, $scope);
$errors = [];
$old = [
    'first_name' => '', 'other_names' => '', 'email' => '', 'phone_number' => '',
    'role' => '', 'ministry_id' => $scope['all'] ? 0 : $scope['ministry_id'], 'department_id' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['first_name', 'other_names', 'email', 'phone_number', 'role'] as $field) {
        $old[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }
    foreach (['ministry_id', 'department_id'] as $field) {
        $old[$field] = isset($_POST[$field]) && ctype_digit((string) $_POST[$field]) ? (int) $_POST[$field] : 0;
    }

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $result = createStaffUser($pdo, $actor, $_POST);

        if ($result['success']) {
            setFlash('success', $result['delivery']['mode'] === 'email'
                ? 'Account created. The invitation was emailed.'
                : 'Account created. Copy the invite link below and share it with the person.');
            redirect('users-view&id=' . $result['user_id']);
        }

        $errors = $result['errors'];
    }
}

$roleCards = [
    'HR' => 'Reviews applications and places students in their ministry.',
    'HR_ADMIN' => 'Everything an HR officer does, plus intakes, capacity and their ministry\'s staff.',
    'SUPERVISOR' => 'Supervises students placed in one state department.',
    'ADMIN' => 'Manages all users, ministries and the audit log.',
];

$directoryJson = json_encode($directory, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

staffPageStart('Invite user', 'users');
?>
<a class="small link-gold text-decoration-none" href="<?= escapeHtml(urlWith('users')) ?>">
    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to users
</a>

<div class="profile-heading my-4">
    <div>
        <h1 class="h2 fw-bold mb-2">Invite a user</h1>
        <p class="text-secondary mb-0">
            <?= mailEnabled()
                ? 'They will receive an email with a link to set their password.'
                : 'Email is not set up yet. After you create the account, the invite link is shown once for you to share.' ?>
        </p>
    </div>
</div>

<?= $errors ? renderAlert('danger', implode(' ', $errors), 0) : '' ?>

<form method="post" action="<?= escapeHtml(urlWith('users-invite')) ?>" class="needs-validation" novalidate data-scope-picker>
    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">

    <section class="card profile-card mb-4" aria-labelledby="person-heading">
        <div class="card-header profile-card-header">
            <span class="section-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
            <div><h2 class="h5 mb-1" id="person-heading">Person</h2><p class="small text-secondary mb-0">Who the account is for</p></div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">First name <span class="text-danger">*</span></label>
                    <input class="form-control" id="first_name" name="first_name" value="<?= escapeHtml($old['first_name']) ?>" maxlength="100" required>
                    <div class="invalid-feedback">Enter a first name.</div>
                </div>
                <div class="col-md-6">
                    <label for="other_names" class="form-label">Other name(s)</label>
                    <input class="form-control" id="other_names" name="other_names" value="<?= escapeHtml($old['other_names']) ?>" maxlength="150">
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= escapeHtml($old['email']) ?>" maxlength="255" autocomplete="off" required>
                    <div class="invalid-feedback">Enter a valid email address.</div>
                </div>
                <div class="col-md-6">
                    <label for="phone_number" class="form-label">Phone number <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?= escapeHtml($old['phone_number']) ?>"
                           placeholder="e.g. +254 712 345 678" maxlength="30" pattern="(?:[+0-9. ]|\(|\)|-){7,30}" required>
                    <div class="invalid-feedback">Enter a valid phone number.</div>
                </div>
            </div>
        </div>
    </section>

    <section class="card profile-card mb-4" aria-labelledby="role-heading">
        <div class="card-header profile-card-header">
            <span class="section-icon section-icon-gold"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
            <div><h2 class="h5 mb-1" id="role-heading">Role and scope</h2><p class="small text-secondary mb-0">What they can do, and where</p></div>
        </div>
        <div class="card-body p-3 p-md-4">
            <fieldset class="mb-4">
                <legend class="form-label fs-6">Role <span class="text-danger">*</span></legend>
                <div class="row g-2">
                    <?php foreach ($roleCards as $key => $description): ?>
                        <?php $allowed = in_array($key, $creatable, true); ?>
                        <div class="col-sm-6">
                            <input type="radio" class="btn-check" name="role" id="role_<?= escapeHtml($key) ?>" value="<?= escapeHtml($key) ?>"
                                   <?= $old['role'] === $key ? 'checked' : '' ?> <?= $allowed ? 'required' : 'disabled' ?>>
                            <label class="btn btn-outline-secondary w-100 text-start h-100 py-2" for="role_<?= escapeHtml($key) ?>">
                                <span class="fw-semibold d-block"><?= escapeHtml(STAFF_ROLES[$key]) ?></span>
                                <span class="small d-block opacity-75">
                                    <?= $allowed ? escapeHtml($description) : 'Only a system admin can assign this role.' ?>
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="row g-3">
                <div class="col-md-6" data-scope-ministry hidden>
                    <label for="ministry_id" class="form-label">Ministry <span class="text-danger">*</span></label>
                    <select class="form-select" id="ministry_id" name="ministry_id" <?= $scope['all'] ? '' : 'disabled data-locked="true"' ?>>
                        <option value="">Select ministry</option>
                        <?php foreach ($directory as $ministry): ?>
                            <option value="<?= (int) $ministry['id'] ?>" <?= $old['ministry_id'] === $ministry['id'] ? 'selected' : '' ?>>
                                <?= escapeHtml($ministry['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$scope['all']): ?>
                        <div class="form-text">HR admins can only invite people into their own ministry.</div>
                    <?php endif; ?>
                    <div class="invalid-feedback">Select a ministry.</div>
                </div>
                <div class="col-md-6" data-scope-department hidden>
                    <label for="department_id" class="form-label">State department <span class="text-danger">*</span></label>
                    <select class="form-select" id="department_id" name="department_id" data-selected="<?= (int) $old['department_id'] ?>">
                        <option value="">Select a ministry first</option>
                    </select>
                    <div class="invalid-feedback">Select a state department.</div>
                </div>
            </div>
        </div>
    </section>

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
        <p class="small text-secondary mb-0">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
            The invite link works once and expires after <?= (int) inviteTtlHours() ?> hours.
        </p>
        <button type="submit" class="btn btn-profile-save btn-lg px-4">
            <i class="bi bi-send me-2" aria-hidden="true"></i>Create account and invite
        </button>
    </div>
</form>
<?php staffPageEnd(['admin-users.js'], 'window.adminDirectory = ' . $directoryJson . ';'); ?>
