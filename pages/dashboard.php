<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (in_array('STUDENT', $_SESSION['user_roles'] ?? [], true)) {
    require __DIR__ . '/student/dashboard.php';
    return;
}

if (in_array('HR', $_SESSION['user_roles'] ?? [], true)) {
    require __DIR__ . '/HR/hr_dashboard.php';
    return;
}

require __DIR__ . '/admin_dashboard.php';