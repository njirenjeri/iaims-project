<?php

/*
 * Tamper-evident audit logging.
 *
 * - Rows live in a separate schema (AUDIT_DB) so the app's database user can be limited
 *   to INSERT and SELECT there.
 * - Every row stores an HMAC over its own content plus the previous row's HMAC. The key
 *   (AUDIT_HMAC_KEY) is kept outside the database, so editing or deleting rows breaks the
 *   chain and cannot be repaired by someone who only has database access.
 * - A MySQL advisory lock serialises writers so two requests can't both extend the same
 *   previous hash and fork the chain.
 *
 * Use auditLog() for actions that must not happen unrecorded (it throws on failure) and
 * auditLogSafe() for security events where a logging fault must not lock people out.
 */

const AUDIT_HASH_FIELDS = [
    'prev_hash', 'occurred_at', 'actor_user_id', 'actor_email', 'actor_roles',
    'session_record_id', 'action', 'outcome', 'target_type', 'target_id',
    'target_label', 'details', 'ip_address', 'user_agent', 'request_method',
    'request_path', 'request_id',
];

function auditGenesisHash(): string
{
    return str_repeat('0', 64);
}

function auditKey(): string
{
    $key = defined('AUDIT_HMAC_KEY') ? AUDIT_HMAC_KEY : '';

    if (strlen($key) < 32 || strpos($key, 'PUT_A_') === 0) {
        throw new RuntimeException('AUDIT_HMAC_KEY is missing or too short. See config/secrets.sample.php.');
    }

    return $key;
}

function auditTable(): string
{
    return '`' . AUDIT_DB . '`.`audit_log`';
}

/* ---------- Request context ---------- */

// The client's IP address. X-Forwarded-For is only believed when the request really
// comes from a proxy listed in TRUSTED_PROXIES; otherwise anyone could spoof it.
function clientIp(): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $trusted = defined('TRUSTED_PROXIES') ? TRUSTED_PROXIES : [];

    if ($remote !== '' && in_array($remote, $trusted, true)) {
        $forwarded = array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));

        // Walk from the right: the first address that isn't one of our proxies is the client.
        foreach (array_reverse($forwarded) as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP) && !in_array($address, $trusted, true)) {
                return $address;
            }
        }
    }

    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';
}

function requestId(): string
{
    static $id = null;

    return $id ??= bin2hex(random_bytes(16));
}

// Path plus the page name only. Query strings can carry reset or invite tokens.
function auditRequestPath(): string
{
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
    $page = $_GET['page'] ?? '';

    if (is_string($page) && preg_match('/^[a-z0-9-]{1,40}$/', $page)) {
        $path .= '?page=' . $page;
    }

    return substr($path, 0, 255);
}

function auditEmailLabel(string $email): string
{
    return mb_substr(strtolower(trim($email)), 0, 255);
}

function auditCurrentActor(): array
{
    return [
        'user_id' => (string) ($_SESSION['user_id'] ?? ''),
        'email' => (string) ($_SESSION['user_email'] ?? ''),
        'roles' => implode(',', (array) ($_SESSION['user_roles'] ?? [])),
    ];
}

/* ---------- Details: redact secrets and cap the size ---------- */

function auditSanitize($value, int $depth = 0)
{
    if ($depth > 4) {
        return '[truncated]';
    }

    if (is_array($value)) {
        $clean = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match('/pass|token|secret|hash|authorization|cookie/i', $key)) {
                $clean[$key] = '[redacted]';
            } else {
                $clean[$key] = auditSanitize($item, $depth + 1);
            }
        }

        return $clean;
    }

    if (is_string($value)) {
        return mb_substr($value, 0, 500);
    }

    return is_scalar($value) || $value === null ? $value : '[unsupported]';
}

function auditEncodeDetails(array $details): string
{
    if ($details === []) {
        return '{}';
    }

    $json = json_encode(
        auditSanitize($details),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
    );

    return ($json === false || strlen($json) > 8000) ? '{"truncated":true}' : $json;
}

/* ---------- Hash chain ---------- */

function auditComputeHash(array $row): string
{
    $payload = [];

    foreach (AUDIT_HASH_FIELDS as $field) {
        $payload[] = (string) $row[$field];
    }

    return hash_hmac(
        'sha256',
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        auditKey()
    );
}

/* ---------- Writer lock ---------- */

function auditLockState(?bool $set = null): bool
{
    static $held = false;

    if ($set !== null) {
        $held = $set;
    }

    return $held;
}

function auditAcquireLock(PDO $pdo): void
{
    $granted = (int) $pdo->query("SELECT GET_LOCK('iaims_audit_chain', 5)")->fetchColumn();

    if ($granted !== 1) {
        throw new RuntimeException('Could not obtain the audit chain lock.');
    }

    auditLockState(true);
}

function auditReleaseLock(PDO $pdo): void
{
    if (auditLockState()) {
        $pdo->query("SELECT RELEASE_LOCK('iaims_audit_chain')");
        auditLockState(false);
    }
}

/* ---------- Writing ---------- */

/**
 * Record an event. Throws if it can't be written.
 *
 * $context keys (all optional):
 *   actor              ['user_id' => , 'email' => , 'roles' => ]  default: the signed-in user
 *   session_record_id  default: the current session record
 *   target_type, target_id, target_label
 *   details            array of extra facts (secrets are redacted automatically)
 */
