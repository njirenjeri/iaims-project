<?php

// require_once __DIR__ . '/config/app.php';

// define('IAIMS_BOOTSTRAPPED', true);

// Harden the session cookie before the session starts.
// session_set_cookie_params([
//     'lifetime' => 0,
//     'path' => '/',
//     'secure' => !empty($_SERVER['HTTPS']),
//     'httponly' => true,
//     'samesite' => 'Lax',
// ]);
// session_start();

// Pages anyone can open.
// $publicRoutes = [
//     'login' => __DIR__ . '/pages/login.php',
//     'logout' => __DIR__ . '/pages/logout.php',
//     'forgot-password' => __DIR__ . '/pages/forgot-password.php',
//     'reset-password' => __DIR__ . '/pages/reset-password.php',
//     'admin-demo-invite' => __DIR__ . '/pages/Admin/admin users/accept-invite.php',
// ];

// Pages that require a signed-in user.
// $protectedRoutes = [
//     'dashboard' => __DIR__ . '/pages/dashboard.php',
//     // 'admin-dashboard' => __DIR__ . '/pages/Admin/admin dashboard/dashboard.php',
//     'admin-dashboard' => __DIR__ . '/pages/Admin/dashboard.php',
//     // 'admin-invite' => __DIR__ . '/pages/Admin/includes/api.php',
//     'users' => __DIR__ . '/pages/Admin/users.php',
//     'ministries' => __DIR__ . '/pages/Admin/ministries.php',
//     'users-invite' => __DIR__ . '/pages/Admin/user.php',
//     // 'admin-user-detail' => __DIR__ . '/pages/Admin/admin users/user.php',
//     // 'admin-invite-page' => __DIR__ . '/pages/Admin/admin users/invite.php',
//     // 'admin-audit' => __DIR__ . '/pages/Admin/audit logs/audit.php',
//     // 'admin-security' => __DIR__ . '/pages/Admin/admin dashboard/security.php',
//     // 'admin-settings' => __DIR__ . '/pages/Admin/admin dashboard/settings.php',
//     'admin-api' => __DIR__ . '/pages/Admin/includes/api.php',
//     'profile' => __DIR__ . '/pages/student/profile.php',
//     'qualifications' => __DIR__ . '/pages/student/qualifications.php',
//     'applications' => __DIR__ . '/pages/student/applications.php',
// ];

// $page = $_GET['page'] ?? 'login';

// if (!is_string($page)) {
//     $page = 'login';
// }

// if (isset($publicRoutes[$page])) {
//     $file = $publicRoutes[$page];
// } elseif (isset($protectedRoutes[$page])) {
//     if (empty($_SESSION['user_id'])) {
//         $_SESSION['auth_error'] = 'Please sign in to access that page.';
//         header('Location: ' . BASE_URL . '/?page=login');
//         exit;
//     }

//     if (str_starts_with($page, 'admin-')) {
//         require_once __DIR__ . '/pages/Admin/includes/data.php';
//         adminRequireAccess();
//     }

//     $file = $protectedRoutes[$page];
// } else {
//     http_response_code(404);
//     exit('Page not found');
// }

// require $file;




// Entry point. Every page is reached as /?page=<route>; this file starts the session,
// confirms a signed-in user still has a live session, and loads the requested page.

require_once __DIR__ . '/config/app.php';

// Basic protections for every response.
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Harden the session cookie before the session starts.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => BASE_URL === '' ? '/' : BASE_URL . '/',
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// A signed-in user must still have an active server-side session record. This is what makes a
// suspension, archive, role change or password reset take effect on their very next request.
if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/bootstrap.php';

    if (!touchSessionRecord($pdo)) {
        auditLogSafe($pdo, 'auth.session_rejected', 'denied', [
            'details' => ['reason' => 'missing_or_inactive_session'],
        ]);

        $_SESSION = [];
        session_regenerate_id(true);
        setFlash('warning', 'Your session has ended. Please sign in again.');
        redirect('login');
    }
}

// Pages anyone can open.
$publicRoutes = [
    'login' => __DIR__ . '/pages/login.php',
    'logout' => __DIR__ . '/pages/logout.php',
    'forgot-password' => __DIR__ . '/pages/forgot-password.php',
    'reset-password' => __DIR__ . '/pages/reset-password.php',
    'accept-invite' => __DIR__ . '/pages/accept-invite.php',
];

// Pages that need a signed-in user. Each page then checks the role it needs itself.
$protectedRoutes = [
    'dashboard' => __DIR__ . '/pages/dashboard.php',

    // Students
    'profile' => __DIR__ . '/pages/student/profile.php',
    'applications' => __DIR__ . '/pages/student/applications.php',

    // System admin and HR admin
    'users' => __DIR__ . '/pages/admin/users.php',
    'users-invite' => __DIR__ . '/pages/admin/user-invite.php',
    'users-view' => __DIR__ . '/pages/admin/user-detail.php',

    // System admin
    'ministries' => __DIR__ . '/pages/admin/ministries.php',
    'audit-log' => __DIR__ . '/pages/admin/audit-log.php',
];

$page = $_GET['page'] ?? (empty($_SESSION['user_id']) ? 'login' : 'dashboard');

if (!is_string($page)) {
    $page = 'login';
}

if (isset($publicRoutes[$page])) {
    $file = $publicRoutes[$page];
} elseif (isset($protectedRoutes[$page])) {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/?page=login');
        exit;
    }

    $file = $protectedRoutes[$page];
} else {
    http_response_code(404);
    exit('Page not found');
}

require $file;
