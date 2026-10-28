<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

requireAnyRole($pdo, ['HR', 'HR_ADMIN', 'SUPERVISOR']);

staffPageStart('Dashboard', 'dashboard');
?>
<?= renderFlash() ?>

<div class="profile-heading mb-4">
    <div>
        <p class="text-uppercase text-primary fw-semibold small mb-2"><?= escapeHtml(STAFF_ROLES[staffRoleKey((array) $_SESSION['user_roles'])] ?? 'Staff') ?></p>
        <h1 class="h2 fw-bold mb-2">Welcome</h1>
        <p class="text-secondary mb-0">Your workspace is being set up. Application review, placements and supervision are coming in the next modules.</p>
    </div>
</div>

<?php if (userHasRole('HR_ADMIN')): ?>
    <section class="card profile-card" aria-labelledby="team-heading">
        <div class="card-header profile-card-header">
            <span class="section-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
            <div><h2 class="h5 mb-1" id="team-heading">Your ministry's staff</h2><p class="small text-secondary mb-0">Invite and manage HR officers and supervisors</p></div>
        </div>
        <div class="card-body">
            <a class="btn btn-profile-save" href="<?= escapeHtml(urlWith('users')) ?>">Open users</a>
        </div>
    </section>
<?php endif; ?>
<?php staffPageEnd(); ?>
