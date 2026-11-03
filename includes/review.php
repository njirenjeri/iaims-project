<?php

require_once __DIR__ . '/applications.php';
require_once __DIR__ . '/allocation.php';

// HR's side of an application. Fairness is built in:
//   - Reviews are taken strictly oldest first ("Review next"), so nobody can pick favourites.
//   - A reviewer can hold only a few at once, so nobody hoards the queue.
//   - Only the reviewer can decide. An HR admin can step in, but jumping the queue or taking over
//     someone else's application needs a reason, and both are recorded.
//   - Accepting never hands out a place directly: the approved application joins the waitlist and
//     places go to the oldest waitlisted application (see allocation.php).
//
// Every change is written together with its history entry and its audit entry.

/* ---------- Helpers ---------- */

function rejectionReasons(): array
{
    return lookupList('rejection_reasons');
}

// The officer's name as it should appear in an application's history.
function reviewerName(PDO $pdo, string $userId, string $fallbackEmail): string
{
    $statement = $pdo->prepare('SELECT first_name, other_names FROM staff_profiles WHERE user_id = :id LIMIT 1');
    $statement->execute(['id' => $userId]);
    $row = $statement->fetch();

    $name = $row ? trim($row['first_name'] . ' ' . $row['other_names']) : '';

    return $name !== '' ? $name : $fallbackEmail;
}

function countOpenReviews(PDO $pdo, string $userId, int $departmentId): int
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM applications
         WHERE preferred_department_id = :department_id AND status = 'under_review' AND reviewer_user_id = :user_id"
    );
    $statement->execute(['department_id' => $departmentId, 'user_id' => $userId]);

    return (int) $statement->fetchColumn();
}

function cleanNote($value, int $max = 500): string
{
    return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
}

// Records a refusal (permission or rule) and returns the failure for the page to show.
function reviewRefused(PDO $pdo, string $attempted, string $applicationId, string $reason, string $message): array
{
    auditLogSafe($pdo, 'application.action_denied', 'denied', [
        'target_type' => 'application',
        'target_id' => $applicationId,
        'details' => ['attempted' => $attempted, 'reason' => $reason],
    ]);

    return ['success' => false, 'message' => $message];
}

/* ---------- Taking an application from the queue ---------- */

// Gives the officer the oldest application still waiting for review in their department.
// Success includes application_id. 'empty' => true means nothing is waiting.
function startNextReview(PDO $pdo, array $actor): array
{
    $settings = getDepartmentSettings($pdo, $actor['department_id']);
    $open = countOpenReviews($pdo, $actor['user_id'], $actor['department_id']);

    if ($open >= $settings['max_open_reviews']) {
        return ['success' => false, 'message' => "You already have {$open} application(s) open. Decide or release one before taking another."];
    }

    $name = reviewerName($pdo, $actor['user_id'], $actor['email']);

    try {
        $applicationId = auditedTransaction($pdo, function (PDO $pdo) use ($actor, $name) {
            $next = $pdo->prepare(
                "SELECT application_id, queue_no FROM applications
                 WHERE preferred_department_id = :department_id AND status = 'submitted'
                 ORDER BY queue_no ASC LIMIT 1"
            );
            $next->execute(['department_id' => $actor['department_id']]);
            $row = $next->fetch();

            if (!$row) {
                return '';
            }

            // The status condition keeps two officers from taking the same application.
            $claim = $pdo->prepare(
                "UPDATE applications SET status = 'under_review', reviewer_user_id = :reviewer, updated_at = :now
                 WHERE application_id = :id AND status = 'submitted'"
            );
            $claim->execute(['reviewer' => $actor['user_id'], 'now' => date('Y-m-d H:i:s'), 'id' => $row['application_id']]);

            if ($claim->rowCount() !== 1) {
                throw new RuntimeException('The application was taken by someone else.');
            }

            recordApplicationEvent($pdo, [
                'application_id' => $row['application_id'],
                'type' => 'review_started',
                'from' => 'submitted',
                'to' => 'under_review',
                'note' => 'Review started.',
                'visibility' => 'student',
                'actor_id' => $actor['user_id'],
                'actor_name' => $name,
            ]);

            auditLog($pdo, 'application.review_started', 'success', [
                'target_type' => 'application',
                'target_id' => $row['application_id'],
                'details' => ['queue_no' => (int) $row['queue_no']],
            ]);

            return $row['application_id'];
        });
    } catch (Throwable $exception) {
        error_log('IAIMS review start error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Could not take the next application. Please try again.'];
    }

    if ($applicationId === '') {
        return ['success' => false, 'empty' => true, 'message' => 'No applications are waiting for review.'];
    }

    return ['success' => true, 'application_id' => $applicationId, 'message' => 'This is the oldest waiting application. It is now yours to review.'];
}

