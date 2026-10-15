<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Keep the token out of Referer headers and browser caches.
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$token = is_string($token) ? $token : '';

$errorMessage = '';
$reset = findValidPasswordReset($pdo, $token);

if ($reset !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Your session has expired. Please refresh the page and try again.';
    } elseif (!is_string($password) || !is_string($confirmPassword)) {
        $errorMessage = 'Invalid form submission.';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'The passwords do not match.';
    } else {
        $result = resetPassword($pdo, $token, $password);

        if ($result['success']) {
            $_SESSION['auth_success'] = $result['message'];
            header('Location: ' . BASE_URL . '/?page=login');
            exit;
        }

        $errorMessage = $result['message'];
    }
}

require __DIR__ . '/../includes/auth_top.php';
?>

<h2 class="h5 fw-semibold text-center mb-4">Reset password</h2>

<?php if ($reset === null): ?>
    <div class="alert alert-danger" role="alert">
        This reset link is invalid or has expired.
    </div>
    <p class="text-center small mb-0">
        <a href="<?= BASE_URL ?>/?page=forgot-password" class="link-gold text-decoration-none">
            Request a new link
        </a>
    </p>
<?php else: ?>
    <?php if ($errorMessage !== ''): ?>
        <?= renderAlert('danger', $errorMessage) ?>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>/?page=reset-password" method="post">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
        <input type="hidden" name="token" value="<?= escapeHtml($token) ?>">

        <div class="input-group input-group-lg mb-2">
            <span class="input-group-text bg-white text-secondary">
                <i class="bi bi-lock" aria-hidden="true"></i>
            </span>
            <input
                type="password"
                class="form-control border-start-0 border-end-0 shadow-none"
                id="password"
                name="password"
                placeholder="New password"
                autocomplete="new-password"
                aria-label="New password"
                maxlength="72"
                required
            >
            <button type="button" class="btn bg-white border border-start-0 text-secondary shadow-none"
                    data-password-toggle="password" aria-label="Show password">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>

        <p class="form-text mb-3">
            At least 8 characters, with an uppercase letter, a lowercase letter and a special character.
        </p>

        <div class="input-group input-group-lg mb-3">
            <span class="input-group-text bg-white text-secondary">
                <i class="bi bi-lock-fill" aria-hidden="true"></i>
            </span>
            <input
                type="password"
                class="form-control border-start-0 border-end-0 shadow-none"
                id="confirmPassword"
                name="confirm_password"
                placeholder="Confirm new password"
                autocomplete="new-password"
                aria-label="Confirm new password"
                maxlength="72"
                required
            >
            <button type="button" class="btn bg-white border border-start-0 text-secondary shadow-none"
                    data-password-toggle="confirmPassword" aria-label="Show confirm password">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>

        <button type="submit" class="btn btn-primary w-100">Reset password</button>
    </form>

    <script>
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordToggle);
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
        });
    </script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/auth_bottom.php'; ?>
