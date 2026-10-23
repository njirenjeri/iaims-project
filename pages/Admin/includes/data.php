<?php

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../includes/functions.php';

const CURRENT_MINISTRY = 'Ministry of Roads and Transport';

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function currentRole(): string {
    $roles = $_SESSION['user_roles'] ?? [];
    if (!is_array($roles)) return 'Admin';
    foreach ($roles as $role) {
        if (!is_string($role)) continue;
        $normalized = normalizedAdminRole($role);
        if (in_array($normalized, ['super admin', 'system admin', 'admin', 'administrator'], true)) return $role;
    }
    foreach ($roles as $role) {
        if (is_string($role) && in_array(normalizedAdminRole($role), ['hr admin', 'hr officer', 'supervisor'], true)) return $role;
    }
    return 'Admin';
}

function normalizedAdminRole(string $role): string {
    return strtolower(trim(str_replace(['_', '-'], ' ', $role)));
}

function adminRequireAccess(): void {
    $hasUserSession = !empty($_SESSION['user_id']);
    $roles = $_SESSION['user_roles'] ?? [];
    $allowed = ['admin', 'administrator', 'super admin', 'system admin', 'hr admin', 'hr officer', 'supervisor'];
    foreach (is_array($roles) ? $roles : [] as $role) {
        if ($hasUserSession && is_string($role) && in_array(normalizedAdminRole($role), $allowed, true)) {
            return;
        }
    }

    $_SESSION['auth_error'] = $hasUserSession
        ? 'Your account does not have permission to access the admin panel.'
        : 'Please sign in to access the admin panel.';
    header('Location: ' . BASE_URL . '/?page=login');
    exit;
}

function isSuperAdmin(): bool {
    $roles = $_SESSION['user_roles'] ?? [];
    foreach (is_array($roles) ? $roles : [] as $role) {
        if (is_string($role) && in_array(normalizedAdminRole($role), ['super admin', 'system admin', 'admin', 'administrator'], true)) {
            return true;
        }
    }
    return false;
}

function adminUrl(string $page, array $query = []): string {
    return BASE_URL . '/?page=' . rawurlencode($page)
        . ($query ? '&' . http_build_query($query) : '');
}

/** Roles the signed-in person may assign when inviting. */
function assignableRoles(): array {
    return isSuperAdmin() ? ['HR officer', 'HR admin', 'Supervisor', 'System admin']
                          : ['HR officer', 'Supervisor'];
}
function allRoles(): array { return ['HR officer', 'HR admin', 'Supervisor', 'System admin']; }

/** ministry => [state departments] */
function getMinistries(): array {
    $all = [
        'Ministry of Roads and Transport' => ['State Dept for Roads', 'State Dept for Transport'],
        'Ministry of Health'              => ['State Dept for Medical Services', 'State Dept for Public Health'],
    ];
    return isSuperAdmin() ? $all : [CURRENT_MINISTRY => $all[CURRENT_MINISTRY]];
}

/* ---------- Dashboard ---------- */
function getStats(): array {
    $users = getVisibleUsers();
    $count = static fn(string $status): int => count(array_filter($users, static fn($user) => ($user['status'] ?? '') === $status));
    return [
        ['id' => 'active',    'label' => 'Active staff',         'value' => $count('Active')],
        ['id' => 'pending',   'label' => 'Pending invites',      'value' => $count('Invited')],
        ['id' => 'suspended', 'label' => 'Suspended',            'value' => $count('Suspended')],
        ['id' => 'failed',    'label' => 'Failed sign-ins, 24h', 'value' => 7],
    ];
}

function getAttention(): array {
    return [
        ['title' => 'Pending invitations', 'meta' => 'Review invited accounts', 'action' => 'Review', 'link' => adminUrl('admin-users', ['status' => 'Invited'])],
        ['title' => 'Recent account activity', 'meta' => 'Review the latest changes', 'action' => 'Inspect', 'link' => adminUrl('admin-audit')],
        ['title' => 'Security settings', 'meta' => 'Review local demo controls', 'action' => 'Inspect', 'link' => adminUrl('admin-security')],
    ];
}

