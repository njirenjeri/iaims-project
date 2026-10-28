<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/staff_actions.php';
require_once __DIR__ . '/../../includes/audit_viewer.php';

requireAnyRole($pdo, ['ADMIN', 'HR_ADMIN']);

$actor = staffActor($pdo);
$userId = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
$target = preg_match('/^[0-9a-f-]{36}$/i', $userId) ? getStaffUser($pdo, $userId) : null;

// Unknown ids and accounts outside the viewer's scope look the same.
if ($target === null || !canViewStaffTarget($actor, $target)) {
    http_response_code(404);
    exit('User not found.');
}

$actions = availableStaffActions($actor, $target);
$allowedKeys = array_column($actions, 'key');
$isSelf = $target['user_id'] === $actor['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $reason = $_POST['reason'] ?? '';

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('danger', 'Your session has expired. Please refresh the page and try again.');
    } elseif (!in_array($action, $allowedKeys, true)) {
        setFlash('danger', 'That action is not available for this account.');
    } else {
        if (isset(STAFF_STATUS_ACTIONS[$action])) {
            $result = setStaffStatus($pdo, $actor, $userId, $action, is_string($reason) ? $reason : '');
        } elseif ($action === 'change_role') {
            $number = static fn (string $key): int => (isset($_POST[$key]) && ctype_digit((string) $_POST[$key])) ? (int) $_POST[$key] : 0;
            $result = changeStaffRole(
                $pdo, $actor, $userId,
                is_string($_POST['role'] ?? null) ? $_POST['role'] : '',
                $number('ministry_id'), $number('department_id'),
                is_string($reason) ? $reason : ''
            );
        } elseif ($action === 'force_reset') {
            $result = forceStaffPasswordReset($pdo, $actor, $userId);
        } else {
            $result = resendStaffInvite($pdo, $actor, $userId);
        }

        setFlash($result['success'] ? 'success' : 'danger', $result['message'] . ($result['success'] ? describeDelivery($result) : ''));
    }

    redirect('users-view&id=' . $userId);
}

$issued = takeIssuedLink($target['user_id']);
$activity = staffRecentActivity($pdo, $target['user_id']);
$name = staffDisplayName($target);
$initials = mb_strtoupper(implode('', array_map(
    static fn (string $word): string => mb_substr($word, 0, 1),
    array_slice(preg_split('/\s+/', $name) ?: [], 0, 2)
)));

$directoryJson = '[]';

