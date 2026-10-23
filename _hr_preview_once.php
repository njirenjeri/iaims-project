<?php
// Temporary local-only visual smoke test. Remove after browser verification.
require_once __DIR__ . '/config/app.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['user_id'] = 'local-visual-test';
$_SESSION['user_email'] = 'preview@example.invalid';
$_SESSION['user_roles'] = ['HR'];
require __DIR__ . '/pages/HR/hr_dashboard.php';