function getAuditEvents(): array {
    return array_map(static fn($event) => [
        'event' => $event['action'] . ': ' . $event['target'],
        'by' => $event['actor'],
        'when' => $event['time'],
        'status' => $event['outcome'],
    ], array_slice(getAuditLog(), 0, 4));
}

/* ---------- Users ---------- */
/** Totals for the filter chips (the sample list below shows only 5 of 26). */
function getUserCounts(): array {
    $counts = ['All' => 0, 'Active' => 0, 'Invited' => 0, 'Suspended' => 0, 'Archived' => 0];
    foreach (getVisibleUsers() as $user) {
        $counts['All']++;
        if (isset($counts[$user['status']])) $counts[$user['status']]++;
    }
    return $counts;
}

function adminSeedUsers(): array {
    $roads = 'Ministry of Roads and Transport';
    return [
        1 => ['id' => 1, 'name' => 'Grace Njeri',       'email' => 'g.njeri@roads.go.ke',    'role' => 'HR admin',   'ministry' => $roads,         'scope' => $roads,                   'status' => 'Active',    'note' => '',                   'last' => 'Today 09:12',
              'phone' => '+254 711 000 111', 'invited_by' => 'Super admin', 'invited_on' => '1 Sep', 'password_set' => '2 Sep'],
        2 => ['id' => 2, 'name' => 'John Kamau',        'email' => 'j.kamau@roads.go.ke',    'role' => 'HR officer', 'ministry' => $roads,         'scope' => $roads,                   'status' => 'Active',    'note' => '',                   'last' => 'Yesterday',
              'phone' => '+254 711 222 333', 'invited_by' => 'G. Njeri',    'invited_on' => '3 Sep', 'password_set' => '4 Sep', 'last_full' => 'Yesterday, 17:02'],
        3 => ['id' => 3, 'name' => 'Eng. Peter Njoroge', 'email' => 'p.njoroge@roads.go.ke', 'role' => 'Supervisor', 'ministry' => $roads,         'scope' => 'State Dept for Roads',   'status' => 'Active',    'note' => '',                   'last' => '12 Oct',
              'phone' => '+254 711 444 555', 'invited_by' => 'G. Njeri',    'invited_on' => '5 Sep', 'password_set' => '6 Sep'],
        4 => ['id' => 4, 'name' => 'Lucy Wekesa',       'email' => 'l.wekesa@roads.go.ke',   'role' => 'Supervisor', 'ministry' => $roads,         'scope' => 'State Dept for Transport', 'status' => 'Invited', 'note' => 'Expires in 2 days', 'last' => 'Never',
              'phone' => '+254 722 000 111', 'invited_by' => 'G. Njeri',    'invited_on' => '19 Oct', 'password_set' => '—'],
        5 => ['id' => 5, 'name' => 'Daniel Mutiso',     'email' => 'd.mutiso@health.go.ke',  'role' => 'HR officer', 'ministry' => 'Ministry of Health', 'scope' => 'Ministry of Health', 'status' => 'Suspended', 'note' => '',                   'last' => '2 Oct',
              'phone' => '+254 733 666 777', 'invited_by' => 'Super admin', 'invited_on' => '10 Aug', 'password_set' => '11 Aug'],
    ];
}

