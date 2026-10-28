<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/guards.php';
require_once __DIR__ . '/../../includes/staff.php';
require_once __DIR__ . '/../../includes/layout.php';

requireAnyRole($pdo, ['ADMIN', 'HR_ADMIN']);

$scope = currentStaffScope($pdo);

if (!$scope['all'] && $scope['ministry_id'] === 0) {
    http_response_code(403);
    exit('Your account has no ministry assigned. Please contact the system administrator.');
}

$perPage = 25;
$page = max(1, (int) ($_GET['p'] ?? 1));
$filters = staffFiltersFromRequest($_GET, $scope);

$result = listStaff($pdo, $scope, $filters, $page, $perPage);
$counts = staffStatusCounts($pdo, $scope);
$ministries = $scope['all'] ? listMinistries($pdo, false) : [];
$scopeName = $scope['all'] ? 'All ministries' : getMinistryName($pdo, $scope['ministry_id']);

$chips = [
    ['', 'All', $counts['all']],
    ['active', 'Active', $counts['active']],
    ['invited', 'Invited', $counts['invited']],
    ['suspended', 'Suspended', $counts['suspended']],
    ['archived', 'Archived', $counts['archived']],
];

// Filters carried into links (the page number is added by the pagination).
$linkFilters = $filters;

staffPageStart('Users', 'users');
?>
<?= renderFlash() ?>

<div class="profile-heading mb-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
        <p class="text-uppercase text-primary fw-semibold small mb-2"><?= escapeHtml($scopeName) ?></p>
        <h1 class="h2 fw-bold mb-2">Users</h1>
        <p class="text-secondary mb-0">
            <?= $scope['all'] ? 'Every staff account in the system.' : 'Staff accounts in your ministry.' ?>
        </p>
    </div>
    <a class="btn btn-profile-save" href="<?= escapeHtml(urlWith('users-invite')) ?>">
        <i class="bi bi-person-plus me-2" aria-hidden="true"></i>Invite user
    </a>
</div>

<form method="get" action="<?= escapeHtml(BASE_URL) ?>/" class="row g-2 mb-3">
    <input type="hidden" name="page" value="users">
    <input type="hidden" name="status" value="<?= escapeHtml($filters['status']) ?>">

    <div class="col-12 col-md-<?= $scope['all'] ? '5' : '8' ?>">
        <input type="search" class="form-control" name="q" value="<?= escapeHtml($filters['q']) ?>"
               placeholder="Search name or email" aria-label="Search name or email">
    </div>
    <div class="col-6 col-md-<?= $scope['all'] ? '2' : '2' ?>">
        <select class="form-select" name="role" aria-label="Role">
            <option value="">All roles</option>
            <?php foreach (STAFF_ROLES as $key => $label): ?>
                <?php if (!$scope['all'] && in_array($key, ['ADMIN'], true)): continue; endif; ?>
                <option value="<?= escapeHtml($key) ?>" <?= $filters['role'] === $key ? 'selected' : '' ?>><?= escapeHtml($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($scope['all']): ?>
        <div class="col-6 col-md-3">
            <select class="form-select" name="ministry" aria-label="Ministry">
                <option value="">All ministries</option>
                <?php foreach ($ministries as $ministry): ?>
                    <option value="<?= (int) $ministry['ministry_id'] ?>" <?= $filters['ministry'] === (int) $ministry['ministry_id'] ? 'selected' : '' ?>>
                        <?= escapeHtml($ministry['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="col-12 col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-secondary flex-fill">Filter</button>
        <a class="btn btn-link link-gold text-decoration-none" href="<?= escapeHtml(urlWith('users')) ?>">Clear</a>
    </div>
</form>

<div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Filter by status">
    <?php foreach ($chips as [$value, $label, $count]): ?>
        <a class="btn btn-sm <?= $filters['status'] === $value ? 'btn-primary' : 'btn-outline-secondary' ?>"
           href="<?= escapeHtml(urlWith('users', ['status' => $value] + $linkFilters)) ?>">
            <?= escapeHtml($label) ?> <span class="opacity-75"><?= (int) $count ?></span>
        </a>
    <?php endforeach; ?>
</div>

<section class="card profile-card" aria-label="Staff accounts">
    <?php if (!$result['rows']): ?>
        <div class="text-center p-4 p-md-5">
            <h2 class="h6 fw-semibold">No users found</h2>
            <p class="text-secondary small mb-0">Try different filters, or invite a new user.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>User</th><th>Role</th><th>Scope</th><th>Status</th><th>Last sign-in</th></tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $row): ?>
                    <tr>
                        <td>
                            <a class="fw-semibold text-decoration-none" href="<?= escapeHtml(urlWith('users-view', ['id' => $row['user_id']])) ?>">
                                <?= escapeHtml(staffDisplayName($row)) ?>
                            </a>
                            <div class="small text-secondary"><?= escapeHtml($row['email']) ?></div>
                        </td>
                        <td><?= staffRoleBadge($row['role_key']) ?></td>
                        <td class="small"><?= escapeHtml(staffScopeLabel($row)) ?></td>
                        <td><?= staffStatusBadge($row['status']) ?></td>
                        <td class="small text-nowrap"><?= escapeHtml(formatWhen($row['last_sign_in'], 'j M Y, H:i') ?: 'Never') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-top">
            <span class="small text-secondary">
                Showing <?= ($page - 1) * $perPage + 1 ?> to <?= min($page * $perPage, $result['total']) ?> of <?= (int) $result['total'] ?>
            </span>
            <?= renderPagination('users', $linkFilters, $page, $result['total'], $perPage) ?>
        </div>
    <?php endif; ?>
</section>
<?php staffPageEnd(); ?>
