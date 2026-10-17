<?php

// Each section is stored in its own table; a row exists only once the section is fully valid.
const PROFILE_SECTIONS = [
    'personal' => [
        'table' => 'student_personal',
        'label' => 'Personal details',
        'fields' => ['first_name', 'other_names', 'identity_type', 'identity_number',
                     'phone_number', 'alternate_phone_number', 'nationality', 'gender'],
    ],
    'academic' => [
        'table' => 'student_academic',
        'label' => 'Academic information',
        'fields' => ['institution', 'course_name', 'academic_qualification'],
    ],
    'residence' => [
        'table' => 'student_residence',
        'label' => 'Residential details',
        'fields' => ['county', 'constituency', 'ward'],
    ],
];

function profileDefaults(): array
{
    $defaults = [];

    foreach (PROFILE_SECTIONS as $section) {
        foreach ($section['fields'] as $field) {
            $defaults[$field] = '';
        }
    }

        return array_merge(
            $defaults,
            [
                'identity_type' => 'national_id',
                'nationality' => 'Kenyan'
            ]);
}

// Returns ['values' => field => value, 'saved' => section => bool, 'updated_at' => ?string].
function getStudentProfile(PDO $pdo, string $userId): array
{
    $profile = ['values' => [], 'saved' => [], 'updated_at' => null];

    foreach (PROFILE_SECTIONS as $key => $section) {
        // Table and column names come from the constant above, never from user input.
        $statement = $pdo->prepare("SELECT * FROM {$section['table']} WHERE user_id = :user_id LIMIT 1");
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();

        $profile['saved'][$key] = $row !== false;

        if ($row) {
            foreach ($section['fields'] as $field) {
                $profile['values'][$field] = $row[$field];
            }

            if ($profile['updated_at'] === null || $row['updated_at'] > $profile['updated_at']) {
                $profile['updated_at'] = $row['updated_at'];
            }
        }
    }

    return $profile;
}

function profileCompletion(array $saved): int
{
    return (int) round(count(array_filter($saved)) / count(PROFILE_SECTIONS) * 100);
}

function isProfileComplete(array $saved): bool
{
    return count(array_filter($saved)) === count(PROFILE_SECTIONS);
}

function sectionBadge(bool $saved): string
{
    return $saved
        ? '<span class="badge text-bg-success ms-auto">Saved</span>'
        : '<span class="badge text-bg-secondary ms-auto">Not saved</span>';
}

function normalizePhone(string $phone): string
{
    return preg_replace('/[\s().-]/', '', $phone);
}

