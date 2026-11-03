<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/allocation.php';

// Department capacity and settings. Each state department has a number of places that can be in
// use at once. A place is in use from the moment it is reserved for a student until the
// placement's end date (or the day the student leaves early), so a place opens by itself when an
// end date passes: nothing has to run on a schedule for that.
//
// HR officers can read all of this; changing it is for the department's HR admin. Every change is
// written together with its audit entry.

const CAPACITY_MAX = 500;

// Placement statuses that use up a place until their end date.
const OCCUPYING_STATUSES = "('proposed', 'offered', 'confirmed')";

function attachmentMinWeeks(): int
{
    return defined('ATTACHMENT_MIN_WEEKS') ? (int) ATTACHMENT_MIN_WEEKS : 8;
}

function attachmentMaxWeeks(): int
{
    return defined('ATTACHMENT_MAX_WEEKS') ? (int) ATTACHMENT_MAX_WEEKS : 12;
}

/* ---------- Reading ---------- */

function departmentSettingsDefaults(): array
{
    return [
        'capacity' => 0,
        'accepting_applications' => 1,
        'offer_response_days' => 3,
        'interest_check_days' => 30,
        'max_open_reviews' => 5,
    ];
}

// The department's settings, or the defaults if it has never saved any.
function getDepartmentSettings(PDO $pdo, int $departmentId): array
{
    $statement = $pdo->prepare(
        'SELECT capacity, accepting_applications, offer_response_days, interest_check_days, max_open_reviews
         FROM department_settings WHERE department_id = :id LIMIT 1'
    );
    $statement->execute(['id' => $departmentId]);
    $row = $statement->fetch();

    if (!$row) {
        return departmentSettingsDefaults();
    }

    return array_map('intval', $row);
}

// Places in use today. "Today" is passed in (Nairobi time from PHP) rather than taken from the
// database server's clock.
function departmentOccupancy(PDO $pdo, int $departmentId, ?string $today = null): int
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM placements
         WHERE department_id = :department_id AND status IN ' . OCCUPYING_STATUSES . ' AND end_date >= :today'
    );
    $statement->execute(['department_id' => $departmentId, 'today' => $today ?? date('Y-m-d')]);

    return (int) $statement->fetchColumn();
}

// Capacity, places in use and places free, for one department.
function departmentCapacity(PDO $pdo, int $departmentId): array
{
    $settings = getDepartmentSettings($pdo, $departmentId);
    $inUse = departmentOccupancy($pdo, $departmentId);

    return [
        'capacity' => $settings['capacity'],
        'in_use' => $inUse,
        'free' => max(0, $settings['capacity'] - $inUse),
        'over' => max(0, $inUse - $settings['capacity']),
        'settings' => $settings,
    ];
}

