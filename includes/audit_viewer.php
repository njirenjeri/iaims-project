<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/guards.php';

// Reading the audit log for the admin screens. This file only ever reads: nothing in the
// application edits or deletes audit entries.

const AUDIT_OUTCOMES = ['success', 'failure', 'denied'];
const AUDIT_PAGE_SIZE = 50;
const AUDIT_EXPORT_LIMIT = 20000;
const AUDIT_CHAIN_CACHE_SECONDS = 600;

/* ---------- Wording ---------- */

function auditActionLabel(string $action): string
{
    static $labels = [
        'auth.login_success' => 'Signed in',
        'auth.login_failed' => 'Failed sign-in',
        'auth.logout' => 'Signed out',
        'auth.new_device_login' => 'Signed in from a new device',
        'auth.registered' => 'Student registered',
        'auth.register_failed' => 'Registration failed',
        'auth.password_reset_requested' => 'Password reset requested',
        'auth.password_reset_completed' => 'Password reset completed',
        'auth.password_reset_failed' => 'Password reset failed',
        'auth.session_rejected' => 'Session rejected',
        'access.denied' => 'Access denied',
        'user.invited' => 'User invited',
        'user.invite_resent' => 'New invite generated',
        'invite.accepted' => 'Invite accepted, password set',
        'user.suspended' => 'Account suspended',
        'user.reactivated' => 'Account reactivated',
        'user.archived' => 'Account archived',
        'user.restored' => 'Account restored',
        'user.role_changed' => 'Role changed',
        'user.password_reset_forced' => 'Password reset forced',
        'user.action_denied' => 'Action refused',
        'ministry.created' => 'Ministry added',
        'ministry.renamed' => 'Ministry renamed',
        'ministry.archived' => 'Ministry archived',
        'ministry.restored' => 'Ministry restored',
        'ministry.archive_blocked' => 'Ministry archive refused',
        'department.created' => 'Department added',
        'department.renamed' => 'Department renamed',
        'department.archived' => 'Department archived',
        'department.restored' => 'Department restored',
        'department.archive_blocked' => 'Department archive refused',
        'audit.viewed' => 'Audit log viewed',
        'audit.exported' => 'Audit log exported',
        'admin.first_admin_created' => 'First system admin created',
        'admin.directory_seeded' => 'Ministries imported',
        'system.audit_self_test' => 'Audit self-test',
    ];

    return $labels[$action] ?? ucfirst(str_replace(['.', '_'], ' ', $action));
}

function auditOutcomeBadge(string $outcome): string
{
    $colours = ['success' => 'success', 'failure' => 'danger', 'denied' => 'warning'];

    return sprintf(
        '<span class="badge text-bg-%s">%s</span>',
        $colours[$outcome] ?? 'secondary',
        escapeHtml(ucfirst($outcome))
    );
}

/* ---------- Filters ---------- */

function auditFiltersFromRequest(array $source): array
{
    $text = static fn (string $key): string => is_string($source[$key] ?? null) ? trim($source[$key]) : '';
    $date = static function (string $value): string {
        $parsed = DateTime::createFromFormat('Y-m-d', $value);

        return ($parsed && $parsed->format('Y-m-d') === $value) ? $value : '';
    };

    $action = $text('action');
    $outcome = $text('outcome');
    $role = $text('role');

    return [
        'q' => mb_substr($text('q'), 0, 100),
        'action' => preg_match('/^[a-z0-9._]{1,80}$/', $action) ? $action : '',
        'outcome' => in_array($outcome, AUDIT_OUTCOMES, true) ? $outcome : '',
        'role' => ($role === 'STUDENT' || array_key_exists($role, STAFF_ROLES)) ? $role : '',
        'from' => $date($text('from')),
        'to' => $date($text('to')),
    ];
}