function auditLog(PDO $pdo, string $action, string $outcome = 'success', array $context = []): void
{
    auditKey(); // fail early if the key isn't configured

    // Outside auditedTransaction() the lock is taken and released here, so a row written
    // while a transaction is still open could be invisible to the next writer.
    $ownsLock = !auditLockState();

    if ($ownsLock && $pdo->inTransaction()) {
        throw new LogicException('Write audit entries inside auditedTransaction(), not a bare transaction.');
    }

    if ($ownsLock) {
        auditAcquireLock($pdo);
    }

    try {
        $actor = $context['actor'] ?? auditCurrentActor();

        $row = [
            'prev_hash' => '',
            'occurred_at' => date('Y-m-d H:i:s'),
            'actor_user_id' => substr((string) ($actor['user_id'] ?? ''), 0, 36),
            'actor_email' => substr((string) ($actor['email'] ?? ''), 0, 255),
            'actor_roles' => substr((string) ($actor['roles'] ?? ''), 0, 255),
            'session_record_id' => (int) ($context['session_record_id'] ?? ($_SESSION['session_record_id'] ?? 0)),
            'action' => substr($action, 0, 80),
            'outcome' => in_array($outcome, ['success', 'failure', 'denied'], true) ? $outcome : 'failure',
            'target_type' => substr((string) ($context['target_type'] ?? ''), 0, 40),
            'target_id' => substr((string) ($context['target_id'] ?? ''), 0, 64),
            'target_label' => substr((string) ($context['target_label'] ?? ''), 0, 255),
            'details' => auditEncodeDetails((array) ($context['details'] ?? [])),
            'ip_address' => clientIp(),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            'request_method' => substr((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 0, 10),
            'request_path' => auditRequestPath(),
            'request_id' => requestId(),
            'row_hash' => '',
        ];

        $previous = $pdo->query('SELECT row_hash FROM ' . auditTable() . ' ORDER BY audit_id DESC LIMIT 1')
            ->fetchColumn();

        $row['prev_hash'] = $previous !== false ? (string) $previous : auditGenesisHash();
        $row['row_hash'] = auditComputeHash($row);

        $columns = array_keys($row);
        $statement = $pdo->prepare(
            'INSERT INTO ' . auditTable() . ' (' . implode(', ', $columns) . ')
             VALUES (:' . implode(', :', $columns) . ')'
        );
        $statement->execute($row);
    } finally {
        if ($ownsLock) {
            auditReleaseLock($pdo);
        }
    }
}

// For security events (sign-ins, resets): a logging fault is reported but doesn't block the user.
function auditLogSafe(PDO $pdo, string $action, string $outcome = 'success', array $context = []): void
{
    try {
        auditLog($pdo, $action, $outcome, $context);
    } catch (Throwable $exception) {
        error_log('IAIMS audit write failed (' . $action . '): ' . $exception->getMessage());
    }
}

/**
 * Run a change and its audit entry as one unit. If the audit write fails, the change
 * is rolled back. Call auditLog() inside the callback.
 *
 * The lock is taken BEFORE the transaction starts so the transaction's snapshot already
 * includes every earlier entry, and it is held until after the commit.
 */
function auditedTransaction(PDO $pdo, callable $callback)
{
    if ($pdo->inTransaction()) {
        throw new LogicException('auditedTransaction() cannot be nested.');
    }

    auditAcquireLock($pdo);

    try {
        $pdo->beginTransaction();
        $result = $callback($pdo);
        $pdo->commit();

        return $result;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    } finally {
        auditReleaseLock($pdo);
    }
}

/* ---------- Verifying ---------- */

// Walks the whole chain. Detects edited rows, deleted rows and reordered rows.
function verifyAuditChain(PDO $pdo): array
{
    $expectedPrevious = auditGenesisHash();
    $lastId = 0;
    $checked = 0;

    $select = $pdo->prepare(
        'SELECT * FROM ' . auditTable() . ' WHERE audit_id > :last ORDER BY audit_id ASC LIMIT 1000'
    );

    while (true) {
        $select->execute(['last' => $lastId]);
        $rows = $select->fetchAll();

        if (!$rows) {
            break;
        }

        foreach ($rows as $row) {
            if (
                !hash_equals($expectedPrevious, (string) $row['prev_hash'])
                || !hash_equals(auditComputeHash($row), (string) $row['row_hash'])
            ) {
                return ['valid' => false, 'checked' => $checked, 'broken_at' => (int) $row['audit_id']];
            }

            $expectedPrevious = (string) $row['row_hash'];
            $lastId = (int) $row['audit_id'];
            $checked++;
        }
    }

    return ['valid' => true, 'checked' => $checked, 'broken_at' => 0];
}

// The newest entry's id and hash. Copy this somewhere the app can't write to: it is the
// only thing that exposes entries deleted from the END of the log.
function latestAuditAnchor(PDO $pdo): array
{
    $row = $pdo->query('SELECT audit_id, row_hash FROM ' . auditTable() . ' ORDER BY audit_id DESC LIMIT 1')
        ->fetch();

    return $row ? ['audit_id' => (int) $row['audit_id'], 'row_hash' => $row['row_hash']] : ['audit_id' => 0, 'row_hash' => ''];
}
