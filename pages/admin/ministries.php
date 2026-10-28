<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/guards.php';
require_once __DIR__ . '/../../includes/directory.php';
require_once __DIR__ . '/../../includes/layout.php';

requireAnyRole($pdo, ['ADMIN']);

$showArchived = isset($_GET['archived']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $id = isset($_POST['id']) && ctype_digit((string) $_POST['id']) ? (int) $_POST['id'] : 0;
    $name = is_string($_POST['name'] ?? null) ? $_POST['name'] : '';
    $status = ($_POST['status'] ?? '') === 'archived' ? 'archived' : 'active';

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $result = ['success' => false, 'message' => 'Your session has expired. Please refresh the page and try again.'];
    } else {
        switch ($action) {
            case 'ministry_create':
                $result = createMinistry($pdo, $name);
                break;
            case 'ministry_rename':
                $result = renameMinistry($pdo, $id, $name);
                break;
            case 'ministry_status':
                $result = setMinistryStatus($pdo, $id, $status);
                break;
            case 'department_create':
                $result = createDepartment($pdo, $id, $name);
                break;
            case 'department_rename':
                $result = renameDepartment($pdo, $id, $name);
                break;
            case 'department_status':
                $result = setDepartmentStatus($pdo, $id, $status);
                break;
            default:
                $result = ['success' => false, 'message' => 'Invalid request.'];
        }
    }

    setFlash($result['success'] ? 'success' : 'danger', $result['message']);
    redirect('ministries' . ($showArchived ? '&archived=1' : ''));
}

$ministries = listMinistries($pdo, $showArchived);
$departments = listDepartmentsByMinistry($pdo, $showArchived);
$csrf = csrfToken();

staffPageStart('Ministries and departments', 'ministries');
?>
<?= renderFlash() ?>

<div class="profile-heading mb-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
        <p class="text-uppercase text-primary fw-semibold small mb-2">Directory</p>
        <h1 class="h2 fw-bold mb-2 text-capitalize">Ministries & departments</h1>
        <p class="text-secondary mb-0">Students and staff choose from these. Archiving hides one without losing its history.</p>
    </div>
    <a class="btn btn-sm btn-outline-secondary"
       href="<?= escapeHtml($showArchived ? urlWith('ministries') : urlWith('ministries', ['archived' => 1])) ?>">
        <?= $showArchived ? 'Hide archived' : 'Show archived' ?>
    </a>
</div>

<form method="post" action="<?= escapeHtml(urlWith('ministries', $showArchived ? ['archived' => 1] : [])) ?>" class="card profile-card mb-4">
    <div class="card-body d-flex flex-column flex-sm-row gap-2">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrf) ?>">
        <input type="hidden" name="action" value="ministry_create">
        <input type="text" class="form-control" name="name" maxlength="191" placeholder="New ministry name" aria-label="New ministry name" required>
        <button type="submit" class="btn btn-profile-save flex-shrink-0"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add ministry</button>
    </div>
</form>

<?php if (!$ministries): ?>
    <section class="card profile-card"><p class="text-secondary p-4 mb-0">No ministries yet. Add one above, or run scripts/seed_ministries.php to import the standard list.</p></section>
<?php endif; ?>

