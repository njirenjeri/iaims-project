<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_GET['action'])) {
    return;
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf'], $token)) {
    json_out(403, ['error' => 'Your session has expired. Reload the page and try again.']);
}
$in = json_decode((string) file_get_contents('php://input', false, null, 0, 1048576), true);
if (!is_array($in)) {
    json_out(400, ['error' => 'Invalid request.']);
}

if ($_GET['action'] === 'save') {
    try {
        $state = [
            'logs' => clean_rows($in['logs'] ?? null, ['id' => 'int', 'sid' => 'int', 'week' => 'int', 'period' => 'str', 'hours' => 'int', 'submitted' => 'str', 'status' => ['Pending', 'Approved', 'Returned'], 'comment' => 'str', 'work' => 'str', 'learn' => 'str']),
            'visits' => clean_rows($in['visits'] ?? null, ['id' => 'int', 'sid' => 'int', 'date' => 'str', 'time' => 'str', 'mode' => ['Physical', 'Virtual'], 'purpose' => 'str', 'status' => ['Scheduled', 'Completed', 'Cancelled']]),
            'assessments' => clean_rows($in['assessments'] ?? null, ['sid' => 'int', 'pct' => 'int', 'date' => 'str', 'comment' => 'str']),
            'reports' => clean_rows($in['reports'] ?? null, ['sid' => 'int', 'title' => 'str', 'submitted' => 'str', 'status' => ['Not submitted', 'Submitted', 'Graded'], 'grade' => 'grade']),
            'sent' => clean_rows($in['sent'] ?? null, ['to' => 'str', 'subject' => 'str', 'body' => 'str', 'date' => 'str']),
        ];
    } catch (InvalidArgumentException $e) {
        json_out(422, ['error' => 'Your changes could not be saved: ' . $e->getMessage()]);
    }
    $_SESSION['state'] = $state; // DATABASE: save $state for the signed-in supervisor here.
    json_out(200, ['saved' => true]);
}

if ($_GET['action'] === 'password') {
    $old = (string) ($in['old'] ?? '');
    $new = (string) ($in['next'] ?? '');
    $confirm = (string) ($in['confirm'] ?? '');
    if ($old === '') {
        json_out(422, ['error' => 'Enter your current password.']);
    }
    if (strlen($new) < 10 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
        json_out(422, ['error' => 'The new password needs at least 10 characters, with letters and numbers.']);
    }
    if (!hash_equals($new, $confirm)) {
        json_out(422, ['error' => 'The new passwords do not match.']);
    }
    // DATABASE: check $old against the stored hash with password_verify(), then store password_hash($new, PASSWORD_DEFAULT).
    json_out(200, ['message' => 'Password accepted. Connect this step to your user table to store it.']);
}

json_out(404, ['error' => 'Unknown action.']);
