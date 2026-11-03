<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/capacity.php';

// Applications: the student's side (applying, seeing where they stand) and the reading that
// students and HR share. HR's side (taking one from the queue, deciding) is in review.php, and
// handing out places is in allocation.php.
//
// The queue is first in, first out. Every application gets a queue number that only goes up when
// it is saved, and reviews and waitlists both follow it.

// An application that still counts against the "one live application per ministry" rule.
const LIVE_APPLICATION_STATUSES = "('submitted', 'under_review', 'waitlisted', 'accepted')";

// How far ahead a student may say they can start. Places are held from the moment they are
// reserved, so a start date far in the future would block a place for months.
const MAX_START_DAYS_AHEAD = 90;

// A list from config/lookups.php (documents, rejection_reasons ...), loaded once.
function lookupList(string $key): array
{
    static $lookups = null;

    $lookups ??= require __DIR__ . '/../config/lookups.php';

    return $lookups[$key] ?? [];
}

/* ---------- Where a student can apply ---------- */

// Active ministries with the departments that are taking applications right now, shaped for the
// form and its script. A department only appears if it is not paused and has an active HR
// officer or admin, so no application is sent where nobody can review it.
function applicationDirectory(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT m.ministry_id, m.name AS ministry_name, d.department_id, d.name AS department_name
         FROM departments d
         INNER JOIN ministries m ON m.ministry_id = d.ministry_id AND m.status = 'active'
         LEFT JOIN department_settings s ON s.department_id = d.department_id
         WHERE d.status = 'active'
           AND COALESCE(s.accepting_applications, 1) = 1
           AND EXISTS (SELECT 1 FROM hr_assignments ha
                       INNER JOIN users u ON u.user_id = ha.user_id
                       WHERE ha.department_id = d.department_id AND u.status = 'active')
         ORDER BY m.name, d.name"
    )->fetchAll();

    $ministries = [];

    foreach ($rows as $row) {
        $id = (int) $row['ministry_id'];
        $ministries[$id] ??= ['id' => $id, 'name' => $row['ministry_name'], 'departments' => []];
        $ministries[$id]['departments'][] = ['id' => (int) $row['department_id'], 'name' => $row['department_name']];
    }

    return array_values($ministries);
}

/* ---------- History ---------- */

