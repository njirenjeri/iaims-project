<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (in_array('STUDENT', $_SESSION['user_roles'] ?? [], true)) {
    require __DIR__ . '/student/dashboard.php';
    return;
}

if (in_array('ADMIN', $_SESSION['user_roles'] ?? [], true)) {
    require __DIR__ . '/Admin/admin dashboard/dashboard.php';
    return;
}

// Other roles get their dashboards with their modules.
require __DIR__ . '/../includes/auth_top.php';
?>

<h2 class="h5 fw-semibold text-center mb-2">Dashboard coming soon</h2>
<p class="text-secondary small text-center mb-4">Your account's dashboard hasn't been set up yet.</p>

<form method="post" action="<?= escapeHtml(BASE_URL) ?>/?page=logout">
    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
    <button type="submit" class="btn btn-primary w-100">Sign out</button>
</form>
if (in_array('HR', $_SESSION['user_roles'] ?? [], true)) {
    require __DIR__ . '/HR/hr_dashboard.php';
    return;
}

require __DIR__ . '/admin_dashboard.php';