// Returns [cleanValues, errorMessages] for one section.
function validateProfileSection(string $section, array $input, array $lookups): array
{
    $text = static function (string $key) use ($input): string {
        $value = $input[$key] ?? '';

        return is_string($value) ? trim(preg_replace('/\s+/', ' ', $value)) : '';
    };

    $clean = [];
    $errors = [];

    // Checks one text field's presence and length.
    $checkText = static function (string $field, string $label, int $max, bool $required = true) use (&$clean, &$errors): void {
        if ($clean[$field] === '' && $required) {
            $errors[$field] = "{$label} is required.";
        } elseif (mb_strlen($clean[$field]) > $max) {
            $errors[$field] = "{$label} must not exceed {$max} characters.";
        }
    };

    switch ($section) {
        case 'personal':
            $clean = [
                'first_name' => $text('first_name'),
                'other_names' => $text('other_names'),
                'identity_type' => $text('identity_type'),
                'identity_number' => strtoupper(str_replace(' ', '', $text('identity_number'))),
                'phone_number' => normalizePhone($text('phone_number')),
                'alternate_phone_number' => normalizePhone($text('alternate_phone_number')),
                'nationality' => $text('nationality'),
                'gender' => $text('gender'),
            ];

            $checkText('first_name', 'First name', 100);
            $checkText('other_names', 'Other names', 150, false);
            $checkText('nationality', 'Nationality', 100);

            if (!in_array($clean['identity_type'], ['national_id', 'passport'], true)) {
                $errors['identity_type'] = 'Select a valid identity document.';
            } else {
                $pattern = $clean['identity_type'] === 'national_id' ? '/^[0-9]{7,8}$/' : '/^[A-Z0-9]{6,20}$/';

                if (!preg_match($pattern, $clean['identity_number'])) {
                    $errors['identity_number'] = 'Enter a valid identity number.';
                }
            }

            if (!preg_match('/^\+?[0-9]{9,15}$/', $clean['phone_number'])) {
                $errors['phone_number'] = 'Enter a valid phone number.';
            }

            // Optional: empty is fine, but if given it must be valid and different.
            if ($clean['alternate_phone_number'] !== '') {
                if (!preg_match('/^\+?[0-9]{9,15}$/', $clean['alternate_phone_number'])) {
                    $errors['alternate_phone_number'] = 'Enter a valid alternate phone number, or leave it blank.';
                } elseif ($clean['alternate_phone_number'] === $clean['phone_number']) {
                    $errors['alternate_phone_number'] = 'The alternate phone number must differ from your main number.';
                }
            }

            if (!in_array($clean['gender'], ['Male', 'Female'], true)) {
                $errors['gender'] = 'Select a gender option.';
            }
            break;

        case 'academic':
            $clean = [
                'institution' => $text('institution'),
                'course_name' => $text('course_name'),
                'academic_qualification' => $text('academic_qualification'),
            ];

            $checkText('institution', 'Institution', 180);
            $checkText('course_name', 'Course name', 180);

            if (!in_array($clean['academic_qualification'], $lookups['qualifications'], true)) {
                $errors['academic_qualification'] = 'Select Degree, Diploma or Certificate.';
            }
            break;

        case 'residence':
            $clean = [
                'county' => $text('county'),
                'constituency' => $text('constituency'),
                'ward' => $text('ward'),
            ];

            if (!in_array($clean['county'], $lookups['counties'], true)) {
                $errors['county'] = 'Select a valid county.';
            }

            $checkText('constituency', 'Constituency', 100);
            $checkText('ward', 'Ward', 100);
            break;

        default:
            $errors['section'] = 'Invalid form submission.';
    }

    return [$clean, $errors];
}