// Adds one entry to an application's history. Call inside the transaction of the change it records.
// $event: application_id, type, and optionally from, to, reason_code, note, visibility
// ('internal' or 'student'), actor_id, actor_name.
function recordApplicationEvent(PDO $pdo, array $event): void
{
    $pdo->prepare(
        'INSERT INTO application_events
            (application_id, event_type, from_status, to_status, reason_code, note, visibility, actor_user_id, actor_name, created_at)
         VALUES
            (:application_id, :event_type, :from_status, :to_status, :reason_code, :note, :visibility, :actor_user_id, :actor_name, :created_at)'
    )->execute([
        'application_id' => $event['application_id'],
        'event_type' => $event['type'],
        'from_status' => $event['from'] ?? '',
        'to_status' => $event['to'] ?? '',
        'reason_code' => $event['reason_code'] ?? '',
        'note' => $event['note'] ?? '',
        'visibility' => ($event['visibility'] ?? 'internal') === 'student' ? 'student' : 'internal',
        'actor_user_id' => $event['actor_id'] ?? '',
        'actor_name' => mb_substr((string) ($event['actor_name'] ?? ''), 0, 255),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

// An application's history, newest first. $studentOnly keeps just what the student may see.
function getApplicationEvents(PDO $pdo, string $applicationId, bool $studentOnly = false): array
{
    $statement = $pdo->prepare(
        'SELECT * FROM application_events WHERE application_id = :id'
        . ($studentOnly ? " AND visibility = 'student'" : '')
        . ' ORDER BY event_id DESC'
    );
    $statement->execute(['id' => $applicationId]);

    return $statement->fetchAll();
}

/* ---------- Applying ---------- */

function validatePdfUpload($file, string $label): ?string
{
    $error = is_array($file) ? ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

    if ($error === UPLOAD_ERR_NO_FILE) {
        return "{$label} is required.";
    }

    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return "{$label} is larger than 10 MB.";
    }

    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return "{$label} could not be uploaded. Please try again.";
    }

    if ($file['size'] <= 0 || $file['size'] > MAX_UPLOAD_BYTES) {
        return "{$label} must be between 1 byte and 10 MB.";
    }

    // Check the real content, not the browser-supplied type or extension.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

    if ($mime !== 'application/pdf') {
        return "{$label} must be a PDF file.";
    }

    return null;
}

// Validates and saves an application with its six PDFs, in the queue. The application, its first
// history entry and its audit entry are written together. Returns
// ['success' => true, 'message' => ..., 'application_id' => ...] or ['success' => false, 'errors' => [...]].
function createApplication(PDO $pdo, string $userId, array $profile, array $input): array
{
    $number = static fn (string $key): int => (isset($input[$key]) && is_scalar($input[$key]) && ctype_digit((string) $input[$key]))
        ? (int) $input[$key]
        : 0;

    $departmentId = $number('department_id');
    $weeks = $number('requested_weeks');
    $earliest = is_string($input['earliest_start'] ?? null) ? trim($input['earliest_start']) : '';
    $today = date('Y-m-d');
    $errors = [];

    // The chosen department must be one that is taking applications right now.
    $chosen = null;

    foreach (applicationDirectory($pdo) as $ministry) {
        foreach ($ministry['departments'] as $department) {
            if ($department['id'] === $departmentId) {
                $chosen = ['ministry_id' => $ministry['id'], 'ministry' => $ministry['name'], 'department' => $department['name']];
            }
        }
    }

    if ($chosen === null) {
        $errors[] = 'Select a department that is currently accepting applications.';
    }

    if ($weeks < attachmentMinWeeks() || $weeks > attachmentMaxWeeks()) {
        $errors[] = 'The attachment length must be between ' . attachmentMinWeeks() . ' and ' . attachmentMaxWeeks() . ' weeks.';
    }

    $parsed = DateTime::createFromFormat('Y-m-d', $earliest);

    if (!$parsed || $parsed->format('Y-m-d') !== $earliest) {
        $errors[] = 'Enter the earliest date you can start.';
    } elseif ($earliest < $today) {
        $errors[] = 'The start date cannot be in the past.';
    } elseif ($earliest > date('Y-m-d', strtotime($today . ' +' . MAX_START_DAYS_AHEAD . ' days'))) {
        $errors[] = 'The start date must be within the next ' . MAX_START_DAYS_AHEAD . ' days.';
    }

    $files = $_FILES;

    foreach (lookupList('documents') as $type => $label) {
        if ($message = validatePdfUpload($files[$type] ?? null, $label)) {
            $errors[] = $message;
        }
    }

    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }

    // One live application per ministry: choosing a department is a preference, not a way to
    // hold several places in the same queue.
    $duplicate = $pdo->prepare(
        'SELECT 1 FROM applications a
         INNER JOIN departments d ON d.department_id = a.preferred_department_id
         WHERE a.user_id = :user_id AND d.ministry_id = :ministry_id AND a.status IN ' . LIVE_APPLICATION_STATUSES . ' LIMIT 1'
    );
    $duplicate->execute(['user_id' => $userId, 'ministry_id' => $chosen['ministry_id']]);

    if ($duplicate->fetchColumn()) {
        return ['success' => false, 'errors' => ['You already have an application in progress with that ministry.']];
    }

    $applicationId = generateUuid();
    $directory = STORAGE_PATH . '/applications/' . $applicationId;
    $movedFiles = [];
    $studentName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['other_names'] ?? ''));

    try {
        auditedTransaction($pdo, function (PDO $pdo) use (
            $applicationId, $userId, $profile, $chosen, $departmentId, $weeks, $earliest, $files, $directory, &$movedFiles, $studentName
        ) {
            $now = date('Y-m-d H:i:s');

            $pdo->prepare(
                'INSERT INTO applications
                    (application_id, user_id, preferred_department_id, ministry, state_department, institution,
                     course_name, academic_qualification, requested_weeks, earliest_start, status,
                     submitted_at, updated_at, interest_confirmed_at)
                 VALUES
                    (:application_id, :user_id, :department_id, :ministry, :state_department, :institution,
                     :course_name, :academic_qualification, :weeks, :earliest, :status,
                     :submitted_at, :updated_at, :confirmed_at)'
            )->execute([
                'application_id' => $applicationId,
                'user_id' => $userId,
                'department_id' => $departmentId,
                'ministry' => $chosen['ministry'],
                'state_department' => $chosen['department'],
                'institution' => $profile['institution'],
                'course_name' => $profile['course_name'],
                'academic_qualification' => $profile['academic_qualification'],
                'weeks' => $weeks,
                'earliest' => $earliest,
                'status' => 'submitted',
                'submitted_at' => $now,
                'updated_at' => $now,
                'confirmed_at' => $now,
            ]);

            if (!mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new RuntimeException('Could not create upload directory.');
            }

            $documentInsert = $pdo->prepare(
                'INSERT INTO application_documents
                    (application_id, document_type, original_name, stored_name, size_bytes, uploaded_at)
                 VALUES (:application_id, :document_type, :original_name, :stored_name, :size_bytes, :uploaded_at)'
            );

            foreach (array_keys(lookupList('documents')) as $type) {
                $file = $files[$type];
                $storedName = bin2hex(random_bytes(16)) . '.pdf';

                if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $storedName)) {
                    throw new RuntimeException("Could not store {$type}.");
                }

                $movedFiles[] = $directory . '/' . $storedName;

                $documentInsert->execute([
                    'application_id' => $applicationId,
                    'document_type' => $type,
                    'original_name' => mb_substr(preg_replace('/[^\w. ()-]/u', '_', basename($file['name'])), 0, 255),
                    'stored_name' => $storedName,
                    'size_bytes' => (int) $file['size'],
                    'uploaded_at' => $now,
                ]);
            }

            recordApplicationEvent($pdo, [
                'application_id' => $applicationId,
                'type' => 'submitted',
                'to' => 'submitted',
                'note' => 'Application submitted to ' . $chosen['department'] . '.',
                'visibility' => 'student',
                'actor_id' => $userId,
                'actor_name' => $studentName,
            ]);

            auditLog($pdo, 'application.submitted', 'success', [
                'target_type' => 'application',
                'target_id' => $applicationId,
                'target_label' => $chosen['department'],
                'details' => ['department_id' => $departmentId, 'weeks' => $weeks, 'earliest_start' => $earliest],
            ]);
        });
    } catch (Throwable $exception) {
        // Files are not part of the transaction, so clean them up by hand.
        foreach ($movedFiles as $path) {
            @unlink($path);
        }
        @rmdir($directory);

        error_log('IAIMS application error: ' . $exception->getMessage());

        return ['success' => false, 'errors' => ['Your application could not be submitted. Please try again.']];
    }

    return ['success' => true, 'message' => 'Your application has been submitted and joined the review queue.', 'application_id' => $applicationId];
}

