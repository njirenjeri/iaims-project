<?php

require_once __DIR__ . '/audit.php';

/*
 * Device profiling and server-side session records.
 *
 * What is collected is what a browser and network honestly reveal: the User-Agent (parsed
 * on the server), the IP address, and a handful of traits the browser reports about itself
 * at sign-in. All of it is self-reported and can be spoofed, so treat it as a signal for
 * investigation, not proof. Hardware identifiers (IMEI, MAC address) are not available to
 * web pages.
 */

/* ---------- User-Agent parsing ---------- */

function parseUserAgent(string $ua): array
{
    $result = ['browser' => 'Unknown', 'browser_version' => '', 'os' => 'Unknown', 'device_type' => 'unknown'];

    if ($ua === '') {
        return $result;
    }

    // Order matters: Edge, Opera and Samsung Internet all also say "Chrome".
    $browsers = [
        'Edge' => '/Edg(?:e|A|iOS)?\/([\d.]+)/',
        'Opera' => '/(?:OPR|Opera)\/([\d.]+)/',
        'Samsung Internet' => '/SamsungBrowser\/([\d.]+)/',
        'UC Browser' => '/UCBrowser\/([\d.]+)/',
        'Firefox' => '/(?:Firefox|FxiOS)\/([\d.]+)/',
        'Chrome' => '/(?:Chrome|CriOS)\/([\d.]+)/',
        'Safari' => '/Version\/([\d.]+).*Safari\//',
    ];

    foreach ($browsers as $name => $pattern) {
        if (preg_match($pattern, $ua, $match)) {
            $result['browser'] = $name;
            $result['browser_version'] = explode('.', $match[1])[0]; // major version only
            break;
        }
    }

    // Order matters again: Android UAs contain "Linux", iOS UAs contain "Mac OS X".
    if (preg_match('/Windows NT ([\d.]+)/', $ua, $match)) {
        $windows = ['10.0' => 'Windows 10/11', '6.3' => 'Windows 8.1', '6.2' => 'Windows 8', '6.1' => 'Windows 7'];
        $result['os'] = $windows[$match[1]] ?? 'Windows';
    } elseif (preg_match('/Android ([\d.]+)/', $ua, $match)) {
        $result['os'] = 'Android ' . explode('.', $match[1])[0];
    } elseif (preg_match('/(?:iPhone|CPU) OS ([\d_]+)/', $ua, $match)) {
        $result['os'] = (strpos($ua, 'iPad') !== false ? 'iPadOS ' : 'iOS ') . explode('_', $match[1])[0];
    } elseif (strpos($ua, 'Mac OS X') !== false) {
        $result['os'] = 'macOS';
    } elseif (strpos($ua, 'CrOS') !== false) {
        $result['os'] = 'ChromeOS';
    } elseif (strpos($ua, 'Linux') !== false) {
        $result['os'] = 'Linux';
    }

    if (preg_match('/bot|crawl|spider|curl|wget|python-requests|httpclient/i', $ua)) {
        $result['device_type'] = 'bot';
    } elseif (preg_match('/iPad|Tablet|Android(?!.*Mobile)/i', $ua)) {
        $result['device_type'] = 'tablet';
    } elseif (preg_match('/Mobi|iPhone|Android/i', $ua)) {
        $result['device_type'] = 'mobile';
    } else {
        $result['device_type'] = 'desktop';
    }

    return $result;
}

/* ---------- Client-reported traits ---------- */

// The browser sends a JSON blob at sign-in. It comes from the user's machine, so every
// field is validated and anything unexpected is dropped.
function sanitizeClientInfo(string $raw): array
{
    if ($raw === '' || strlen($raw) > 2000) {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        return [];
    }

    $text = static function ($value, int $max): string {
        return is_string($value) ? mb_substr(preg_replace('/[^\p{L}\p{N} ._\-\/+]/u', '', $value), 0, $max) : '';
    };
    $size = static function ($value): string {
        return is_string($value) && preg_match('/^\d{2,5}x\d{2,5}$/', $value) ? $value : '';
    };
    $number = static function ($value, float $min, float $max): float {
        return is_numeric($value) ? max($min, min($max, (float) $value)) : 0.0;
    };

    $clean = [
        'screen' => $size($data['screen'] ?? null),
        'viewport' => $size($data['viewport'] ?? null),
        'pixel_ratio' => $number($data['pixel_ratio'] ?? 0, 0, 10),
        'timezone' => $text($data['timezone'] ?? '', 64),
        'language' => $text($data['language'] ?? '', 20),
        'platform' => $text($data['platform'] ?? '', 40),
        'platform_version' => $text($data['platform_version'] ?? '', 30),
        'model' => $text($data['model'] ?? '', 60),
        'touch' => (int) $number($data['touch'] ?? 0, 0, 20),
        'cores' => (int) $number($data['cores'] ?? 0, 0, 256),
        'memory' => $number($data['memory'] ?? 0, 0, 512),
        'color_scheme' => in_array($data['color_scheme'] ?? '', ['dark', 'light'], true) ? $data['color_scheme'] : '',
    ];

    if (isset($data['mobile'])) {
        $clean['mobile'] = (bool) $data['mobile'];
    }

    if (isset($data['languages']) && is_array($data['languages'])) {
        $clean['languages'] = array_slice(array_map(static fn ($item) => $text($item, 20), $data['languages']), 0, 5);
    }

    return $clean;
}

