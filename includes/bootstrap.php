<?php

// One include for every page: configuration, the database connection and the shared
// functions. Pages start with:
//
//     require_once __DIR__ . '/../includes/bootstrap.php';
//
// (use ../../includes/ from pages/student/ and other subfolders).
//
// Domain files that only some pages need, such as student.php, are required by those pages.

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/helpers.php';        // escaping, redirects, alerts, CSRF, role guard
require_once __DIR__ . '/audit.php';          // tamper-evident audit log
require_once __DIR__ . '/device.php';         // device profile and session records
require_once __DIR__ . '/auth.php';           // registration, sign-in, sign-out
require_once __DIR__ . '/password_reset.php'; // forgot and reset password