// HR admins only: take a SPECIFIC application, either one that is still waiting (out of order
// needs a reason) or one another officer is reviewing (a takeover needs a reason).
function takeApplication(PDO $pdo, array $actor, string $applicationId, string $reason): array
{
    if (!in_array('HR_ADMIN', $actor['roles'], true)) {
        return reviewRefused($pdo, 'take', $applicationId, 'not_hr_admin', 'Only an HR admin can take a specific application.');
    }

    $application = getApplicationForHr($pdo, $applicationId, $actor['department_id']);

    if ($application === null) {
        return ['success' => false, 'message' => 'That application was not found.'];
    }

    if (!in_array($application['status'], ['submitted', 'under_review'], true)) {
        return ['success' => false, 'message' => 'Only an application that is waiting or under review can be taken.'];
    }

    if ($application['status'] === 'under_review' && $application['reviewer_user_id'] === $actor['user_id']) {
        return ['success' => false, 'message' => 'You already have this application.'];
    }

    $reason = cleanNote($reason, 200);

    // Is it the oldest one still waiting? If not, or if it belongs to someone else, say why.
    $oldest = $pdo->prepare("SELECT MIN(queue_no) FROM applications WHERE preferred_department_id = :id AND status = 'submitted'");
    $oldest->execute(['id' => $actor['department_id']]);
    $outOfOrder = $application['status'] === 'submitted' && (int) $application['queue_no'] > (int) $oldest->fetchColumn();
    $takeover = $application['status'] === 'under_review';

    if (($outOfOrder || $takeover) && mb_strlen($reason) < 3) {
        return ['success' => false, 'message' => $takeover
            ? 'To take over another officer\'s application, give a reason.'
            : 'This is not the oldest waiting application. To review it first, give a reason.'];
    }

    $settings = getDepartmentSettings($pdo, $actor['department_id']);

    if (countOpenReviews($pdo, $actor['user_id'], $actor['department_id']) >= $settings['max_open_reviews']) {
        return ['success' => false, 'message' => 'You already have the maximum number of open reviews. Decide or release one first.'];
    }

    $name = reviewerName($pdo, $actor['user_id'], $actor['email']);

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($actor, $application, $reason, $name, $outOfOrder, $takeover) {
            $claim = $pdo->prepare(
                'UPDATE applications SET status = :to_status, reviewer_user_id = :reviewer, updated_at = :now
                 WHERE application_id = :id AND status = :from_status AND reviewer_user_id = :old_reviewer'
            );
            $claim->execute([
                'to_status' => 'under_review',
                'reviewer' => $actor['user_id'],
                'now' => date('Y-m-d H:i:s'),
                'id' => $application['application_id'],
                'from_status' => $application['status'],
                'old_reviewer' => $application['reviewer_user_id'],
            ]);

            if ($claim->rowCount() !== 1) {
                throw new RuntimeException('The application changed while you were working.');
            }

            recordApplicationEvent($pdo, [
                'application_id' => $application['application_id'],
                'type' => $takeover ? 'review_reassigned' : 'review_started',
                'from' => $application['status'],
                'to' => 'under_review',
                'note' => ($takeover ? 'Review taken over by an HR admin.' : 'Review started by an HR admin.') . ($reason !== '' ? ' Reason: ' . $reason : ''),
                'visibility' => 'internal',
                'actor_id' => $actor['user_id'],
                'actor_name' => $name,
            ]);

            auditLog($pdo, $takeover ? 'application.review_reassigned' : 'application.review_started', 'success', [
                'target_type' => 'application',
                'target_id' => $application['application_id'],
                'details' => ['queue_no' => (int) $application['queue_no'], 'out_of_order' => $outOfOrder, 'reason' => $reason, 'previous_reviewer' => $application['reviewer_user_id']],
            ]);
        });
    } catch (Throwable $exception) {
        error_log('IAIMS take application error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'The application could not be taken. Reload the page and try again.'];
    }

    return ['success' => true, 'message' => 'The application is now yours to review.'];
}

