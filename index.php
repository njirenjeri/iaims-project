<?php

require_once __DIR__ . '/config/app.php';

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
];

// Pages that require a signed-in user.
$protectedRoutes = [
    'dashboard' => __DIR__ . '/pages/dashboard.php',
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
        header('Location: ' . BASE_URL . '/?page=login');
        exit;
    }
    $file = $protectedRoutes[$page];
} else {
    http_response_code(404);
    exit('Page not found');
}

require $file;
