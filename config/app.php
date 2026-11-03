<?php

// Name displayed across the application.
define('APP_NAME', 'Industrial Attachment Information Management System');
define('APP_ENV', 'development'); // 'development' or 'production'

// Folder the project is served from, and the absolute address used in emails.
// Never build APP_URL from $_SERVER['HTTP_HOST'] (host header injection).
define('BASE_URL', '/iaims-project');
define('APP_URL', 'http://localhost' . BASE_URL);

// Email. While MAIL_ENABLED is false, invite and reset links are shown once to the admin
// instead of being emailed. Turn it on after configuring SMTP in production.
define('MAIL_FROM', 'no-reply@example.com');
define('MAIL_ENABLED', false);
define('INVITE_TTL_HOURS', 72);
define('PASSWORD_RESET_TTL_MINUTES', 30);
define('PASSWORD_RESET_COOLDOWN_SECONDS', 60);

// Attachments run between these lengths (in weeks).
define('ATTACHMENT_MIN_WEEKS', 8);
define('ATTACHMENT_MAX_WEEKS', 12);

// Uploaded application documents (outside public URLs; see storage/.htaccess).
define('STORAGE_PATH', __DIR__ . '/../storage');
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);

// Secrets live in config/secrets.php, which is not committed (see secrets.sample.php).
if (is_file(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
}

// Audit log: the separate schema it lives in, and proxies trusted to set X-Forwarded-For.
define('AUDIT_DB', 'iaims_audit');
define('TRUSTED_PROXIES', []);

ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
date_default_timezone_set('Africa/Nairobi');
