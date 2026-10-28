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

// The slice of staff the signed-in user may manage: everyone (system admin) or one ministry
// (HR admin). all = false with ministry_id 0 means "no scope", so that user sees nothing.
function currentStaffScope(PDO $pdo): array
{
    static $scope = null;

    if ($scope !== null) {
        return $scope;
    }

    if (userHasRole('ADMIN')) {
        return $scope = ['all' => true, 'ministry_id' => 0];
    }

    $ministryId = 0;

    if (userHasRole('HR_ADMIN')) {
        $statement = $pdo->prepare('SELECT ministry_id FROM hr_assignments WHERE user_id = :user_id LIMIT 1');
        $statement->execute(['user_id' => (string) ($_SESSION['user_id'] ?? '')]);
        $ministryId = (int) $statement->fetchColumn();
    }

    return $scope = ['all' => false, 'ministry_id' => $ministryId];
}
