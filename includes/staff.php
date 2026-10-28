<?php

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/directory.php';

// Reading staff accounts and checking the details for a new one. Changes live in staff_actions.php.
// A "staff" user is anyone holding ADMIN, HR_ADMIN, HR or SUPERVISOR; students are not listed here.

const STAFF_STATUSES = ['invited', 'active', 'suspended', 'archived'];

const STAFF_FROM = "
    FROM users u
    LEFT JOIN staff_profiles sp ON sp.user_id = u.user_id
    LEFT JOIN hr_assignments ha ON ha.user_id = u.user_id
    LEFT JOIN supervisor_assignments sa ON sa.user_id = u.user_id
    LEFT JOIN departments d ON d.department_id = sa.department_id
    LEFT JOIN ministries m ON m.ministry_id = COALESCE(ha.ministry_id, d.ministry_id)
";

const STAFF_SELECT = "
    SELECT u.user_id, u.email, u.status,
           COALESCE(sp.first_name, '') AS first_name,
           COALESCE(sp.other_names, '') AS other_names,
           COALESCE(sp.phone_number, '') AS phone_number,
           (SELECT GROUP_CONCAT(r2.name) FROM user_roles ur2
             INNER JOIN roles r2 ON r2.role_id = ur2.role_id
             WHERE ur2.user_id = u.user_id) AS role_names,
           COALESCE(ha.ministry_id, d.ministry_id, 0) AS ministry_id,
           COALESCE(m.name, '') AS ministry_name,
           COALESCE(sa.department_id, 0) AS department_id,
           COALESCE(d.name, '') AS department_name,
           COALESCE((SELECT MAX(s.started_at) FROM user_sessions s WHERE s.user_id = u.user_id), '') AS last_sign_in
" . STAFF_FROM;

const STAFF_ONLY = "EXISTS (SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.role_id = ur.role_id
                            WHERE ur.user_id = u.user_id AND r.name IN ('ADMIN', 'HR_ADMIN', 'HR', 'SUPERVISOR'))";

/* ---------- Permissions that depend on who is looking ---------- */

// The roles the signed-in user may give to a new account.
function creatableRoles(array $scope): array
{
    if ($scope['all']) {
        return ['HR', 'HR_ADMIN', 'SUPERVISOR', 'ADMIN'];
    }

    return $scope['ministry_id'] > 0 ? ['HR', 'SUPERVISOR'] : [];
}

// May this person even see that account? (HR admins see their own ministry only.)
function canViewStaffTarget(array $actor, array $target): bool
{
    if ($actor['scope']['all']) {
        return true;
    }

    return $actor['scope']['ministry_id'] > 0 && $target['ministry_id'] === $actor['scope']['ministry_id'];
}

/* ---------- Listing ---------- */

function staffFiltersFromRequest(array $source, array $scope): array
{
    $text = static fn (string $key): string => is_string($source[$key] ?? null) ? trim($source[$key]) : '';
    $role = $text('role');
    $status = $text('status');
    $ministry = $source['ministry'] ?? 0;

    return [
        'q' => mb_substr($text('q'), 0, 100),
        'role' => array_key_exists($role, STAFF_ROLES) ? $role : '',
        'status' => in_array($status, STAFF_STATUSES, true) ? $status : '',
        'ministry' => ($scope['all'] && is_scalar($ministry) && ctype_digit((string) $ministry)) ? (int) $ministry : 0,
    ];
}

// The WHERE conditions for the staff list. Archived accounts only appear when asked for.
function staffWhere(array $scope, array $filters): array
{
    $where = [STAFF_ONLY];
    $params = [];

    if (!$scope['all']) {
        $where[] = 'COALESCE(ha.ministry_id, d.ministry_id, 0) = :scope_ministry';
        $params['scope_ministry'] = $scope['ministry_id'];
    }

    if (($filters['status'] ?? '') !== '') {
        $where[] = 'u.status = :status';
        $params['status'] = $filters['status'];
    } else {
        $where[] = "u.status <> 'archived'";
    }

    if (($filters['role'] ?? '') !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM user_roles ur3 INNER JOIN roles r3 ON r3.role_id = ur3.role_id
                            WHERE ur3.user_id = u.user_id AND r3.name = :role)';
        $params['role'] = $filters['role'];

        // HR admins also hold the HR role; the HR filter means officers only.
        if ($filters['role'] === 'HR') {
            $where[] = "NOT EXISTS (SELECT 1 FROM user_roles ur4 INNER JOIN roles r4 ON r4.role_id = ur4.role_id
                                    WHERE ur4.user_id = u.user_id AND r4.name = 'HR_ADMIN')";
        }
    }

    if (($filters['ministry'] ?? 0) > 0) {
        $where[] = 'COALESCE(ha.ministry_id, d.ministry_id, 0) = :ministry';
        $params['ministry'] = $filters['ministry'];
    }

    if (($filters['q'] ?? '') !== '') {
        $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
        $where[] = '(u.email LIKE :q1 OR sp.first_name LIKE :q2 OR sp.other_names LIKE :q3)';
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
    }

    return [$where, $params];
}