if (in_array('change_role', $allowedKeys, true)) {
    $directoryJson = json_encode(directoryForScope($pdo, $actor['scope']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
}

$details = [
    'Role' => STAFF_ROLES[$target['role_key']] ?? 'None',
    'Scope' => staffScopeLabel($target),
    'Phone' => $target['phone_number'] !== '' ? $target['phone_number'] : 'Not recorded',
    'Last sign-in' => formatWhen($target['last_sign_in']) ?: 'Never',
];

if ($target['invite'] !== null) {
    $details['Invited by'] = ($target['invite']['created_by_email'] ?: 'Unknown') . ', ' . formatWhen($target['invite']['created_at'], 'j M Y');

    if ($target['status'] === 'invited') {
        $details['Invite'] = ucfirst($target['invite']['status']) . ', expires ' . formatWhen($target['invite']['expires_at']);
    }
}

staffPageStart($name, 'users');
?>
<a class="small link-gold text-decoration-none" href="<?= escapeHtml(urlWith('users')) ?>">
    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to users
</a>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 my-4">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center fw-semibold text-bg-primary" style="width: 48px; height: 48px;" aria-hidden="true">
            <?= escapeHtml($initials) ?>
        </div>
        <div>
            <h1 class="h3 fw-bold mb-0"><?= escapeHtml($name) ?></h1>
            <div class="text-secondary small"><?= escapeHtml($target['email']) ?></div>
        </div>
    </div>
    <div class="d-flex gap-2"><?= staffRoleBadge($target['role_key']) ?> <?= staffStatusBadge($target['status']) ?></div>
</div>

<?= renderFlash() ?>

<?php if ($issued !== null): ?>
    <section class="card border-warning mb-4" aria-labelledby="link-heading">
        <div class="card-body">
            <h2 class="h6 fw-semibold" id="link-heading"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i><?= escapeHtml($issued['label']) ?></h2>
            <p class="small text-secondary mb-3">Shown once. Valid until <?= escapeHtml($issued['expires']) ?>.</p>
            <div class="input-group mb-3">
                <input type="text" class="form-control font-monospace small" id="issuedLink" readonly value="<?= escapeHtml($issued['url']) ?>" aria-label="<?= escapeHtml($issued['label']) ?>">
                <button type="button" class="btn btn-outline-secondary" data-copy-target="issuedLink">
                    <i class="bi bi-clipboard me-1" aria-hidden="true"></i>Copy link
                </button>
            </div>
            <div class="alert alert-warning small mb-0" role="note">
                Share this only with <?= escapeHtml($name) ?>. Anyone who has the link can set the password. Showing it has been recorded in the audit log.
            </div>
        </div>
    </section>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <section class="card profile-card mb-4" aria-labelledby="details-heading">
            <div class="card-header profile-card-header">
                <span class="section-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
                <div><h2 class="h5 mb-1" id="details-heading">Details</h2></div>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($details as $label => $value): ?>
                    <li class="list-group-item d-flex justify-content-between gap-3 small">
                        <span class="text-secondary"><?= escapeHtml($label) ?></span>
                        <span class="text-end"><?= escapeHtml($value) ?></span>
                    </li>
                <?php endforeach; ?>
                <li class="list-group-item d-flex justify-content-between gap-3 small">
                    <span class="text-secondary">Two-factor sign-in</span>
                    <span class="text-secondary">Not available yet</span>
                </li>
            </ul>
        </section>

        <section class="card profile-card" aria-labelledby="actions-heading">
            <div class="card-header profile-card-header">
                <span class="section-icon section-icon-gold"><i class="bi bi-gear" aria-hidden="true"></i></span>
                <div><h2 class="h5 mb-1" id="actions-heading">Account actions</h2></div>
            </div>
            <div class="card-body d-grid gap-2">
                <?php if ($isSelf): ?>
                    <p class="small text-secondary mb-0">This is your own account. For safety, you can't suspend, archive or change your own role.</p>
                <?php elseif (!$actions): ?>
                    <p class="small text-secondary mb-0">You can view this account but not change it.</p>
                <?php endif; ?>

                <?php foreach ($actions as $item): ?>
                    <?php if ($item['key'] === 'change_role'): ?>
                        <button type="button" class="btn btn-outline-secondary text-start" data-bs-toggle="modal" data-bs-target="#roleModal">
                            <i class="bi <?= escapeHtml($item['icon']) ?> me-2" aria-hidden="true"></i><?= escapeHtml($item['label']) ?>
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn text-start <?= $item['danger'] ? 'btn-outline-danger' : 'btn-outline-secondary' ?>"
                                data-bs-toggle="modal" data-bs-target="#actionModal"
                                data-action="<?= escapeHtml($item['key']) ?>"
                                data-title="<?= escapeHtml($item['label']) ?>"
                                data-body="<?= escapeHtml($item['body']) ?>"
                                data-reason="<?= escapeHtml($item['reason']) ?>"
                                data-danger="<?= $item['danger'] ? '1' : '0' ?>">
                            <i class="bi <?= escapeHtml($item['icon']) ?> me-2" aria-hidden="true"></i><?= escapeHtml($item['label']) ?>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="col-lg-7">
        <section class="card profile-card" aria-labelledby="activity-heading">
            <div class="card-header profile-card-header">
                <span class="section-icon"><i class="bi bi-activity" aria-hidden="true"></i></span>
                <div><h2 class="h5 mb-1" id="activity-heading">Recent activity</h2><p class="small text-secondary mb-0">Done by this user or to this user</p></div>
                <?php if (userHasRole('ADMIN')): ?>
                    <a class="ms-auto small link-gold text-decoration-none" href="<?= escapeHtml(urlWith('audit-log', ['q' => $target['email']])) ?>">Full log</a>
                <?php endif; ?>
            </div>
            <?php if (!$activity): ?>
                <p class="text-secondary p-4 mb-0">No activity recorded yet.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($activity as $event): ?>
                        <li class="list-group-item py-3">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <?= escapeHtml(auditActionLabel($event['action'])) ?>
                                    <?php if ($event['outcome'] !== 'success'): ?><?= auditOutcomeBadge($event['outcome']) ?><?php endif; ?>
                                </div>
                                <div class="small text-secondary text-nowrap"><?= escapeHtml(formatWhen($event['occurred_at'], 'j M, H:i')) ?></div>
                            </div>
                            <div class="small text-secondary">
                                <?php if ($event['actor_email'] !== '' && $event['actor_email'] !== $target['email']): ?>
                                    By <?= escapeHtml($event['actor_email']) ?> ·
                                <?php endif; ?>
                                <?php if ($event['browser'] !== ''): ?>
                                    <?= escapeHtml($event['browser'] . ', ' . $event['os'] . ', ' . $event['device_type']) ?> ·
                                <?php endif; ?>
                                <?= escapeHtml($event['ip_address'] !== '' ? $event['ip_address'] : 'no address') ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php if ($actions): ?>
    <!-- Confirmation for every action that doesn't need its own fields -->
    <div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalTitle" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" action="<?= escapeHtml(urlWith('users-view', ['id' => $target['user_id']])) ?>" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                <input type="hidden" name="action" value="">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="actionModalTitle" data-modal-title></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3" data-modal-body></p>
                    <div data-reason-wrap hidden>
                        <label for="actionReason" class="form-label">Reason <span class="text-danger" data-reason-star hidden>*</span></label>
                        <textarea class="form-control" id="actionReason" name="reason" rows="3" maxlength="200"></textarea>
                        <div class="form-text">Recorded in the audit log.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" data-modal-submit>Confirm</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if (in_array('change_role', $allowedKeys, true)): ?>
    <div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleModalTitle" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" action="<?= escapeHtml(urlWith('users-view', ['id' => $target['user_id']])) ?>" class="modal-content" data-scope-picker>
                <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                <input type="hidden" name="action" value="change_role">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="roleModalTitle">Change role or scope</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary">They will be signed out everywhere and must sign in again to get the new access.</p>
                    <div class="mb-3">
                        <label for="roleSelect" class="form-label">Role</label>
                        <select class="form-select" id="roleSelect" name="role" required>
                            <?php foreach (creatableRoles($actor['scope']) as $key): ?>
                                <option value="<?= escapeHtml($key) ?>" <?= $target['role_key'] === $key ? 'selected' : '' ?>><?= escapeHtml(STAFF_ROLES[$key]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" data-scope-ministry hidden>
                        <label for="roleMinistry" class="form-label">Ministry</label>
                        <select class="form-select" id="roleMinistry" name="ministry_id" <?= $actor['scope']['all'] ? '' : 'disabled data-locked="true"' ?>>
                            <option value="">Select ministry</option>
                            <?php foreach (json_decode($directoryJson, true) ?: [] as $ministry): ?>
                                <option value="<?= (int) $ministry['id'] ?>" <?= $target['ministry_id'] === $ministry['id'] ? 'selected' : '' ?>><?= escapeHtml($ministry['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" data-scope-department hidden>
                        <label for="roleDepartment" class="form-label">State department</label>
                        <select class="form-select" id="roleDepartment" name="department_id" data-selected="<?= (int) $target['department_id'] ?>">
                            <option value="">Select a ministry first</option>
                        </select>
                    </div>
                    <div>
                        <label for="roleReason" class="form-label">Reason</label>
                        <textarea class="form-control" id="roleReason" name="reason" rows="2" maxlength="200"></textarea>
                        <div class="form-text">Optional. Recorded in the audit log.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php staffPageEnd(['admin-users.js'], 'window.adminDirectory = ' . $directoryJson . ';'); ?>