/** HR admins only see their own ministry. */
function adminSeedAuditLog(): array {
    $dev = ['Browser' => 'Chrome 130', 'System' => 'Windows 11, desktop', 'Screen' => '1920 x 1080',
            'Timezone, language' => 'Africa/Nairobi, en-KE', 'IP' => '41.89.x.x', 'Device ID' => 'a91f...3c2e (known)'];
    return [
        ['date' => '2026-10-19', 'time' => '19 Oct 09:14', 'actor' => 'G. Njeri', 'actor_role' => 'HR admin', 'action' => 'user.invited', 'target' => 'L. Wekesa', 'outcome' => 'Success',
         'changes' => ['status' => 'none to invited', 'Role' => 'Supervisor', 'Scope' => 'State Dept for Transport'], 'device' => $dev],
        ['date' => '2026-10-19', 'time' => '19 Oct 08:52', 'actor' => 'J. Kamau', 'actor_role' => 'HR officer', 'action' => 'user.role_changed', 'target' => 'G. Njeri', 'outcome' => 'Denied',
         'changes' => ['role' => 'HR admin to System admin', 'Reason' => 'HR officers cannot change roles'], 'device' => array_merge($dev, ['IP' => '41.89.x.y'])],
        ['date' => '2026-10-18', 'time' => '18 Oct 16:30', 'actor' => 'Super admin', 'actor_role' => '', 'action' => 'user.password_reset_issued', 'target' => 'D. Mutiso', 'outcome' => 'Success',
         'changes' => ['Reset link' => 'issued', 'Sessions ended' => '0'], 'device' => $dev],
        ['date' => '2026-10-18', 'time' => '18 Oct 16:28', 'actor' => 'Super admin', 'actor_role' => '', 'action' => 'user.suspended', 'target' => 'D. Mutiso', 'outcome' => 'Success',
         'changes' => ['status' => 'active to suspended', 'Reason' => 'Left the ministry', 'Sessions ended' => '2'], 'device' => $dev],
    ];
}

function adminDemoState(): array {
    static $state;
    if ($state !== null) return $state;
    $path = STORAGE_PATH . '/admin-demo.json';
    if (!is_file($path)) {
        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            throw new RuntimeException('Unable to create local admin storage directory.');
        }
        $state = [
            'users' => adminSeedUsers(),
            'audit' => adminSeedAuditLog(),
            'invites' => [],
            'settings' => ['session_timeout' => 30, 'require_mfa' => false, 'maintenance_mode' => false],
            'security' => ['lockout_threshold' => 5, 'lockout_minutes' => 15, 'password_min_length' => 12],
        ];
        adminSaveDemoState($state);
        return $state;
    }
    $contents = file_get_contents($path);
    if ($contents === false) throw new RuntimeException('Unable to read local admin storage.');
    $decoded = json_decode($contents, true);
    if (!is_array($decoded) || !isset($decoded['users'], $decoded['audit'], $decoded['invites'], $decoded['settings'], $decoded['security'])) {
        throw new RuntimeException('Local admin storage is invalid. Remove storage/admin-demo.json to restore the demo data.');
    }
    $state = $decoded;
    return $state;
}

function adminSaveDemoState(array $state): void {
    $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents(STORAGE_PATH . '/admin-demo.json', $encoded . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to save local admin changes.');
    }
}

function getUsers(): array {
    return adminDemoState()['users'];
}

function getVisibleUsers(): array {
    return array_filter(getUsers(), fn($u) => isSuperAdmin() || $u['ministry'] === CURRENT_MINISTRY);
}

function getUser(int $id): ?array { return getVisibleUsers()[$id] ?? null; }

function initials(string $name): string {
    $parts = preg_split('/\s+/', trim(preg_replace('/^(Eng\.|Dr\.|Prof\.)\s*/', '', $name)));
    return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
}

function getUserActivity(int $id): array {
    if ($id !== 2) {
        return [['title' => 'Account created and invited', 'meta' => 'By G. Njeri (HR admin)', 'when' => '3 Sep 14:20', 'current' => false]];
    }
    return [
        ['title' => 'Signed in',                         'meta' => 'Chrome 130, Windows 11, desktop · 41.89.x.x', 'when' => 'Yesterday 17:02', 'current' => true],
        ['title' => 'Decision saved: application shortlisted', 'meta' => 'Same device',                           'when' => 'Yesterday 17:10', 'current' => false],
        ['title' => 'Signed in from a new device',       'meta' => 'Safari, iPhone, mobile · 105.163.x.x',        'when' => '14 Oct 08:31',    'current' => false],
        ['title' => 'Password set from invite link',     'meta' => 'Chrome 130, Windows 11',                      'when' => '4 Sep 10:15',     'current' => false],
        ['title' => 'Account created and invited',       'meta' => 'By G. Njeri (HR admin)',                      'when' => '3 Sep 14:20',     'current' => false],
    ];
}

/* ---------- Audit log ---------- */
function getAuditLog(): array {
    return adminDemoState()['audit'];
}
