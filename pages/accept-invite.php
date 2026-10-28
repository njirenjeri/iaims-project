<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/invites.php';

// Keep the token out of Referer headers and browser caches.
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$token = is_string($token) ? $token : '';

$errorMessage = '';
$invite = findValidInvite($pdo, $token);

if ($invite !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Your session has expired. Please refresh the page and try again.';
    } elseif (!is_string($password) || !is_string($confirmPassword)) {
        $errorMessage = 'Invalid form submission.';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'The passwords do not match.';
    } else {
        // Also records which device finished the activation.
        $result = acceptInvite($pdo, $token, $password);

        if ($result['success']) {
            $_SESSION['auth_success'] = $result['message'];
            redirect('login');
        }

        $errorMessage = $result['message'];
    }
}

require __DIR__ . '/../includes/auth_top.php';
?>

<h2 class="h5 fw-semibold text-center mb-2">Set your password</h2>

<?php if ($invite === null): ?>
    <div class="alert alert-danger mt-3" role="alert">
        This invitation is invalid or has expired. Ask your administrator to send you a new one.
    </div>
    <p class="text-center small mb-0">
        <a href="<?= escapeHtml(BASE_URL) ?>/?page=login" class="link-gold text-decoration-none">Go to sign in</a>
    </p>
<?php else: ?>
    <p class="text-secondary small text-center mb-4">
        Welcome<?= $invite['first_name'] !== '' ? ', ' . escapeHtml($invite['first_name']) : '' ?>.
        Choose a password for <strong><?= escapeHtml($invite['email']) ?></strong>.
    </p>

    <?= $errorMessage !== '' ? renderAlert('danger', $errorMessage, 0) : '' ?>

    <form action="<?= escapeHtml(BASE_URL) ?>/?page=accept-invite" method="post">
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
                placeholder="Password"
                autocomplete="new-password"
                aria-label="Password"
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
                placeholder="Confirm password"
                autocomplete="new-password"
                aria-label="Confirm password"
                maxlength="72"
                required
            >
            <button type="button" class="btn bg-white border border-start-0 text-secondary shadow-none"
                    data-password-toggle="confirmPassword" aria-label="Show confirm password">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>

        <button type="submit" class="btn btn-primary w-100">Activate account</button>
    </form>

    <script src="<?= escapeHtml(BASE_URL) ?>/assets/js/password-toggle.js"></script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/auth_bottom.php'; ?>