function hydrateStaffRow(array $row): array
{
    $row['roles'] = $row['role_names'] === null || $row['role_names'] === '' ? [] : explode(',', $row['role_names']);
    $row['role_key'] = staffRoleKey($row['roles']);
    $row['ministry_id'] = (int) $row['ministry_id'];
    $row['department_id'] = (int) $row['department_id'];

    return $row;
}

// ['rows' => [...], 'total' => n]
function listStaff(PDO $pdo, array $scope, array $filters, int $page, int $perPage): array
{
    [$where, $params] = staffWhere($scope, $filters);
    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $count = $pdo->prepare('SELECT COUNT(*) ' . STAFF_FROM . $whereSql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    // Both numbers are cast to integers, so they are safe to place in the query text.
    $offset = max(0, ($page - 1) * $perPage);
    $statement = $pdo->prepare(
        STAFF_SELECT . $whereSql . ' ORDER BY sp.first_name, u.email LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset
    );
    $statement->execute($params);

    return ['rows' => array_map('hydrateStaffRow', $statement->fetchAll()), 'total' => $total];
}

// Accounts per status within the scope, plus 'all' (everything except archived).
function staffStatusCounts(PDO $pdo, array $scope): array
{
    $where = [STAFF_ONLY];
    $params = [];

    if (!$scope['all']) {
        $where[] = 'COALESCE(ha.ministry_id, d.ministry_id, 0) = :scope_ministry';
        $params['scope_ministry'] = $scope['ministry_id'];
    }

    $statement = $pdo->prepare(
        'SELECT u.status, COUNT(*) AS total ' . STAFF_FROM . ' WHERE ' . implode(' AND ', $where) . ' GROUP BY u.status'
    );
    $statement->execute($params);

    $counts = array_fill_keys(STAFF_STATUSES, 0);

    foreach ($statement->fetchAll() as $row) {
        $counts[$row['status']] = (int) $row['total'];
    }

    $counts['all'] = $counts['invited'] + $counts['active'] + $counts['suspended'];

    return $counts;
}

/* ---------- One account ---------- */

function getStaffUser(PDO $pdo, string $userId): ?array
{
    $statement = $pdo->prepare(STAFF_SELECT . ' WHERE u.user_id = :user_id AND ' . STAFF_ONLY . ' LIMIT 1');
    $statement->execute(['user_id' => $userId]);
    $row = $statement->fetch();

    if (!$row) {
        return null;
    }

    $row = hydrateStaffRow($row);

    $invite = $pdo->prepare(
        "SELECT i.status, i.created_at, i.expires_at, COALESCE(c.email, '') AS created_by_email
         FROM user_invites i
         LEFT JOIN users c ON c.user_id = i.created_by
         WHERE i.user_id = :user_id
         ORDER BY i.invite_id DESC
         LIMIT 1"
    );
    $invite->execute(['user_id' => $userId]);
    $row['invite'] = $invite->fetch() ?: null;

    return $row;
}

function staffDisplayName(array $row): string
{
    $name = trim($row['first_name'] . ' ' . $row['other_names']);

    return $name !== '' ? $name : $row['email'];
}

// Recent audit entries done by this user or to this user, with the device used when known.
function staffRecentActivity(PDO $pdo, string $userId, int $limit = 8): array
{
    $statement = $pdo->prepare(
        'SELECT a.audit_id, a.occurred_at, a.actor_email, a.action, a.outcome, a.ip_address, a.target_label,
                COALESCE(s.browser, \'\') AS browser, COALESCE(s.os, \'\') AS os, COALESCE(s.device_type, \'\') AS device_type
         FROM ' . auditTable() . ' a
         LEFT JOIN user_sessions s ON s.session_record_id = a.session_record_id
         WHERE a.actor_user_id = :actor OR (a.target_type = \'user\' AND a.target_id = :target)
         ORDER BY a.audit_id DESC
         LIMIT ' . (int) $limit
    );
    $statement->execute(['actor' => $userId, 'target' => $userId]);

    return $statement->fetchAll();
}

/* ---------- Presentation helpers ---------- */

function staffStatusBadge(string $status): string
{
    $colours = ['invited' => 'warning', 'active' => 'success', 'suspended' => 'danger', 'archived' => 'secondary'];

    return sprintf(
        '<span class="badge text-bg-%s">%s</span>',
        $colours[$status] ?? 'secondary',
        escapeHtml(ucfirst($status))
    );
}

function staffRoleBadge(string $roleKey): string
{
    return '<span class="badge text-bg-light border">' . escapeHtml(STAFF_ROLES[$roleKey] ?? 'No role') . '</span>';
}

function staffScopeLabel(array $row): string
{
    if ($row['department_name'] !== '') {
        return $row['department_name'];
    }

    return $row['ministry_name'] !== '' ? $row['ministry_name'] : 'All ministries';
}

/* ---------- Checking the details for a new account or a role change ---------- */

function staffCleanPhone(string $phone): string
{
    return preg_replace('/[\s().-]/', '', $phone);
}

// Works out the ministry and department a role needs. Returns
// [ministryId, departmentId, ministryName, departmentName, errorMessage].
function resolveStaffScope(PDO $pdo, array $scope, string $role, int $ministryId, int $departmentId): array
{
    if ($role === 'ADMIN') {
        return [0, 0, '', '', ''];
    }

    // An HR admin can only act inside their own ministry, whatever the form says.
    if (!$scope['all']) {
        $ministryId = $scope['ministry_id'];
    }

    if ($role === 'SUPERVISOR') {
        $statement = $pdo->prepare(
            "SELECT d.department_id, d.name AS department_name, d.ministry_id, m.name AS ministry_name
             FROM departments d
             INNER JOIN ministries m ON m.ministry_id = d.ministry_id
             WHERE d.department_id = :id AND d.status = 'active' AND m.status = 'active'
             LIMIT 1"
        );
        $statement->execute(['id' => $departmentId]);
        $row = $statement->fetch();

        if (!$row) {
            return [0, 0, '', '', 'Select a state department.'];
        }

        if (!$scope['all'] && (int) $row['ministry_id'] !== $scope['ministry_id']) {
            return [0, 0, '', '', 'That department is outside your ministry.'];
        }

        return [(int) $row['ministry_id'], (int) $row['department_id'], $row['ministry_name'], $row['department_name'], ''];
    }

    // HR and HR admin work for a whole ministry.
    $statement = $pdo->prepare("SELECT ministry_id, name FROM ministries WHERE ministry_id = :id AND status = 'active' LIMIT 1");
    $statement->execute(['id' => $ministryId]);
    $row = $statement->fetch();

    if (!$row) {
        return [0, 0, '', '', 'Select a ministry.'];
    }

    return [(int) $row['ministry_id'], 0, $row['name'], '', ''];
}

// Returns [cleanValues, errorMessages] for the invite form.
function validateStaffInput(PDO $pdo, array $input, array $scope): array
{
    $text = static fn (string $key): string => is_string($input[$key] ?? null) ? trim(preg_replace('/\s+/', ' ', $input[$key])) : '';
    $number = static fn (string $key): int => (isset($input[$key]) && is_scalar($input[$key]) && ctype_digit((string) $input[$key]))
        ? (int) $input[$key]
        : 0;

    $clean = [
        'first_name' => $text('first_name'),
        'other_names' => $text('other_names'),
        'email' => strtolower($text('email')),
        'phone_number' => staffCleanPhone($text('phone_number')),
        'role' => $text('role'),
        'ministry_id' => $number('ministry_id'),
        'department_id' => $number('department_id'),
        'ministry_name' => '',
        'department_name' => '',
    ];

    $errors = [];

    if ($clean['first_name'] === '') {
        $errors['first_name'] = 'First name is required.';
    } elseif (mb_strlen($clean['first_name']) > 100) {
        $errors['first_name'] = 'First name must not exceed 100 characters.';
    }

    if (mb_strlen($clean['other_names']) > 150) {
        $errors['other_names'] = 'Other names must not exceed 150 characters.';
    }

    if (!filter_var($clean['email'], FILTER_VALIDATE_EMAIL) || strlen($clean['email']) > 255) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (!preg_match('/^\+?[0-9]{9,15}$/', $clean['phone_number'])) {
        $errors['phone_number'] = 'Enter a valid phone number.';
    }

    if (!in_array($clean['role'], creatableRoles($scope), true)) {
        $errors['role'] = 'Select a role you are allowed to assign.';
    } else {
        [$ministryId, $departmentId, $ministryName, $departmentName, $scopeError] =
            resolveStaffScope($pdo, $scope, $clean['role'], $clean['ministry_id'], $clean['department_id']);

        if ($scopeError !== '') {
            $errors['scope'] = $scopeError;
        } else {
            $clean['ministry_id'] = $ministryId;
            $clean['department_id'] = $departmentId;
            $clean['ministry_name'] = $ministryName;
            $clean['department_name'] = $departmentName;
        }
    }

    if (!isset($errors['email'])) {
        $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $exists->execute(['email' => $clean['email']]);

        if ($exists->fetchColumn()) {
            $errors['email'] = 'An account with that email already exists.';
        }
    }

    return [$clean, $errors];
}