function saveProfileSection(PDO $pdo, string $userId, string $section, array $clean): array
{
    $definition = PROFILE_SECTIONS[$section];
    $fields = $definition['fields'];
    $now = date('Y-m-d H:i:s');

    $columns = implode(', ', $fields);
    $placeholders = implode(', ', array_map(static fn ($field) => ':' . $field, $fields));
    $updates = implode(', ', array_map(static fn ($field) => "{$field} = VALUES({$field})", $fields));

    // VALUES() works on both MySQL and MariaDB.
    $sql = "INSERT INTO {$definition['table']} (user_id, {$columns}, created_at, updated_at)
            VALUES (:user_id, {$placeholders}, :created_at, :updated_at)
            ON DUPLICATE KEY UPDATE {$updates}, updated_at = VALUES(updated_at)";

    try {
        $pdo->prepare($sql)->execute($clean + [
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['success' => true];
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            return ['success' => false, 'message' => 'That identity number is already registered to another account.'];
        }

        error_log('IAIMS profile save error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Could not save this section. Please try again.'];
    }
}

// Loads the bundled county > constituency > ward list once per request.
function kenyaLocations(): array
{
    static $locations = null;

    if ($locations === null) {
        $json = file_get_contents(__DIR__ . '/../assets/data/kenya-locations.json');
        $locations = $json !== false ? (json_decode($json, true) ?: []) : [];
    }

    return $locations;
}

/* ---------- Applications ---------- */

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

function createApplication(PDO $pdo, string $userId, array $profile, array $input, array $files, array $lookups): array
{
    $ministry = is_string($input['ministry'] ?? null) ? trim($input['ministry']) : '';
    $department = is_string($input['state_department'] ?? null) ? trim($input['state_department']) : '';
    $errors = [];

    if (!isset($lookups['ministries'][$ministry])
        || !in_array($department, $lookups['ministries'][$ministry], true)) {
        $errors[] = 'Select a valid ministry and state department.';
    }

    foreach ($lookups['documents'] as $type => $label) {
        if ($message = validatePdfUpload($files[$type] ?? null, $label)) {
            $errors[] = $message;
        }
    }

    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }

    // One live application per state department.
    $duplicate = $pdo->prepare(
        "SELECT 1 FROM applications
         WHERE user_id = :user_id AND state_department = :department
           AND status IN ('submitted', 'under_review') LIMIT 1"
    );
    $duplicate->execute(['user_id' => $userId, 'department' => $department]);

    if ($duplicate->fetch()) {
        return ['success' => false, 'errors' => ['You already have an active application to that state department.']];
    }

    $applicationId = generateUuid();
    $directory = STORAGE_PATH . '/applications/' . $applicationId;
    $movedFiles = [];
    $now = date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        $insert = $pdo->prepare(
            'INSERT INTO applications
                (application_id, user_id, ministry, state_department, institution,
                 course_name, academic_qualification, status, submitted_at, updated_at)
             VALUES
                (:application_id, :user_id, :ministry, :state_department, :institution,
                 :course_name, :academic_qualification, :status, :submitted_at, :updated_at)'
        );
        $insert->execute([
            'application_id' => $applicationId,
            'user_id' => $userId,
            'ministry' => $ministry,
            'state_department' => $department,
            'institution' => $profile['institution'],
            'course_name' => $profile['course_name'],
            'academic_qualification' => $profile['academic_qualification'],
            'status' => 'submitted',
            'submitted_at' => $now,
            'updated_at' => $now,
        ]);

        if (!mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create upload directory.');
        }

        $documentInsert = $pdo->prepare(
            'INSERT INTO application_documents
                (application_id, document_type, original_name, stored_name, size_bytes, uploaded_at)
             VALUES (:application_id, :document_type, :original_name, :stored_name, :size_bytes, :uploaded_at)'
        );

        foreach (array_keys($lookups['documents']) as $type) {
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

        $pdo->commit();

        return ['success' => true, 'message' => 'Your application has been submitted.'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Files are not part of the transaction, so clean them up by hand.
        foreach ($movedFiles as $path) {
            @unlink($path);
        }
        @rmdir($directory);

        error_log('IAIMS application error: ' . $exception->getMessage());

        return ['success' => false, 'errors' => ['Your application could not be submitted. Please try again.']];
    }
}

// function listApplications(PDO $pdo, string $userId): array
// {
//     $statement = $pdo->prepare(
//         'SELECT application_id, ministry, state_department, status, submitted_at
//          FROM applications WHERE user_id = :user_id ORDER BY submitted_at DESC'
//     );
//     $statement->execute(['user_id' => $userId]);

//     return $statement->fetchAll();
// }

// function statusBadge(string $status): string
// {
//     $colours = [
//         'submitted' => 'primary', 'under_review' => 'warning', 'accepted' => 'success',
//         'rejected' => 'danger', 'withdrawn' => 'secondary',
//     ];

//     return sprintf(
//         '<span class="badge text-bg-%s">%s</span>',
//         $colours[$status] ?? 'secondary',
//         escapeHtml(ucwords(str_replace('_', ' ', $status)))
//     );
// }


function listApplications(PDO $pdo, string $userId, ?int $limit = null): array
{
    $sql = 'SELECT application_id, ministry, state_department, status, submitted_at
            FROM applications WHERE user_id = :user_id ORDER BY submitted_at DESC';

    if ($limit !== null) {
        $sql .= ' LIMIT ' . max(1, $limit); // int-cast, so safe to inline
    }

    $statement = $pdo->prepare($sql);
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}


function getApplicationCounts(PDO $pdo, string $userId): array
{
    $statement = $pdo->prepare(
        'SELECT status, COUNT(*) AS total FROM applications WHERE user_id = :user_id GROUP BY status'
    );
    $statement->execute(['user_id' => $userId]);

    $counts = ['submitted' => 0, 'under_review' => 0, 'accepted' => 0, 'rejected' => 0, 'withdrawn' => 0];

    foreach ($statement->fetchAll() as $row) {
        $counts[$row['status']] = (int) $row['total'];
    }

    return $counts;
}
