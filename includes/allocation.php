<?php

require_once __DIR__ . '/capacity.php';
require_once __DIR__ . '/applications.php';

// Handing out places. A department has a number of places; when one is free, it goes to the
// OLDEST waitlisted application in that department, strictly by queue number. This is the one
// place that rule lives, so every path that frees or adds a place (an acceptance, a bigger
// capacity, a placement that ends) calls this and the order can never be bypassed.

// Reserves a place for each waitlisted application, oldest first, until the department runs out
// of free places or of waitlisted students. A reserved place is a "proposed" placement: it uses
// up a place straight away, and an HR officer then confirms the supervisor and dates and sends
// the offer. Returns how many places were reserved.
//
// Call inside auditedTransaction(). $actor needs user_id and email (and optionally name); pass
// 'system' details for changes that nobody triggered by hand.
function allocateDepartment(PDO $pdo, int $departmentId, array $actor): int
{
    $settings = getDepartmentSettings($pdo, $departmentId);
    $today = date('Y-m-d');
    $free = $settings['capacity'] - departmentOccupancy($pdo, $departmentId, $today);
    $allocated = 0;

    if ($free <= 0) {
        return 0;
    }

    $next = $pdo->prepare(
        "SELECT application_id, user_id, requested_weeks, earliest_start, state_department
         FROM applications
         WHERE preferred_department_id = :department_id AND status = 'waitlisted'
         ORDER BY queue_no ASC
         LIMIT 1"
    );
    $reserve = $pdo->prepare(
        "UPDATE applications SET status = 'accepted', updated_at = :now WHERE application_id = :id AND status = 'waitlisted'"
    );
    $insert = $pdo->prepare(
        'INSERT INTO placements
            (application_id, user_id, department_id, supervisor_user_id, start_date, end_date, status,
             response_deadline, created_by, created_at, status_changed_at)
         VALUES
            (:application_id, :user_id, :department_id, :supervisor, :start_date, :end_date, :status,
             :deadline, :created_by, :created_at, :changed_at)'
    );

    while ($free > 0) {
        $next->execute(['department_id' => $departmentId]);
        $application = $next->fetch();

        if (!$application) {
            break;
        }

        // A sensible first guess; the officer can change the dates when confirming.
        $start = max($application['earliest_start'], $today);
        $end = date('Y-m-d', strtotime($start . ' +' . ((int) $application['requested_weeks'] * 7 - 1) . ' days'));
        $now = date('Y-m-d H:i:s');

        $insert->execute([
            'application_id' => $application['application_id'],
            'user_id' => $application['user_id'],
            'department_id' => $departmentId,
            'supervisor' => '',
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'proposed',
            'deadline' => date('Y-m-d H:i:s', strtotime("+{$settings['offer_response_days']} days")),
            'created_by' => (string) ($actor['user_id'] ?? ''),
            'created_at' => $now,
            'changed_at' => $now,
        ]);

        $reserve->execute(['now' => $now, 'id' => $application['application_id']]);

        if ($reserve->rowCount() !== 1) {
            throw new RuntimeException('The waitlist changed while places were being handed out.');
        }

        recordApplicationEvent($pdo, [
            'application_id' => $application['application_id'],
            'type' => 'place_reserved',
            'from' => 'waitlisted',
            'to' => 'accepted',
            'note' => 'A place has been reserved for you from ' . $start . '. You will receive an offer shortly.',
            'visibility' => 'student',
            'actor_id' => (string) ($actor['user_id'] ?? ''),
            'actor_name' => (string) ($actor['name'] ?? $actor['email'] ?? 'System'),
        ]);

        auditLog($pdo, 'placement.reserved', 'success', [
            'target_type' => 'application',
            'target_id' => $application['application_id'],
            'target_label' => $application['state_department'],
            'details' => ['department_id' => $departmentId, 'start_date' => $start, 'end_date' => $end],
        ]);

        $free--;
        $allocated++;
    }

    return $allocated;
}
