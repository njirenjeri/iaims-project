<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    // Clear everything and issue a fresh session ID.
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['auth_success'] = 'You have been signed out.';
}

redirect(empty($_SESSION['user_id']) ? 'login' : 'dashboard');