// What is waiting in the department, for the dashboard and the capacity page.
function departmentQueueCounts(PDO $pdo, int $departmentId): array
{
    $counts = ['submitted' => 0, 'under_review' => 0, 'waitlisted' => 0, 'accepted' => 0, 'rejected' => 0, 'withdrawn' => 0];

    $statement = $pdo->prepare(
        'SELECT status, COUNT(*) AS total FROM applications WHERE preferred_department_id = :id GROUP BY status'
    );
    $statement->execute(['id' => $departmentId]);

    foreach ($statement->fetchAll() as $row) {
        $counts[$row['status']] = (int) $row['total'];
    }

    // The oldest application still waiting for review: how far behind the queue is.
    $oldest = $pdo->prepare(
        "SELECT MIN(submitted_at) FROM applications WHERE preferred_department_id = :id AND status = 'submitted'"
    );
    $oldest->execute(['id' => $departmentId]);
    $counts['oldest_waiting'] = (string) ($oldest->fetchColumn() ?: '');

    $offers = $pdo->prepare(
        "SELECT COUNT(*) AS pending,
                COALESCE(SUM(CASE WHEN response_deadline <= :soon THEN 1 ELSE 0 END), 0) AS ending_soon
         FROM placements WHERE department_id = :id AND status = 'offered'"
    );
    $offers->execute(['id' => $departmentId, 'soon' => (new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s')]);
    $offerRow = $offers->fetch();

    $counts['offers_pending'] = (int) ($offerRow['pending'] ?? 0);
    $counts['offers_ending_soon'] = (int) ($offerRow['ending_soon'] ?? 0);

    $running = $pdo->prepare(
        "SELECT COUNT(*) FROM placements
         WHERE department_id = :id AND status = 'confirmed' AND start_date <= :today AND end_date >= :today2"
    );
    $running->execute(['id' => $departmentId, 'today' => date('Y-m-d'), 'today2' => date('Y-m-d')]);
    $counts['running'] = (int) $running->fetchColumn();

    $reserved = $pdo->prepare("SELECT COUNT(*) FROM placements WHERE department_id = :id AND status = 'proposed'");
    $reserved->execute(['id' => $departmentId]);
    $counts['proposed'] = (int) $reserved->fetchColumn();

    return $counts;
}

/* ---------- Checking input ---------- */

// Returns [cleanValues, errorMessages] for the settings form.
function validateDepartmentSettings(array $input): array
{
    $number = static fn (string $key): int => (isset($input[$key]) && is_scalar($input[$key]) && ctype_digit((string) $input[$key]))
        ? (int) $input[$key]
        : -1;

    $clean = [
        'capacity' => $number('capacity'),
        'accepting_applications' => !empty($input['accepting_applications']) ? 1 : 0,
        'offer_response_days' => $number('offer_response_days'),
        'interest_check_days' => $number('interest_check_days'),
        'max_open_reviews' => $number('max_open_reviews'),
    ];

    $errors = [];

    if ($clean['capacity'] < 0 || $clean['capacity'] > CAPACITY_MAX) {
        $errors['capacity'] = 'Capacity must be a whole number from 0 to ' . CAPACITY_MAX . '.';
    }

    if ($clean['offer_response_days'] < 1 || $clean['offer_response_days'] > 14) {
        $errors['offer_response_days'] = 'Days to answer an offer must be between 1 and 14.';
    }

    if ($clean['interest_check_days'] < 7 || $clean['interest_check_days'] > 180) {
        $errors['interest_check_days'] = 'Waitlist check-ins must be between 7 and 180 days apart.';
    }

    if ($clean['max_open_reviews'] < 1 || $clean['max_open_reviews'] > 20) {
        $errors['max_open_reviews'] = 'Open reviews per officer must be between 1 and 20.';
    }

    return [$clean, $errors];
}

/* ---------- Changing ---------- */

// Saves the department's capacity and settings. HR admins only, and only for their own
// department. Lowering capacity never removes anyone: new places simply wait until enough
// placements have ended.
function saveDepartmentSettings(PDO $pdo, array $actor, array $input): array
{
    if (!in_array('HR_ADMIN', $actor['roles'], true) || $actor['department_id'] === 0) {
        auditLogSafe($pdo, 'department.settings_denied', 'denied', [
            'target_type' => 'department',
            'target_id' => (string) $actor['department_id'],
        ]);

        return ['success' => false, 'errors' => ['Only an HR admin can change capacity and settings.']];
    }

    [$clean, $errors] = validateDepartmentSettings($input);

    if ($errors) {
        return ['success' => false, 'errors' => array_values($errors)];
    }

    $departmentId = $actor['department_id'];
    $before = getDepartmentSettings($pdo, $departmentId);
    $changes = [];

    foreach ($clean as $field => $value) {
        if ((int) $before[$field] !== $value) {
            $changes[$field] = ['from' => (int) $before[$field], 'to' => $value];
        }
    }

    if (!$changes) {
        return ['success' => false, 'errors' => ['Nothing to change.']];
    }

    $departmentName = currentHrDepartment($pdo)['department_name'];
    $allocated = 0;

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($actor, $departmentId, $departmentName, $clean, $changes, &$allocated) {
            $pdo->prepare(
                'INSERT INTO department_settings
                    (department_id, capacity, accepting_applications, offer_response_days, interest_check_days, max_open_reviews, updated_at)
                 VALUES (:department_id, :capacity, :accepting, :offer_days, :interest_days, :max_reviews, :updated_at)
                 ON DUPLICATE KEY UPDATE capacity = VALUES(capacity), accepting_applications = VALUES(accepting_applications),
                    offer_response_days = VALUES(offer_response_days), interest_check_days = VALUES(interest_check_days),
                    max_open_reviews = VALUES(max_open_reviews), updated_at = VALUES(updated_at)'
            )->execute([
                'department_id' => $departmentId,
                'capacity' => $clean['capacity'],
                'accepting' => $clean['accepting_applications'],
                'offer_days' => $clean['offer_response_days'],
                'interest_days' => $clean['interest_check_days'],
                'max_reviews' => $clean['max_open_reviews'],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // More places (or none in use) may mean waitlisted students can now be given one.
            $allocated = allocateDepartment($pdo, $departmentId, ['user_id' => $actor['user_id'], 'email' => $actor['email']]);

            auditLog($pdo, 'department.settings_changed', 'success', [
                'target_type' => 'department',
                'target_id' => (string) $departmentId,
                'target_label' => $departmentName,
                'details' => ['changes' => $changes, 'places_reserved' => $allocated],
            ]);
        });
    } catch (Throwable $exception) {
        error_log('IAIMS department settings error: ' . $exception->getMessage());

        return ['success' => false, 'errors' => ['The settings could not be saved. Please try again.']];
    }

    return [
        'success' => true,
        'message' => 'Capacity and settings saved.'
            . ($allocated > 0 ? " {$allocated} waitlisted student(s) were given a place, oldest first." : ''),
    ];
}