// Returns an application to the queue, to its original place in line. The reviewer can do this,
// and so can an HR admin.
function releaseReview(PDO $pdo, array $actor, string $applicationId): array
{
    $application = getApplicationForHr($pdo, $applicationId, $actor['department_id']);

    if ($application === null) {
        return ['success' => false, 'message' => 'That application was not found.'];
    }

    if ($application['status'] !== 'under_review') {
        return ['success' => false, 'message' => 'That application is not under review.'];
    }

    $isReviewer = $application['reviewer_user_id'] === $actor['user_id'];

    if (!$isReviewer && !in_array('HR_ADMIN', $actor['roles'], true)) {
        return reviewRefused($pdo, 'release', $applicationId, 'not_reviewer', 'Only the reviewer or an HR admin can release it.');
    }

    $name = reviewerName($pdo, $actor['user_id'], $actor['email']);

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($actor, $application, $name) {
            $update = $pdo->prepare(
                "UPDATE applications SET status = 'submitted', reviewer_user_id = '', updated_at = :now
                 WHERE application_id = :id AND status = 'under_review'"
            );
            $update->execute(['now' => date('Y-m-d H:i:s'), 'id' => $application['application_id']]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The application changed while you were working.');
            }

            recordApplicationEvent($pdo, [
                'application_id' => $application['application_id'],
                'type' => 'review_released',
                'from' => 'under_review',
                'to' => 'submitted',
                'note' => 'Returned to the queue.',
                'visibility' => 'internal',
                'actor_id' => $actor['user_id'],
                'actor_name' => $name,
            ]);

            auditLog($pdo, 'application.review_released', 'success', [
                'target_type' => 'application',
                'target_id' => $application['application_id'],
                'details' => ['previous_reviewer' => $application['reviewer_user_id']],
            ]);
        });
    } catch (Throwable $exception) {
        error_log('IAIMS release review error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'The application could not be released. Reload the page and try again.'];
    }

    return ['success' => true, 'message' => 'Returned to the queue, in its original place.'];
}

/* ---------- Deciding ---------- */