function auditWhere(array $filters): array
{
    $where = ['1 = 1'];
    $params = [];

    if ($filters['q'] !== '') {
        $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
        $where[] = '(a.actor_email LIKE :q1 OR a.target_label LIKE :q2 OR a.ip_address LIKE :q3 OR a.actor_user_id = :q4)';
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $filters['q']];
    }

    if ($filters['action'] !== '') {
        $where[] = 'a.action = :action';
        $params['action'] = $filters['action'];
    }

    if ($filters['outcome'] !== '') {
        $where[] = 'a.outcome = :outcome';
        $params['outcome'] = $filters['outcome'];
    }

    if ($filters['role'] !== '') {
        $where[] = 'FIND_IN_SET(:role, a.actor_roles) > 0';
        $params['role'] = $filters['role'];
    }

    if ($filters['from'] !== '') {
        $where[] = 'a.occurred_at >= :from_at';
        $params['from_at'] = $filters['from'] . ' 00:00:00';
    }

    if ($filters['to'] !== '') {
        $where[] = 'a.occurred_at <= :to_at';
        $params['to_at'] = $filters['to'] . ' 23:59:59';
    }

    return [implode(' AND ', $where), $params];
}

/* ---------- Reading ---------- */

// ['rows' => [...], 'total' => n], newest first.
function searchAuditLog(PDO $pdo, array $filters, int $page, int $perPage): array
{
    [$whereSql, $params] = auditWhere($filters);

    $count = $pdo->prepare('SELECT COUNT(*) FROM ' . auditTable() . ' a WHERE ' . $whereSql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $offset = max(0, ($page - 1) * $perPage);
    $statement = $pdo->prepare(
        'SELECT a.* FROM ' . auditTable() . ' a WHERE ' . $whereSql
        . ' ORDER BY a.audit_id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset
    );
    $statement->execute($params);

    return ['rows' => $statement->fetchAll(), 'total' => $total];
}

function auditActionOptions(PDO $pdo): array
{
    return array_column($pdo->query('SELECT DISTINCT action FROM ' . auditTable() . ' ORDER BY action')->fetchAll(), 'action');
}

// Device details for the sessions behind a page of entries, keyed by session record id.
function auditSessionDetails(PDO $pdo, array $sessionIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $sessionIds))));

    if (!$ids) {
        return [];
    }

    $statement = $pdo->prepare(
        'SELECT session_record_id, browser, browser_version, os, device_type, screen, timezone, language,
                device_hash, is_new_device, started_at
         FROM user_sessions
         WHERE session_record_id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ')'
    );
    $statement->execute($ids);

    $details = [];

    foreach ($statement->fetchAll() as $row) {
        $details[(int) $row['session_record_id']] = $row;
    }

    return $details;
}

/* ---------- Chain status (cached briefly because it reads the whole log) ---------- */

function auditChainStatus(PDO $pdo, bool $force): array
{
    $cached = $_SESSION['audit_chain'] ?? null;

    if (!$force && is_array($cached) && (time() - (int) $cached['at']) < AUDIT_CHAIN_CACHE_SECONDS) {
        return $cached;
    }

    $status = ['at' => time(), 'result' => verifyAuditChain($pdo), 'anchor' => latestAuditAnchor($pdo)];
    $_SESSION['audit_chain'] = $status;

    return $status;
}

/* ---------- CSV export ---------- */

// Spreadsheet programs run cells that start with = + - @ as formulas, so those get a leading quote.
function auditCsvCell($value): string
{
    $value = (string) $value;

    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

// Writes the filtered log as a CSV download (newest first, capped). Call before any output.
function streamAuditCsv(PDO $pdo, array $filters): int
{
    [$whereSql, $params] = auditWhere($filters);

    $statement = $pdo->prepare(
        'SELECT a.* FROM ' . auditTable() . ' a WHERE ' . $whereSql
        . ' ORDER BY a.audit_id DESC LIMIT ' . (int) AUDIT_EXPORT_LIMIT
    );
    $statement->execute($params);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="audit-log-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // lets Excel read UTF-8
    fputcsv($out, ['audit_id', 'occurred_at', 'actor_email', 'actor_roles', 'action', 'outcome', 'target_type',
        'target_id', 'target_label', 'ip_address', 'user_agent', 'request_path', 'details']);

    $rows = 0;

    while ($row = $statement->fetch()) {
        fputcsv($out, array_map('auditCsvCell', [
            $row['audit_id'], $row['occurred_at'], $row['actor_email'], $row['actor_roles'], $row['action'],
            $row['outcome'], $row['target_type'], $row['target_id'], $row['target_label'], $row['ip_address'],
            $row['user_agent'], $row['request_path'], $row['details'],
        ]));
        $rows++;
    }

    fclose($out);

    return $rows;
}
