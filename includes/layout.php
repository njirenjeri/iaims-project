<?php

require_once __DIR__ . '/helpers.php';

// Page frame for staff screens (admin, HR, supervisor): head, sidebar and closing scripts.
// A page calls staffPageStart() first and staffPageEnd() last, and writes only its own
// content in between:
//
//     staffPageStart('Users', 'users');
//     ... page content ...
//     staffPageEnd(['admin-users.js'], 'window.adminDirectory = [];');

// $current is the sidebar item to highlight: 'dashboard', 'users', 'ministries' or 'audit'.
function staffPageStart(string $title, string $current): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapeHtml($title) ?> | <?= escapeHtml(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= escapeHtml(BASE_URL) ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= escapeHtml(BASE_URL) ?>/assets/css/student-layout.css" rel="stylesheet">
</head>
<body class="profile-page">
    <div class="student-shell">
        <?php require __DIR__ . '/staff_nav.php'; ?>

        <div class="student-main">
            <main class="student-container">
    <?php
}

// $scripts are file names in assets/js/. $inlineScript is raw JavaScript run before them
// (build it with json_encode and the JSON_HEX_* flags).
function staffPageEnd(array $scripts = [], string $inlineScript = ''): void
{
    ?>
                <p class="text-center text-secondary small mt-4 mb-0">&copy; <?= date('Y') ?> <?= escapeHtml(APP_NAME) ?></p>
            </main>
        </div>
    </div>

    <?php if ($inlineScript !== ''): ?>
    <script><?= $inlineScript ?></script>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= escapeHtml(BASE_URL) ?>/assets/js/alerts.js"></script>
    <?php foreach ($scripts as $script): ?>
    <script src="<?= escapeHtml(BASE_URL) ?>/assets/js/<?= escapeHtml($script) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
    <?php
}
