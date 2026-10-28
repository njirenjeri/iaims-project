<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';

// Ministries and their state departments. Nothing is ever deleted: archiving hides a record
// from dropdowns while keeping it linked to its history. Every change is written together with
// its audit entry, so a change can't exist without a record of who made it.

const DIRECTORY_NAME_MAX = 191;
const DIRECTORY_LIVE_STATUSES = "('invited', 'active', 'suspended')";

/* ---------- Reading ---------- */

function cleanDirectoryName(string $name): string
{
    return trim(preg_replace('/\s+/', ' ', $name));
}

function directoryNameError(string $name, string $label): string
{
    if ($name === '') {
        return "{$label} is required.";
    }

    if (mb_strlen($name) > DIRECTORY_NAME_MAX) {
        return "{$label} must not exceed " . DIRECTORY_NAME_MAX . ' characters.';
    }

    return '';
}

function getMinistryName(PDO $pdo, int $ministryId): string
{
    $statement = $pdo->prepare('SELECT name FROM ministries WHERE ministry_id = :id LIMIT 1');
    $statement->execute(['id' => $ministryId]);

    return (string) $statement->fetchColumn();
}

// Ministries with how many active departments and live staff accounts each has.
function listMinistries(PDO $pdo, bool $includeArchived): array
{
    $live = DIRECTORY_LIVE_STATUSES;

    $sql = "SELECT m.ministry_id, m.name, m.status,
                   (SELECT COUNT(*) FROM departments d
                     WHERE d.ministry_id = m.ministry_id AND d.status = 'active') AS department_count,
                   (SELECT COUNT(*) FROM hr_assignments ha
                     INNER JOIN users u ON u.user_id = ha.user_id
                     WHERE ha.ministry_id = m.ministry_id AND u.status IN {$live})
                   + (SELECT COUNT(*) FROM supervisor_assignments sa
                       INNER JOIN departments d2 ON d2.department_id = sa.department_id
                       INNER JOIN users u2 ON u2.user_id = sa.user_id
                       WHERE d2.ministry_id = m.ministry_id AND u2.status IN {$live}) AS staff_count
            FROM ministries m"
        . ($includeArchived ? '' : " WHERE m.status = 'active'")
        . ' ORDER BY m.name';

    return $pdo->query($sql)->fetchAll();
}

// Departments grouped by ministry id, each with its live supervisor count.
function listDepartmentsByMinistry(PDO $pdo, bool $includeArchived): array
{
    $live = DIRECTORY_LIVE_STATUSES;

    $sql = "SELECT d.department_id, d.ministry_id, d.name, d.status,
                   (SELECT COUNT(*) FROM supervisor_assignments sa
                     INNER JOIN users u ON u.user_id = sa.user_id
                     WHERE sa.department_id = d.department_id AND u.status IN {$live}) AS staff_count
            FROM departments d"
        . ($includeArchived ? '' : " WHERE d.status = 'active'")
        . ' ORDER BY d.name';

    $grouped = [];

    foreach ($pdo->query($sql)->fetchAll() as $row) {
        $grouped[(int) $row['ministry_id']][] = $row;
    }

    return $grouped;
}

// Active ministries with their active departments, shaped for dropdowns and the page's script.
// An HR admin only gets their own ministry.
function directoryForScope(PDO $pdo, array $scope): array
{
    $ministries = [];

    foreach (listMinistries($pdo, false) as $ministry) {
        $id = (int) $ministry['ministry_id'];

        if (!$scope['all'] && $id !== $scope['ministry_id']) {
            continue;
        }

        $ministries[$id] = ['id' => $id, 'name' => $ministry['name'], 'departments' => []];
    }

    foreach (listDepartmentsByMinistry($pdo, false) as $ministryId => $departments) {
        if (!isset($ministries[$ministryId])) {
            continue;
        }

        foreach ($departments as $department) {
            $ministries[$ministryId]['departments'][] = [
                'id' => (int) $department['department_id'],
                'name' => $department['name'],
            ];
        }
    }

    return array_values($ministries);
}

/* ---------- Ministries ---------- */

function createMinistry(PDO $pdo, string $name): array
{
    $name = cleanDirectoryName($name);

    if ($error = directoryNameError($name, 'Ministry name')) {
        return ['success' => false, 'message' => $error];
    }

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($name) {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare(
                'INSERT INTO ministries (name, status, created_at, updated_at) VALUES (:name, :status, :now, :now2)'
            )->execute(['name' => $name, 'status' => 'active', 'now' => $now, 'now2' => $now]);

            auditLog($pdo, 'ministry.created', 'success', [
                'target_type' => 'ministry',
                'target_id' => (string) $pdo->lastInsertId(),
                'target_label' => $name,
            ]);
        });
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            return ['success' => false, 'message' => 'A ministry with that name already exists.'];
        }

        error_log('IAIMS ministry create error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Could not add the ministry. Please try again.'];
    } catch (Throwable $exception) {
        error_log('IAIMS ministry create error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Could not add the ministry. Please try again.'];
    }

    return ['success' => true, 'message' => "Ministry \"{$name}\" added."];
}

