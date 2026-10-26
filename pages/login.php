<?php

// Load the existing application settings.
require_once __DIR__ . '/../config/app.php';

// Load the database connection and reusable authentication functions.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// index.php already starts the session.
// Start one here only if this file is accessed directly.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate a CSRF token to protect form submissions.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Messages displayed by the login page.
$errorMessage = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);
$successMessage = $_SESSION['auth_success'] ?? '';
unset($_SESSION['auth_success']);

// Process the form before rendering the HTML.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    // Reject requests without a valid CSRF token.
    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $errorMessage = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $action = $_POST['auth_action'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Ensure submitted values have the expected types.
        if (
            !is_string($email) ||
            !is_string($password) ||
            !in_array($action, ['login', 'register'], true)
        ) {
            $errorMessage = 'Invalid form submission.';
        } elseif ($action === 'register') {
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!is_string($confirmPassword) ||
                $password !== $confirmPassword) {
                $errorMessage = 'The passwords do not match.';
            } else {
                // This function always assigns the STUDENT role.
                $result = registerStudent($pdo, $email, $password);

                if ($result['success']) {
                    $_SESSION['auth_success'] = $result['message'];

                    // Redirect after registration to avoid resubmission.
                    header('Location: ' . BASE_URL . '/?page=login');
                    exit;
                }

                $errorMessage = $result['message'];
            }
        } else {
            // Authenticate using the saved password hash.
            $user = authenticateUser($pdo, $email, $password);

            if ($user === null) {
                $errorMessage = 'Invalid email or password, or the account is inactive.';
            } elseif (empty($user['roles'])) {
                $errorMessage = 'Your account has no assigned role. Please contact the administrator.';
            } else {
                // Replace the session ID after successful authentication.
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_roles'] = $user['roles'];

                // Send the authenticated user to the dashboard.
                header('Location: ' . BASE_URL . '/?page=dashboard');
                exit;
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link
        href="<?= BASE_URL ?>/assets/css/style.css"
        rel="stylesheet"
    >
</head>
<body class="bg-body-tertiary">
    <main class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
        <section class="card shadow-sm border-0 auth-card">
            <header class="text-center p-4">
                <img
                    src="<?= BASE_URL ?>/assets/img/coat-of-arms.png"
                    alt="Coat of arms"
                    class="coat-of-arms mb-2"
                >
                <p class="text-secondary opacity-50 small fw-semibold mb-1 republic-heading">REPUBLIC OF KENYA</p>
                <!-- <p class="small mb-2">
                    <a href="https://commons.wikimedia.org/wiki/File:Coat_of_arms_of_Kenya_(Official).svg" rel="noopener noreferrer" target="_blank">Coat of Arms by Ashiriz</a>
                    · <a href="https://creativecommons.org/licenses/by-sa/3.0/" rel="license noopener noreferrer" target="_blank">CC BY-SA 3.0</a>
                </p> -->

                <h1 class="h4 fw-semibold mb-1">Industrial Attachment</h1>

                <p class="text-secondary small mb-0">Information Management System</p>
            </header>

            <hr class="gold-divider">

            <div class="card-body p-4">
                <form action="<?= BASE_URL ?>/?page=login" id="authForm" method="post" novalidate>
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                    <input type="hidden" name="auth_action" id="authAction" value="login">

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

                    <!-- Registration only: unmet password rules -->
                    <div class="small mt-2" id="passwordFeedback" aria-live="polite" hidden></div>

                    <!-- Registration only: confirm password -->
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
                        <a href="<?= BASE_URL ?>/?page=forgot-password" id="forgotPasswordLink" class="small text-decoration-none link-gold">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-3 text-capitalize" id="submitButton">Sign in</button>

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

    <!-- bootstrap js -->
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

     <!-- local js -->
      <script src="<?= BASE_URL ?>/assets/js/alerts.js"></script>
      <script src="<?= BASE_URL ?>/assets/js/app.js"></script>

</body>
</html>