/* ---------- What a student sees ---------- */

// The student's applications, newest first, with where each one stands in its department's line.
function listApplications(PDO $pdo, string $userId, ?int $limit = null): array
{
    $sql = "SELECT a.application_id, a.status, a.submitted_at, a.queue_no, a.requested_weeks, a.earliest_start,
                   a.ministry, a.state_department, a.preferred_department_id,
                   (SELECT COUNT(*) FROM applications o
                     WHERE o.preferred_department_id = a.preferred_department_id
                       AND o.status = 'submitted' AND o.queue_no < a.queue_no) AS ahead_in_review,
                   (SELECT COUNT(*) FROM applications w
                     WHERE w.preferred_department_id = a.preferred_department_id
                       AND w.status = 'waitlisted' AND w.queue_no < a.queue_no) AS ahead_on_waitlist
            FROM applications a WHERE a.user_id = :user_id ORDER BY a.queue_no DESC";

    if ($limit !== null) {
        $sql .= ' LIMIT ' . max(1, $limit); // int-cast, so safe to place in the query text
    }

    $statement = $pdo->prepare($sql);
    $statement->execute(['user_id' => $userId]);
    $rows = $statement->fetchAll();

    // The most recent thing the student is allowed to be told about each application.
    $latest = [];

    if ($rows) {
        $ids = array_column($rows, 'application_id');
        $events = $pdo->prepare(
            "SELECT application_id, event_type, to_status, reason_code, note, created_at
             FROM application_events
             WHERE visibility = 'student' AND application_id IN (" . implode(', ', array_fill(0, count($ids), '?')) . ')
             ORDER BY event_id DESC'
        );
        $events->execute($ids);

        foreach ($events->fetchAll() as $event) {
            $latest[$event['application_id']] ??= $event;
        }
    }

    foreach ($rows as &$row) {
        $row['latest_event'] = $latest[$row['application_id']] ?? null;
    }
    unset($row); // end the reference so later loops over $rows behave

    return $rows;
}

function getApplicationCounts(PDO $pdo, string $userId): array
{
    $statement = $pdo->prepare(
        'SELECT status, COUNT(*) AS total FROM applications WHERE user_id = :user_id GROUP BY status'
    );
    $statement->execute(['user_id' => $userId]);

    $counts = ['submitted' => 0, 'under_review' => 0, 'waitlisted' => 0, 'accepted' => 0, 'rejected' => 0, 'withdrawn' => 0, 'completed' => 0];

    foreach ($statement->fetchAll() as $row) {
        $counts[$row['status']] = (int) $row['total'];
    }

    return $counts;
}

function statusBadge(string $status): string
{
    $colours = [
        'submitted' => 'primary', 'under_review' => 'warning', 'waitlisted' => 'info', 'accepted' => 'success',
        'rejected' => 'danger', 'withdrawn' => 'secondary', 'completed' => 'dark',
    ];

    return sprintf(
        '<span class="badge text-bg-%s">%s</span>',
        $colours[$status] ?? 'secondary',
        escapeHtml(ucwords(str_replace('_', ' ', $status)))
    );
}

// A plain sentence about where the application stands, for the student.
function applicationProgressText(array $application): string
{
    switch ($application['status']) {
        case 'submitted':
            $ahead = (int) $application['ahead_in_review'];

            return $ahead === 0 ? 'You are next in line for review.' : $ahead . ($ahead === 1 ? ' application is' : ' applications are') . ' ahead of yours.';
        case 'under_review':
            return 'An officer is reviewing it now.';
        case 'waitlisted':
            return 'Approved. All places are in use, so you are on the waitlist at position ' . ((int) $application['ahead_on_waitlist'] + 1) . '.';
        case 'accepted':
            return 'Approved. A place has been reserved for you.';
        default:
            return '';
    }
}

/* ---------- What HR sees ---------- */

// One application with the applicant's details, but only if it belongs to the given department.
function getApplicationForHr(PDO $pdo, string $applicationId, int $departmentId): ?array
{
    if (!preg_match('/^[0-9a-f-]{36}$/i', $applicationId)) {
        return null;
    }

    $statement = $pdo->prepare(
        "SELECT a.*, u.email AS student_email,
                COALESCE(p.first_name, '') AS first_name, COALESCE(p.other_names, '') AS other_names,
                COALESCE(p.phone_number, '') AS phone_number, COALESCE(p.alternate_phone_number, '') AS alternate_phone_number,
                COALESCE(p.identity_type, '') AS identity_type, COALESCE(p.identity_number, '') AS identity_number,
                COALESCE(p.nationality, '') AS nationality, COALESCE(p.gender, '') AS gender,
                COALESCE(rs.county, '') AS county, COALESCE(rs.constituency, '') AS constituency, COALESCE(rs.ward, '') AS ward,
                COALESCE(rv.email, '') AS reviewer_email,
                COALESCE(rp.first_name, '') AS reviewer_first, COALESCE(rp.other_names, '') AS reviewer_other
         FROM applications a
         INNER JOIN users u ON u.user_id = a.user_id
         LEFT JOIN student_personal p ON p.user_id = a.user_id
         LEFT JOIN student_residence rs ON rs.user_id = a.user_id
         LEFT JOIN users rv ON rv.user_id = a.reviewer_user_id
         LEFT JOIN staff_profiles rp ON rp.user_id = a.reviewer_user_id
         WHERE a.application_id = :id AND a.preferred_department_id = :department_id
         LIMIT 1"
    );
    $statement->execute(['id' => $applicationId, 'department_id' => $departmentId]);
    $row = $statement->fetch();

    if (!$row) {
        return null;
    }

    $row['student_name'] = trim($row['first_name'] . ' ' . $row['other_names']) ?: $row['student_email'];
    $row['reviewer_name'] = trim($row['reviewer_first'] . ' ' . $row['reviewer_other']) ?: $row['reviewer_email'];

    // Where it stands in its line.
    $position = $pdo->prepare(
        'SELECT COUNT(*) FROM applications
         WHERE preferred_department_id = :department_id AND status = :status AND queue_no < :queue_no'
    );

    $position->execute(['department_id' => $departmentId, 'status' => 'submitted', 'queue_no' => $row['queue_no']]);
    $row['ahead_in_review'] = (int) $position->fetchColumn();

    $position->execute(['department_id' => $departmentId, 'status' => 'waitlisted', 'queue_no' => $row['queue_no']]);
    $row['ahead_on_waitlist'] = (int) $position->fetchColumn();

    return $row;
}

function applicationDocuments(PDO $pdo, string $applicationId): array
{
    $statement = $pdo->prepare(
        'SELECT document_id, document_type, original_name, size_bytes, uploaded_at
         FROM application_documents WHERE application_id = :id ORDER BY document_id'
    );
    $statement->execute(['id' => $applicationId]);

    return $statement->fetchAll();
}

// ['rows' => [...], 'total' => n] in queue order (oldest first). $status '' means all.
function listDepartmentApplications(PDO $pdo, int $departmentId, string $status, int $page, int $perPage): array
{
    $where = 'a.preferred_department_id = :department_id';
    $params = ['department_id' => $departmentId];

    if ($status !== '') {
        $where .= ' AND a.status = :status';
        $params['status'] = $status;
    }

    $count = $pdo->prepare('SELECT COUNT(*) FROM applications a WHERE ' . $where);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $offset = max(0, ($page - 1) * $perPage);
    $statement = $pdo->prepare(
        "SELECT a.application_id, a.queue_no, a.status, a.submitted_at, a.institution, a.course_name, a.reviewer_user_id,
                u.email AS student_email,
                COALESCE(p.first_name, '') AS first_name, COALESCE(p.other_names, '') AS other_names,
                COALESCE(rp.first_name, '') AS reviewer_first
         FROM applications a
         INNER JOIN users u ON u.user_id = a.user_id
         LEFT JOIN student_personal p ON p.user_id = a.user_id
         LEFT JOIN staff_profiles rp ON rp.user_id = a.reviewer_user_id
         WHERE {$where}
         ORDER BY a.queue_no ASC
         LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset
    );
    $statement->execute($params);

    $rows = array_map(static function (array $row): array {
        $row['student_name'] = trim($row['first_name'] . ' ' . $row['other_names']) ?: $row['student_email'];

        return $row;
    }, $statement->fetchAll());

    return ['rows' => $rows, 'total' => $total];
}
