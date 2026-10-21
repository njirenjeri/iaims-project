<?php
$active = 'users'; $pageTitle = 'Users';
require __DIR__ . '/../includes/header.php';

$users      = getVisibleUsers();
$counts     = getUserCounts();
$initial    = $_GET['status'] ?? 'All';
if (!isset($counts[$initial])) $initial = 'All';
$statusCls  = ['Active' => 'status-ok', 'Invited' => 'status-invited', 'Suspended' => 'status-suspended', 'Archived' => 'status-muted'];
?>
<header class="page-head">
  <div class="d-flex gap-3 align-items-start">
    <button class="btn btn-menu d-lg-none" id="menuBtn" aria-label="Open menu"><i class="bi bi-list"></i></button>
    <div>
      <h1 class="h4 mb-0">Users</h1>
      <p class="text-muted small mb-0">
        <?= isSuperAdmin() ? 'Super admin view: all ministries. An HR admin sees only their own ministry.'
                           : 'Showing users in ' . h(CURRENT_MINISTRY) . '.' ?>
      </p>
    </div>
  </div>
  <a class="btn btn-outline-gold" href="<?= h(adminUrl('admin-invite-page')) ?>"><i class="bi bi-person-plus"></i> Invite user</a>
</header>

<div class="row g-2 mb-3">
  <div class="col-12 col-md-6">
    <input class="form-control" id="userSearch" type="search" placeholder="Search name or email" aria-label="Search name or email">
  </div>
  <div class="col-6 col-md-3">
    <select class="form-select" id="roleFilter" aria-label="Filter by role">
      <option value="">All roles</option>
      <?php foreach (allRoles() as $r): ?><option><?= h($r) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-3">
    <select class="form-select" id="ministryFilter" aria-label="Filter by ministry">
      <option value="">All ministries</option>
      <?php foreach (array_keys(getMinistries()) as $m): ?><option><?= h($m) ?></option><?php endforeach; ?>
    </select>
  </div>
</div>

<div class="chips mb-3" id="statusChips" role="group" aria-label="Filter by status">
  <?php foreach ($counts as $label => $n): ?>
    <button type="button" class="chip <?= $label === $initial ? 'active' : '' ?>" data-status="<?= $label === 'All' ? '' : h($label) ?>">
      <?= h($label) ?> <?= (int)$n ?>
    </button>
  <?php endforeach; ?>
</div>

<div class="panel p-0">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 data-table" id="usersTable">
      <thead>
        <tr><th>User</th><th>Role</th><th>Scope</th><th>Status</th><th>Last sign-in</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr data-name="<?= h(strtolower($u['name'] . ' ' . $u['email'])) ?>"
              data-role="<?= h($u['role']) ?>" data-ministry="<?= h($u['ministry']) ?>" data-status="<?= h($u['status']) ?>">
            <td>
              <a class="link-name" href="<?= h(adminUrl('admin-user', ['id' => (int)$u['id']])) ?>"><?= h($u['name']) ?></a>
              <div class="text-muted small"><?= h($u['email']) ?></div>
            </td>
            <td><?= h($u['role']) ?></td>
            <td><?= h($u['scope']) ?></td>
            <td>
              <span class="status <?= $statusCls[$u['status']] ?? 'status-muted' ?>"><?= h($u['status']) ?></span>
              <?php if ($u['note']): ?><div class="text-muted small mt-1"><?= h($u['note']) ?></div><?php endif; ?>
            </td>
            <td><?= h($u['last']) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr id="noResults" hidden><td colspan="5" class="text-center text-muted py-4">No users match these filters. Clear the search or choose All.</td></tr>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