function renameMinistry(PDO $pdo, int $ministryId, string $name): array
{
    $name = cleanDirectoryName($name);

    if ($error = directoryNameError($name, 'Ministry name')) {
        return ['success' => false, 'message' => $error];
    }

    $old = getMinistryName($pdo, $ministryId);

    if ($old === '') {
        return ['success' => false, 'message' => 'That ministry was not found.'];
    }

    if ($old === $name) {
        return ['success' => false, 'message' => 'The name is unchanged.'];
    }

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($ministryId, $name, $old) {
            $pdo->prepare('UPDATE ministries SET name = :name, updated_at = :now WHERE ministry_id = :id')
                ->execute(['name' => $name, 'now' => date('Y-m-d H:i:s'), 'id' => $ministryId]);

            auditLog($pdo, 'ministry.renamed', 'success', [
                'target_type' => 'ministry',
                'target_id' => (string) $ministryId,
                'target_label' => $name,
                'details' => ['name' => ['from' => $old, 'to' => $name]],
            ]);
        });
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            return ['success' => false, 'message' => 'A ministry with that name already exists.'];
        }

        error_log('IAIMS ministry rename error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Could not rename the ministry. Please try again.'];
    } catch (Throwable $exception) {
        error_log('IAIMS ministry rename error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Could not rename the ministry. Please try again.'];
    }

    return ['success' => true, 'message' => 'Ministry renamed.'];
}

// $status is 'archived' or 'active'. A ministry with live staff can't be archived.
function setMinistryStatus(PDO $pdo, int $ministryId, string $status): array
{
    if (!in_array($status, ['active', 'archived'], true)) {
        return ['success' => false, 'message' => 'Invalid request.'];
    }

    $match = null;

    foreach (listMinistries($pdo, true) as $ministry) {
        if ((int) $ministry['ministry_id'] === $ministryId) {
            $match = $ministry;
        }
    }

    if ($match === null) {
        return ['success' => false, 'message' => 'That ministry was not found.'];
    }

    if ($match['status'] === $status) {
        return ['success' => false, 'message' => 'Nothing to change.'];
    }

    if ($status === 'archived' && (int) $match['staff_count'] > 0) {
        auditLogSafe($pdo, 'ministry.archive_blocked', 'denied', [
            'target_type' => 'ministry',
            'target_id' => (string) $ministryId,
            'target_label' => $match['name'],
            'details' => ['live_staff' => (int) $match['staff_count']],
        ]);

        return [
            'success' => false,
            'message' => "Archive or move the {$match['staff_count']} staff account(s) in this ministry first.",
        ];
    }

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($ministryId, $status, $match) {
            $pdo->prepare('UPDATE ministries SET status = :status, updated_at = :now WHERE ministry_id = :id')
                ->execute(['status' => $status, 'now' => date('Y-m-d H:i:s'), 'id' => $ministryId]);

            auditLog($pdo, $status === 'archived' ? 'ministry.archived' : 'ministry.restored', 'success', [
                'target_type' => 'ministry',
                'target_id' => (string) $ministryId,
                'target_label' => $match['name'],
            ]);
        });
    } catch (Throwable $exception) {
        error_log('IAIMS ministry status error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Could not update the ministry. Please try again.'];
    }

    return ['success' => true, 'message' => $status === 'archived' ? 'Ministry archived.' : 'Ministry restored.'];
}

/* ---------- Departments ---------- */

function createDepartment(PDO $pdo, int $ministryId, string $name): array
{
    $name = cleanDirectoryName($name);

    if ($error = directoryNameError($name, 'Department name')) {
        return ['success' => false, 'message' => $error];
    }

    $ministry = getMinistryName($pdo, $ministryId);

    if ($ministry === '') {
        return ['success' => false, 'message' => 'That ministry was not found.'];
    }

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($ministryId, $ministry, $name) {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare(
                'INSERT INTO departments (ministry_id, name, status, created_at, updated_at)
                 VALUES (:ministry_id, :name, :status, :now, :now2)'
            )->execute(['ministry_id' => $ministryId, 'name' => $name, 'status' => 'active', 'now' => $now, 'now2' => $now]);

            auditLog($pdo, 'department.created', 'success', [
                'target_type' => 'department',
                'target_id' => (string) $pdo->lastInsertId(),
                'target_label' => $name,
                'details' => ['ministry' => $ministry],
            ]);
        });
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            return ['success' => false, 'message' => 'That ministry already has a department with this name.'];
        }

        error_log('IAIMS department create error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Could not add the department. Please try again.'];
    } catch (Throwable $exception) {
        error_log('IAIMS department create error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Could not add the department. Please try again.'];
    }

    return ['success' => true, 'message' => "Department \"{$name}\" added to {$ministry}."];
}

