
<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../config/app.php';


// Everyone lands on ?page=dashboard after signing in; the role decides what they see.
if (userHasRole('ADMIN')) {
    require __DIR__ . '/admin/dashboard.php';
    return;
}

if (userHasRole('STUDENT')) {
    require __DIR__ . '/student/dashboard.php';
    return;
}

if (userHasRole('HR') || userHasRole('HR_ADMIN') || userHasRole('SUPERVISOR')) {
    require __DIR__ . '/staff/home.php';
    return;
}

// A role we don't have a dashboard for.
require __DIR__ . '/../includes/auth_top.php';
?>

<h2 class="h5 fw-semibold text-center mb-2">Dashboard coming soon</h2>
<p class="text-secondary small text-center mb-4">Your account's dashboard hasn't been set up yet.</p>

<form method="post" action="<?= escapeHtml(BASE_URL) ?>/?page=logout">
    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
    <button type="submit" class="btn btn-primary w-100">Sign out</button>
</form>

<?php require __DIR__ . '/../includes/auth_bottom.php'; ?>