// $decision is 'accept' or 'reject'. Only the reviewer can decide. Rejecting needs a reason from
// the fixed list; accepting adds the application to the waitlist and then hands out any free
// places, oldest first, so the result is a place reserved or a position on the waitlist.
function decideApplication(PDO $pdo, array $actor, string $applicationId, string $decision, string $reasonCode, string $note): array
{
    if (!in_array($decision, ['accept', 'reject'], true)) {
        return ['success' => false, 'message' => 'Invalid request.'];
    }

    $application = getApplicationForHr($pdo, $applicationId, $actor['department_id']);

    if ($application === null) {
        return ['success' => false, 'message' => 'That application was not found.'];
    }

    if ($application['status'] !== 'under_review') {
        return ['success' => false, 'message' => 'Only an application under review can be decided.'];
    }

    if ($application['reviewer_user_id'] !== $actor['user_id']) {
        return reviewRefused($pdo, $decision, $applicationId, 'not_reviewer', 'Only the officer reviewing this application can decide it. An HR admin can take it over first.');
    }

    $note = cleanNote($note);
    $name = reviewerName($pdo, $actor['user_id'], $actor['email']);
    $actorForEvents = ['user_id' => $actor['user_id'], 'email' => $actor['email'], 'name' => $name];

    if ($decision === 'reject') {
        $reasons = rejectionReasons();

        if (!isset($reasons[$reasonCode])) {
            return ['success' => false, 'message' => 'Choose a reason for the rejection.'];
        }

        if ($reasonCode === 'other' && mb_strlen($note) < 3) {
            return ['success' => false, 'message' => 'Explain the reason in the note.'];
        }

        try {
            auditedTransaction($pdo, function (PDO $pdo) use ($actor, $application, $reasonCode, $reasons, $note, $name) {
                $update = $pdo->prepare(
                    "UPDATE applications SET status = 'rejected', updated_at = :now
                     WHERE application_id = :id AND status = 'under_review' AND reviewer_user_id = :reviewer"
                );
                $update->execute(['now' => date('Y-m-d H:i:s'), 'id' => $application['application_id'], 'reviewer' => $actor['user_id']]);

                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('The application changed while you were working.');
                }

                recordApplicationEvent($pdo, [
                    'application_id' => $application['application_id'],
                    'type' => 'decision',
                    'from' => 'under_review',
                    'to' => 'rejected',
                    'reason_code' => $reasonCode,
                    'note' => $note,
                    'visibility' => 'student',
                    'actor_id' => $actor['user_id'],
                    'actor_name' => $name,
                ]);

                auditLog($pdo, 'application.rejected', 'success', [
                    'target_type' => 'application',
                    'target_id' => $application['application_id'],
                    'details' => ['queue_no' => (int) $application['queue_no'], 'reason' => $reasonCode],
                ]);
            });
        } catch (Throwable $exception) {
            error_log('IAIMS reject error: ' . $exception->getMessage());

            return ['success' => false, 'message' => 'The decision could not be saved. Reload the page and try again.'];
        }

        return ['success' => true, 'message' => 'Application rejected. The student can see the reason.'];
    }

    // Accept
    $finalStatus = 'waitlisted';
    $position = 0;

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($actor, $application, $note, $name, $actorForEvents, &$finalStatus, &$position) {
            $now = date('Y-m-d H:i:s');

            // Approved: it joins the department's waitlist, oldest first.
            $update = $pdo->prepare(
                "UPDATE applications SET status = 'waitlisted', interest_confirmed_at = :confirmed, updated_at = :now
                 WHERE application_id = :id AND status = 'under_review' AND reviewer_user_id = :reviewer"
            );
            $update->execute(['confirmed' => $now, 'now' => $now, 'id' => $application['application_id'], 'reviewer' => $actor['user_id']]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The application changed while you were working.');
            }

            recordApplicationEvent($pdo, [
                'application_id' => $application['application_id'],
                'type' => 'decision',
                'from' => 'under_review',
                'to' => 'waitlisted',
                'note' => $note !== '' ? $note : 'Your application was approved.',
                'visibility' => 'student',
                'actor_id' => $actor['user_id'],
                'actor_name' => $name,
            ]);

            // Hand out any free places to the oldest waitlisted applications. That may or may not
            // include this one, if older applications are already waiting.
            allocateDepartment($pdo, $actor['department_id'], $actorForEvents);

            $check = $pdo->prepare('SELECT status FROM applications WHERE application_id = :id');
            $check->execute(['id' => $application['application_id']]);
            $finalStatus = (string) $check->fetchColumn();

            if ($finalStatus === 'waitlisted') {
                $ahead = $pdo->prepare(
                    "SELECT COUNT(*) FROM applications
                     WHERE preferred_department_id = :department_id AND status = 'waitlisted' AND queue_no < :queue_no"
                );
                $ahead->execute(['department_id' => $actor['department_id'], 'queue_no' => $application['queue_no']]);
                $position = (int) $ahead->fetchColumn() + 1;

                recordApplicationEvent($pdo, [
                    'application_id' => $application['application_id'],
                    'type' => 'waitlisted',
                    'to' => 'waitlisted',
                    'note' => 'All places are in use, so you are on the waitlist at position ' . $position . '. You will be offered a place when one opens.',
                    'visibility' => 'student',
                    'actor_id' => $actor['user_id'],
                    'actor_name' => $name,
                ]);
            }

            auditLog($pdo, 'application.accepted', 'success', [
                'target_type' => 'application',
                'target_id' => $application['application_id'],
                'details' => ['queue_no' => (int) $application['queue_no'], 'outcome' => $finalStatus, 'waitlist_position' => $position],
            ]);
        });
    } catch (Throwable $exception) {
        error_log('IAIMS accept error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'The decision could not be saved. Reload the page and try again.'];
    }

    return [
        'success' => true,
        'message' => $finalStatus === 'accepted'
            ? 'Accepted, and a place has been reserved. Confirm the supervisor and dates to send the offer.'
            : "Accepted. All places are in use, so the student is on the waitlist at position {$position}.",
    ];
}

/* ---------- Notes ---------- */

// An internal note on an application, for HR only. The audit entry records that a note was
// added and how long it was, never its text.
function addApplicationNote(PDO $pdo, array $actor, string $applicationId, string $note): array
{
    $application = getApplicationForHr($pdo, $applicationId, $actor['department_id']);

    if ($application === null) {
        return ['success' => false, 'message' => 'That application was not found.'];
    }

    $note = cleanNote($note, 1000);

    if (mb_strlen($note) < 3) {
        return ['success' => false, 'message' => 'Write the note first.'];
    }

    $name = reviewerName($pdo, $actor['user_id'], $actor['email']);

    try {
        auditedTransaction($pdo, function (PDO $pdo) use ($actor, $application, $note, $name) {
            recordApplicationEvent($pdo, [
                'application_id' => $application['application_id'],
                'type' => 'note',
                'note' => $note,
                'visibility' => 'internal',
                'actor_id' => $actor['user_id'],
                'actor_name' => $name,
            ]);

            auditLog($pdo, 'application.note_added', 'success', [
                'target_type' => 'application',
                'target_id' => $application['application_id'],
                'details' => ['length' => mb_strlen($note)],
            ]);
        });
    } catch (Throwable $exception) {
        error_log('IAIMS note error: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'The note could not be saved. Please try again.'];
    }

    return ['success' => true, 'message' => 'Note added.'];
}
