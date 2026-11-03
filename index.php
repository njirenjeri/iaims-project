<?php

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

    // HR officers and HR admins, each tied to one department (the pages check which of the two)
    'hr-queue' => __DIR__ . '/pages/hr/queue.php',
    'hr-application' => __DIR__ . '/pages/hr/application.php',
    'hr-document' => __DIR__ . '/pages/hr/document.php',
    'hr-capacity' => __DIR__ . '/pages/hr/capacity.php',
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
