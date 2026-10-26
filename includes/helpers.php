<?php

// Small helpers used by every page. Nothing in here touches the database.

/* ---------- Output ---------- */

// Escape values before displaying them in HTML (prevents XSS).
function escapeHtml($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* ---------- Navigation ---------- */

function redirect(string $page, string $fragment = ''): void
{
    header('Location: ' . BASE_URL . '/?page=' . $page . ($fragment !== '' ? '#' . $fragment : ''));
    exit;
}

// Only allow a signed-in user who holds the given role.
function requireRole(string $role): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login');
    }

    if (!in_array($role, $_SESSION['user_roles'] ?? [], true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

/* ---------- Messages ---------- */

// Render a dismissible Bootstrap alert that closes itself after a timeout (0 = stays).
function renderAlert(string $type, string $message, ?int $timeout = null): string
{
    $allowed = ['success', 'danger', 'warning', 'info'];
    $type = in_array($type, $allowed, true) ? $type : 'info';

    // Errors stay a little longer so they can be read.
    $timeout ??= ($type === 'danger' ? 8000 : 5000);

    return sprintf(
        '<div class="alert alert-%s alert-dismissible fade show" role="alert" data-timeout="%d">
            %s
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>',
        $type,
        $timeout,
        escapeHtml($message)
    );
}

// A one-time message shown after a redirect.
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function renderFlash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return renderAlert($flash['type'], $flash['message']);
}

/* ---------- Security ---------- */

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf($token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ---------- Identifiers ---------- */

// Generate a version 4 UUID.
function generateUuid(): string
{
    $data = random_bytes(16);

    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
