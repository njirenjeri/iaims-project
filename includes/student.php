<?php

require_once __DIR__ . '/helpers.php';

// Student profile, saved one section at a time. Applications (applying, status, the queue) are in
// applications.php because HR needs them too.

/* ================================================================== */
/* Profile                                                             */
/* ================================================================== */

// Each section is stored in its own table; a row exists only once the section is fully valid,
// so no column ever needs to allow NULL.
const PROFILE_SECTIONS = [
    'personal' => [
        'table' => 'student_personal',
        'label' => 'Personal details',
        'fields' => ['first_name', 'other_names', 'identity_type', 'identity_number',
                     'phone_number', 'alternate_phone_number', 'nationality', 'gender', 'tribe', 'disability_status','disability_details'],
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

    return array_merge($defaults, ['identity_type' => 'national_id', 'nationality' => 'Kenyan']);
}

// Returns ['values' => field => value, 'saved' => section => bool, 'updated_at' => ?string].
function getStudentProfile(PDO $pdo, string $userId): array
{
    $profile = ['values' => [], 'saved' => [], 'updated_at' => null];

    foreach (PROFILE_SECTIONS as $key => $section) {
        // Table names come from the constant above, never from user input.
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

// The bundled county > constituency > ward list, loaded once per request.
function kenyaLocations(): array
{
    static $locations = null;

    if ($locations === null) {
        $json = file_get_contents(__DIR__ . '/../assets/data/kenya-locations.json');
        $locations = $json !== false ? (json_decode($json, true) ?: []) : [];
    }

    return $locations;
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
                'tribe' => $text('tribe'),
                'disability_status' => $text('disability_status'),
                'disability_details' => $text('disability_details'),
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
                 if (!in_array($clean['tribe'], $lookups['tribes'], true)) {
                $errors['tribe'] = 'Select a tribe from the list.';
            }
            
            if (!in_array($clean['disability_status'], ['none', 'physical', 'visual', 'hearing', 'intellectual', 'multiple', 'other'], true)) {
                $errors['disability_status'] = 'Select a valid disability status.';
            } elseif ($clean['disability_status'] !== 'none' && $clean['disability_details'] === '') {
                $errors['disability_details'] = 'Please explain your disability briefly.';
            } elseif (mb_strlen($clean['disability_details']) > 500) {
                $errors['disability_details'] = 'Explanation must not exceed 500 characters.';
            }
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

            $locations = kenyaLocations();

            if (!isset($locations[$clean['county']])) {
                $errors['county'] = 'Select a valid county.';
            } elseif (!isset($locations[$clean['county']][$clean['constituency']])) {
                $errors['constituency'] = 'Select a constituency in that county.';
            } elseif (!in_array($clean['ward'], $locations[$clean['county']][$clean['constituency']], true)) {
                $errors['ward'] = 'Select a ward in that constituency.';
            }
            break;

        default:
            $errors['section'] = 'Invalid form submission.';
    }

    return [$clean, $errors];
}

