<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Your session has expired. Please refresh the page and try again.';
    } elseif (!is_string($email) || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } else {
        $token = createPasswordResetToken($pdo, $email);

        if ($token !== null) {
            sendPasswordResetEmail(strtolower(trim($email)), $token);
        }

        // Same message whether or not the account exists, so emails can't be enumerated.
        $_SESSION['auth_success'] = 'If an account exists for that email, a password reset link has been sent.';
        header('Location: ' . BASE_URL . '/?page=login');
        exit;
    }
}

require __DIR__ . '/../includes/auth_top.php';
?>

<h2 class="h5 fw-semibold text-center mb-2">Forgot password?</h2>
<p class="text-secondary small text-center mb-4">
    Enter your email and we'll send you a link to reset your password.
</p>

<?php if ($errorMessage !== ''): ?>
    <?= renderAlert('danger', $errorMessage) ?>
<?php endif; ?>

<form action="<?= BASE_URL ?>/?page=forgot-password" method="post">
    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">

    <div class="input-group input-group-lg mb-3">
        <span class="input-group-text bg-white text-secondary">
            <i class="bi bi-envelope" aria-hidden="true"></i>
        </span>
        <input
            type="email"
            class="form-control border-start-0 shadow-none"
            name="email"
            placeholder="name@example.com"
            autocomplete="email"
            aria-label="Email"
            required
        >
    </div>

    <button type="submit" class="btn btn-primary w-100">Send reset link</button>
</form>

<p class="text-center small mt-4 mb-0">
    <a href="<?= BASE_URL ?>/?page=login" class="link-gold text-decoration-none">Back to sign in</a>
</p>

<?php require __DIR__ . '/../includes/auth_bottom.php'; ?>