<?php foreach ($ministries as $ministry): ?>
    <?php
    $ministryId = (int) $ministry['ministry_id'];
    $archived = $ministry['status'] === 'archived';
    ?>
    <section class="card profile-card mb-4 <?= $archived ? 'opacity-75' : '' ?>" aria-labelledby="ministry-<?= $ministryId ?>">
        <div class="card-header profile-card-header flex-wrap">
            <span class="section-icon"><i class="bi bi-building" aria-hidden="true"></i></span>
            <div>
                <h2 class="h5 mb-1" id="ministry-<?= $ministryId ?>">
                    <?= escapeHtml($ministry['name']) ?>
                    <?php if ($archived): ?><span class="badge text-bg-secondary ms-1">Archived</span><?php endif; ?>
                </h2>
                <p class="small text-secondary mb-0">
                    <?= (int) $ministry['department_count'] ?> active department(s) · <?= (int) $ministry['staff_count'] ?> staff account(s)
                </p>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#renameModal"
                        data-action="ministry_rename" data-id="<?= $ministryId ?>" data-name="<?= escapeHtml($ministry['name']) ?>" data-title="Rename ministry">
                    Rename
                </button>
                <form method="post" action="<?= escapeHtml(urlWith('ministries', $showArchived ? ['archived' => 1] : [])) ?>"
                      data-confirm="<?= $archived ? 'Restore this ministry?' : 'Archive this ministry? It will be hidden from dropdowns.' ?>">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrf) ?>">
                    <input type="hidden" name="action" value="ministry_status">
                    <input type="hidden" name="id" value="<?= $ministryId ?>">
                    <input type="hidden" name="status" value="<?= $archived ? 'active' : 'archived' ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?= $archived ? 'Restore' : 'Archive' ?></button>
                </form>
            </div>
        </div>

        <ul class="list-group list-group-flush">
            <?php foreach ($departments[$ministryId] ?? [] as $department): ?>
                <?php
                $departmentId = (int) $department['department_id'];
                $departmentArchived = $department['status'] === 'archived';
                ?>
                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2 py-3 <?= $departmentArchived ? 'opacity-75' : '' ?>">
                    <div>
                        <?= escapeHtml($department['name']) ?>
                        <?php if ($departmentArchived): ?><span class="badge text-bg-secondary ms-1">Archived</span><?php endif; ?>
                        <div class="small text-secondary"><?= (int) $department['staff_count'] ?> supervisor(s)</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#renameModal"
                                data-action="department_rename" data-id="<?= $departmentId ?>" data-name="<?= escapeHtml($department['name']) ?>" data-title="Rename department">
                            Rename
                        </button>
                        <form method="post" action="<?= escapeHtml(urlWith('ministries', $showArchived ? ['archived' => 1] : [])) ?>"
                              data-confirm="<?= $departmentArchived ? 'Restore this department?' : 'Archive this department? It will be hidden from dropdowns.' ?>">
                            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrf) ?>">
                            <input type="hidden" name="action" value="department_status">
                            <input type="hidden" name="id" value="<?= $departmentId ?>">
                            <input type="hidden" name="status" value="<?= $departmentArchived ? 'active' : 'archived' ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary"><?= $departmentArchived ? 'Restore' : 'Archive' ?></button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>

            <?php if (!$archived): ?>
                <li class="list-group-item py-3">
                    <form method="post" action="<?= escapeHtml(urlWith('ministries', $showArchived ? ['archived' => 1] : [])) ?>" class="d-flex flex-column flex-sm-row gap-2">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrf) ?>">
                        <input type="hidden" name="action" value="department_create">
                        <input type="hidden" name="id" value="<?= $ministryId ?>">
                        <input type="text" class="form-control form-control-sm" name="name" maxlength="191" placeholder="Add a state department" aria-label="Add a state department to <?= escapeHtml($ministry['name']) ?>" required>
                        <button type="submit" class="btn btn-sm btn-outline-secondary flex-shrink-0">Add department</button>
                    </form>
                </li>
            <?php endif; ?>
        </ul>
    </section>
<?php endforeach; ?>

<div class="modal fade" id="renameModal" tabindex="-1" aria-labelledby="renameModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= escapeHtml(urlWith('ministries', $showArchived ? ['archived' => 1] : [])) ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrf) ?>">
            <input type="hidden" name="action" value="">
            <input type="hidden" name="id" value="">
            <div class="modal-header">
                <h2 class="modal-title h5" id="renameModalTitle" data-modal-title>Rename</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="renameName" class="form-label">Name</label>
                <input type="text" class="form-control" id="renameName" name="name" maxlength="191" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
<?php staffPageEnd(['admin-users.js']); ?>
