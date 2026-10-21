<?php

require_once __DIR__ . '/config/app.php';

define('IAIMS_BOOTSTRAPPED', true);

// Harden the session cookie before the session starts.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Pages anyone can open.
$publicRoutes = [
    'login' => __DIR__ . '/pages/login.php',
    'logout' => __DIR__ . '/pages/logout.php',
    'forgot-password' => __DIR__ . '/pages/forgot-password.php',
    'reset-password' => __DIR__ . '/pages/reset-password.php',
    'admin-demo-invite' => __DIR__ . '/pages/Admin/admin users/accept-invite.php',
];

// Pages that require a signed-in user.
$protectedRoutes = [
    'dashboard' => __DIR__ . '/pages/dashboard.php',
    'admin-dashboard' => __DIR__ . '/pages/Admin/admin dashboard/dashboard.php',
    'admin-invite' => __DIR__ . '/pages/Admin/includes/api.php',
    'admin-users' => __DIR__ . '/pages/Admin/admin users/users.php',
    'admin-user' => __DIR__ . '/pages/Admin/admin users/user.php',
    'admin-user-detail' => __DIR__ . '/pages/Admin/admin users/user.php',
    'admin-invite-page' => __DIR__ . '/pages/Admin/admin users/invite.php',
    'admin-audit' => __DIR__ . '/pages/Admin/audit logs/audit.php',
    'admin-security' => __DIR__ . '/pages/Admin/admin dashboard/security.php',
    'admin-settings' => __DIR__ . '/pages/Admin/admin dashboard/settings.php',
    'admin-api' => __DIR__ . '/pages/Admin/includes/api.php',
    'profile' => __DIR__ . '/pages/student/profile.php',
    'qualifications' => __DIR__ . '/pages/student/qualifications.php',
    'applications' => __DIR__ . '/pages/student/applications.php',
];

$page = $_GET['page'] ?? 'login';

if (!is_string($page)) {
    $page = 'login';
}

if (isset($publicRoutes[$page])) {
    $file = $publicRoutes[$page];
} elseif (isset($protectedRoutes[$page])) {
    if (empty($_SESSION['user_id'])) {
        $_SESSION['auth_error'] = 'Please sign in to access that page.';
        header('Location: ' . BASE_URL . '/?page=login');
        exit;
    }

    if (str_starts_with($page, 'admin-')) {
        require_once __DIR__ . '/pages/Admin/includes/data.php';
        adminRequireAccess();
    }

    $file = $protectedRoutes[$page];
} else {
    http_response_code(404);
    exit('Page not found');
}

require $file;
