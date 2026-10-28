<?php

require_once __DIR__ . '/../includes/bootstrap.php';

// index.php already starts the session; start one only if this file is opened directly.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// // Already signed in: go to the dashboard instead of showing the form again.
// if (!empty($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
//     redirect('dashboard');
// }

// // Messages displayed by the login page.
// $errorMessage = '';
$successMessage = $_SESSION['auth_success'] ?? '';
unset($_SESSION['auth_success']);

// Generate a CSRF token to protect form submissions.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Already signed in: go to the dashboard instead of showing the form again.
if (!empty($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    redirect('dashboard');
}

// Messages displayed by the login page.
$errorMessage = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['auth_action'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Your session has expired. Please refresh the page and try again.';
    } elseif (!is_string($email) || !is_string($password) || !in_array($action, ['login', 'register'], true)) {
        $errorMessage = 'Invalid form submission.';
    } elseif ($action === 'register') {
        $confirm = $_POST['confirm_password'] ?? '';
        $result = processRegistration($pdo, $email, $password, is_string($confirm) ? $confirm : '');

        if ($result['success']) {
            $_SESSION['auth_success'] = $result['message'];
            redirect('login');
        }

        $errorMessage = $result['message'];
    } else {
        $deviceInfo = is_string($_POST['device_info'] ?? null) ? $_POST['device_info'] : '';
        $result = processLogin($pdo, $email, $password, $deviceInfo);

        if ($result['success']) {
            redirect('dashboard');
        }

        $errorMessage = $result['message'];
    }
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapeHtml(APP_NAME) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= escapeHtml(BASE_URL) ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
    <main class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
        <section class="card shadow-sm border-0 auth-card">
            <header class="text-center p-4">
                <img src="<?= escapeHtml(BASE_URL) ?>/assets/img/coat-of-arms.png" alt="Coat of arms" class="coat-of-arms mb-2">
                <p class="text-secondary opacity-50 small fw-semibold mb-1 republic-heading">REPUBLIC OF KENYA</p>

                <h1 class="h4 fw-semibold mb-1">Industrial Attachment</h1>
                <p class="text-secondary small mb-0">Information Management System</p>
            </header>

            <hr class="gold-divider">

            <div class="card-body p-4">
                <form action="<?= escapeHtml(BASE_URL) ?>/?page=login" id="authForm" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrf) ?>">
                    <input type="hidden" name="auth_action" id="authAction" value="login">
                    <input type="hidden" name="device_info" id="deviceInfo" value="">

                    <?= renderFlash() ?>

                    <?php if ($errorMessage !== ''): ?>
                        <?= renderAlert('danger', $errorMessage) ?>
                    <?php endif; ?>

                    <?php if ($successMessage !== ''): ?>
                        <?= renderAlert('success', $successMessage) ?>
                    <?php endif; ?>

                    <div class="input-group input-group-lg mb-3">
                        <span class="input-group-text bg-white text-secondary">
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                        </span>
                        <input
                            type="email"
                            class="form-control border-start-0 shadow-none"
                            id="email"
                            name="email"
                            placeholder="name@example.com"
                            autocomplete="email"
                            aria-label="Email"
                            required
                        >
                    </div>

                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white text-secondary">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                        </span>
                        <input
                            type="password"
                            class="form-control border-start-0 border-end-0 shadow-none"
                            id="password"
                            name="password"
                            placeholder="Password"
                            autocomplete="current-password"
                            aria-label="Password"
                            maxlength="72"
                            required
                        >
                        <button
                            type="button"
                            class="btn bg-white border border-start-0 text-secondary shadow-none"
                            data-password-toggle="password"
                            aria-label="Show password"
                        >
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="small mt-2" id="passwordFeedback" aria-live="polite" hidden></div>

                    <div class="mt-3" id="confirmPasswordWrapper" hidden>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white text-secondary">
                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                            </span>
                            <input
                                type="password"
                                class="form-control border-start-0 border-end-0 shadow-none"
                                id="confirmPassword"
                                name="confirm_password"
                                placeholder="Confirm password"
                                autocomplete="new-password"
                                aria-label="Confirm password"
                                maxlength="72"
                            >
                            <button
                                type="button"
                                class="btn bg-white border border-start-0 text-secondary shadow-none"
                                data-password-toggle="confirmPassword"
                                aria-label="Show confirm password"
                            >
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div id="confirmPasswordFeedback" class="small mt-2" aria-live="polite"></div>
                    </div>

                    <div class="text-end mt-2" id="forgotPasswordWrapper">
                        <a href="<?= escapeHtml(BASE_URL) ?>/?page=forgot-password" id="forgotPasswordLink" class="small text-decoration-none link-gold">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-3" id="submitButton">Sign in</button>

                    <p class="text-center small mt-4 mb-0">
                        <span id="togglePrompt">Don't have an account?</span>
                        <button type="button" id="toggleAuth" class="btn btn-link link-gold p-0 ms-1 text-decoration-none">
                            Register
                        </button>
                    </p>
                </form>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= escapeHtml(BASE_URL) ?>/assets/js/alerts.js"></script>
    <script src="<?= escapeHtml(BASE_URL) ?>/assets/js/device-info.js"></script>
    <script src="<?= escapeHtml(BASE_URL) ?>/assets/js/app.js"></script>
</body>
</html>