// A keyed hash of the traits that rarely change, so a browser update doesn't look like a
// new device. Not proof of identity: a different but similar machine can collide and a
// determined person can spoof it.
function deviceFingerprint(array $parsed, array $client): string
{
    $parts = [
        $parsed['browser'], $parsed['os'] === 'Unknown' ? '' : preg_replace('/ [\d.]+$/', '', $parsed['os']),
        $parsed['device_type'], $client['screen'] ?? '', $client['timezone'] ?? '',
        $client['language'] ?? '', $client['platform'] ?? '', (string) ($client['cores'] ?? 0),
        (string) ($client['memory'] ?? 0), (string) ($client['touch'] ?? 0),
    ];

    return hash_hmac('sha256', 'device:' . implode('|', $parts), auditKey());
}

/* ---------- Session records ---------- */

/**
 * Create the session record for a successful sign-in. Store the returned token and id in
 * $_SESSION['session_token'] and $_SESSION['session_record_id'].
 */
function startSessionRecord(PDO $pdo, string $userId, string $rawClientInfo): array
{
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
    $parsed = parseUserAgent($userAgent);
    $client = sanitizeClientInfo($rawClientInfo);

    // iPadOS Safari presents itself as a Mac; touch support gives it away.
    if ($parsed['os'] === 'macOS' && ($client['touch'] ?? 0) > 1) {
        $parsed['os'] = 'iPadOS';
        $parsed['device_type'] = 'tablet';
    }

    $deviceHash = deviceFingerprint($parsed, $client);

    $history = $pdo->prepare(
        'SELECT COUNT(*) AS total, SUM(CASE WHEN device_hash = :device_hash THEN 1 ELSE 0 END) AS same_device
         FROM user_sessions WHERE user_id = :user_id'
    );
    $history->execute(['device_hash' => $deviceHash, 'user_id' => $userId]);
    $counts = $history->fetch();

    $firstSession = (int) $counts['total'] === 0;
    $isNewDevice = (int) ($counts['same_device'] ?? 0) === 0;

    $token = bin2hex(random_bytes(32));
    $now = date('Y-m-d H:i:s');

    $insert = $pdo->prepare(
        'INSERT INTO user_sessions
            (user_id, token_hash, status, started_at, last_seen_at, ip_address, user_agent,
             browser, browser_version, os, device_type, screen, timezone, language,
             client_info, device_hash, is_new_device)
         VALUES
            (:user_id, :token_hash, :status, :started_at, :last_seen_at, :ip_address, :user_agent,
             :browser, :browser_version, :os, :device_type, :screen, :timezone, :language,
             :client_info, :device_hash, :is_new_device)'
    );
    $insert->execute([
        'user_id' => $userId,
        'token_hash' => hash('sha256', $token),
        'status' => 'active',
        'started_at' => $now,
        'last_seen_at' => $now,
        'ip_address' => clientIp(),
        'user_agent' => $userAgent,
        'browser' => $parsed['browser'],
        'browser_version' => $parsed['browser_version'],
        'os' => $parsed['os'],
        'device_type' => $parsed['device_type'],
        'screen' => $client['screen'] ?? '',
        'timezone' => $client['timezone'] ?? '',
        'language' => $client['language'] ?? '',
        'client_info' => $client === [] ? '{}' : json_encode($client, JSON_UNESCAPED_UNICODE),
        'device_hash' => $deviceHash,
        'is_new_device' => $isNewDevice ? 1 : 0,
    ]);

    return [
        'token' => $token,
        'session_record_id' => (int) $pdo->lastInsertId(),
        'first_session' => $firstSession,
        'is_new_device' => $isNewDevice,
        'summary' => trim($parsed['browser'] . ' ' . $parsed['browser_version']) . ' on ' . $parsed['os']
            . ' (' . $parsed['device_type'] . ')',
    ];
}

// Called on every request for a signed-in user. Returns false when the session record is
// missing or no longer active, which is what makes suspensions take effect immediately.
function touchSessionRecord(PDO $pdo): bool
{
    $token = $_SESSION['session_token'] ?? '';

    if (!is_string($token) || $token === '') {
        return false;
    }

    $statement = $pdo->prepare(
        'SELECT session_record_id, user_id, status, last_seen_at FROM user_sessions WHERE token_hash = :token_hash LIMIT 1'
    );
    $statement->execute(['token_hash' => hash('sha256', $token)]);
    $record = $statement->fetch();

    if (!$record || $record['status'] !== 'active' || $record['user_id'] !== (string) ($_SESSION['user_id'] ?? '')) {
        return false;
    }

    $_SESSION['session_record_id'] = (int) $record['session_record_id'];

    // Refresh last_seen at most once a minute to keep the write load down.
    if (strtotime($record['last_seen_at']) < time() - 60) {
        $update = $pdo->prepare('UPDATE user_sessions SET last_seen_at = :now WHERE session_record_id = :id');
        $update->execute(['now' => date('Y-m-d H:i:s'), 'id' => $record['session_record_id']]);
    }

    return true;
}

// End the current session record (for example on sign-out).
function endSessionRecord(PDO $pdo, string $status = 'logged_out'): void
{
    $token = $_SESSION['session_token'] ?? '';

    if (!is_string($token) || $token === '') {
        return;
    }

    $statement = $pdo->prepare(
        "UPDATE user_sessions SET status = :status, last_seen_at = :now
         WHERE token_hash = :token_hash AND status = 'active'"
    );
    $statement->execute([
        'status' => $status,
        'now' => date('Y-m-d H:i:s'),
        'token_hash' => hash('sha256', $token),
    ]);
}

// End every active session for a user (suspension, password reset). Returns how many ended.
function revokeUserSessions(PDO $pdo, string $userId, string $status = 'revoked'): int
{
    $statement = $pdo->prepare(
        "UPDATE user_sessions SET status = :status, last_seen_at = :now
         WHERE user_id = :user_id AND status = 'active'"
    );
    $statement->execute(['status' => $status, 'now' => date('Y-m-d H:i:s'), 'user_id' => $userId]);

    return $statement->rowCount();
}
