<?php

define('APP_NAME', 'Industrial Attachment Information Management System');
define('APP_ENV', 'development'); // 'development' or 'production'

define('BASE_URL', '/iaims-project');
// Absolute URL used in emails. Not build this from $_SERVER['HTTP_HOST'] (host header injection).
define('APP_URL', 'http://localhost' . BASE_URL);


define('MAIL_FROM', 'no-reply@example.com');
define('PASSWORD_RESET_TTL_MINUTES', 30);
define('PASSWORD_RESET_COOLDOWN_SECONDS', 60);

define('STORAGE_PATH', __DIR__ . '/../storage');
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);

ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
date_default_timezone_set('Africa/Nairobi');


if (is_file(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
}

define('AUDIT_DB', 'iaims_audit');
// IPs of reverse proxies allowed to set X-Forwarded-For. Leave empty if you have none.
define('TRUSTED_PROXIES', []);
