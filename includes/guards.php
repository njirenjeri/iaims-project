<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';

// Who is signed in and what they may see. Pages call these before doing anything else.

// The four staff roles. HR_ADMIN also holds the HR role, so it can do everything an officer can.
const STAFF_ROLES = [
    'ADMIN' => 'System admin',
    'HR_ADMIN' => 'HR admin',
    'HR' => 'HR officer',
    'SUPERVISOR' => 'Supervisor',
];

// The single role key that describes a set of role names (the most powerful one wins).
function staffRoleKey(array $roleNames): string
{
    foreach (array_keys(STAFF_ROLES) as $key) {
        if (in_array($key, $roleNames, true)) {
            return $key;
        }
    }

    return '';
}

// The role rows stored for a role key.
function staffRoleNames(string $roleKey): array
{
    return $roleKey === 'HR_ADMIN' ? ['HR', 'HR_ADMIN'] : [$roleKey];
}

function userHasRole(string $role): bool
{
    return in_array($role, (array) ($_SESSION['user_roles'] ?? []), true);
}

// Allow the page only if the signed-in user holds at least one of the roles. A refusal is logged.
function requireAnyRole(PDO $pdo, array $roles): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login');
    }

    foreach ($roles as $role) {
        if (userHasRole($role)) {
            return;
        }
    }

    auditLogSafe($pdo, 'access.denied', 'denied', ['details' => ['required_roles' => implode(',', $roles)]]);

    http_response_code(403);
    exit('Access denied.');
}

/* ---------- Department scope ---------- */

// The department an HR officer or HR admin works in:
// ['department_id', 'ministry_id', 'department_name', 'ministry_name'] (zeros and '' if none).
// The ministry follows from the department.
function currentHrDepartment(PDO $pdo): array
{
    static $department = null;

    if ($department !== null) {
        return $department;
    }

    $department = ['department_id' => 0, 'ministry_id' => 0, 'department_name' => '', 'ministry_name' => ''];

    if (userHasRole('HR') || userHasRole('HR_ADMIN')) {
        $statement = $pdo->prepare(
            'SELECT d.department_id, d.ministry_id, d.name AS department_name, m.name AS ministry_name
             FROM hr_assignments ha
             INNER JOIN departments d ON d.department_id = ha.department_id
             INNER JOIN ministries m ON m.ministry_id = d.ministry_id
             WHERE ha.user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => (string) ($_SESSION['user_id'] ?? '')]);
        $row = $statement->fetch();

        if ($row) {
            $department = [
                'department_id' => (int) $row['department_id'],
                'ministry_id' => (int) $row['ministry_id'],
                'department_name' => $row['department_name'],
                'ministry_name' => $row['ministry_name'],
            ];
        }
    }

    return $department;
}

// The slice of staff the signed-in user may manage: everyone (system admin) or one department
// (HR admin). all = false with department_id 0 means "no scope", so that user sees nothing.
function currentStaffScope(PDO $pdo): array
{
    static $scope = null;

    if ($scope !== null) {
        return $scope;
    }

    if (userHasRole('ADMIN')) {
        return $scope = ['all' => true, 'department_id' => 0, 'ministry_id' => 0];
    }

    $scope = ['all' => false, 'department_id' => 0, 'ministry_id' => 0];

    if (userHasRole('HR_ADMIN')) {
        $department = currentHrDepartment($pdo);
        $scope['department_id'] = $department['department_id'];
        $scope['ministry_id'] = $department['ministry_id'];
    }

    return $scope;
}

/* ---------- HR screens ---------- */

// Start of every HR page: the user must be an HR officer or HR admin with a department.
// Returns that department (see currentHrDepartment()).
function requireHrAccess(PDO $pdo): array
{
    requireAnyRole($pdo, ['HR', 'HR_ADMIN']);

    $department = currentHrDepartment($pdo);

    if ($department['department_id'] === 0) {
        http_response_code(403);
        exit('Your account has no department assigned. Please contact the system administrator.');
    }

    return $department;
}

// Who is acting on an HR screen, for permission checks and audit entries.
function hrActor(PDO $pdo): array
{
    $department = currentHrDepartment($pdo);

    return [
        'user_id' => (string) ($_SESSION['user_id'] ?? ''),
        'email' => (string) ($_SESSION['user_email'] ?? ''),
        'roles' => (array) ($_SESSION['user_roles'] ?? []),
        'department_id' => $department['department_id'],
        'ministry_id' => $department['ministry_id'],
    ];
}