function getDepartment(PDO $pdo, int $departmentId): ?array
{
    $statement = $pdo->prepare(
        "SELECT d.department_id, d.ministry_id, d.name, d.status, m.name AS ministry_name,
                (SELECT COUNT(*) FROM supervisor_assignments sa
                  INNER JOIN users u ON u.user_id = sa.user_id
                  WHERE sa.department_id = d.department_id AND u.status IN " . DIRECTORY_LIVE_STATUSES . ") AS staff_count
         FROM departments d
         INNER JOIN ministries m ON m.ministry_id = d.ministry_id
         WHERE d.department_id = :id
         LIMIT 1"
    );
    $statement->execute(['id' => $departmentId]);

    return $statement->fetch() ?: null;
}

function renameDepartment(PDO $pdo, int $departmentId, string $name): array
{
    $name = cleanDirectoryName($name);

    if ($error = directoryNameError($name, 'Department name')) {
        return ['success' => false, 'message' => $error];
    }

    $department = getDepartment($pdo, $departmentId);

    if ($department === null) {
        return ['success' => false, 'message' => 'That department was not found.'];
    }

    if ($department['name'] === $name) {
        return ['success' => false, 'message' => 'The name is unchanged.'];
    }

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($departmentId, $name, $department) {
            $pdo->prepare('UPDATE departments SET name = :name, updated_at = :now WHERE department_id = :id')
                ->execute(['name' => $name, 'now' => date('Y-m-d H:i:s'), 'id' => $departmentId]);

            auditLog($pdo, 'department.renamed', 'success', [
                'target_type' => 'department',
                'target_id' => (string) $departmentId,
                'target_label' => $name,
                'details' => ['name' => ['from' => $department['name'], 'to' => $name], 'ministry' => $department['ministry_name']],
            ]);
        });
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            return ['success' => false, 'message' => 'That ministry already has a department with this name.'];
        }

        error_log('IAIMS department rename error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Could not rename the department. Please try again.'];
    } catch (Throwable $exception) {
        error_log('IAIMS department rename error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Could not rename the department. Please try again.'];
    }

    return ['success' => true, 'message' => 'Department renamed.'];
}

// $status is 'archived' or 'active'. A department with live supervisors can't be archived,
// and one can't be restored while its ministry is archived.
function setDepartmentStatus(PDO $pdo, int $departmentId, string $status): array
{
    if (!in_array($status, ['active', 'archived'], true)) {
        return ['success' => false, 'message' => 'Invalid request.'];
    }

    $department = getDepartment($pdo, $departmentId);

    if ($department === null) {
        return ['success' => false, 'message' => 'That department was not found.'];
    }

    if ($department['status'] === $status) {
        return ['success' => false, 'message' => 'Nothing to change.'];
    }

    if ($status === 'archived' && (int) $department['staff_count'] > 0) {
        auditLogSafe($pdo, 'department.archive_blocked', 'denied', [
            'target_type' => 'department',
            'target_id' => (string) $departmentId,
            'target_label' => $department['name'],
            'details' => ['live_staff' => (int) $department['staff_count']],
        ]);

        return [
            'success' => false,
            'message' => "Archive or move the {$department['staff_count']} supervisor(s) in this department first.",
        ];
    }

    if ($status === 'active') {
        $ministryStatus = $pdo->prepare('SELECT status FROM ministries WHERE ministry_id = :id LIMIT 1');
        $ministryStatus->execute(['id' => $department['ministry_id']]);

        if ($ministryStatus->fetchColumn() !== 'active') {
            return ['success' => false, 'message' => 'Restore the ministry first.'];
        }
    }

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($departmentId, $status, $department) {
            $pdo->prepare('UPDATE departments SET status = :status, updated_at = :now WHERE department_id = :id')
                ->execute(['status' => $status, 'now' => date('Y-m-d H:i:s'), 'id' => $departmentId]);

            auditLog($pdo, $status === 'archived' ? 'department.archived' : 'department.restored', 'success', [
                'target_type' => 'department',
                'target_id' => (string) $departmentId,
                'target_label' => $department['name'],
                'details' => ['ministry' => $department['ministry_name']],
            ]);
        });
    } catch (Throwable $exception) {
        error_log('IAIMS department status error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Could not update the department. Please try again.'];
    }

    return ['success' => true, 'message' => $status === 'archived' ? 'Department archived.' : 'Department restored.'];
}
