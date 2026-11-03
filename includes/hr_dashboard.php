<?php

require_once __DIR__ . '/capacity.php';

// Everything the HR dashboard shows for one department, gathered here so the page stays short.

function hrDashboardStats(PDO $pdo, int $departmentId): array
{
    $queue = departmentQueueCounts($pdo, $departmentId);
    $capacity = departmentCapacity($pdo, $departmentId);

    // How long the oldest unreviewed application has been waiting, in whole days.
    $oldestDays = 0;

    if ($queue['oldest_waiting'] !== '') {
        $oldestDays = (int) floor((time() - strtotime($queue['oldest_waiting'])) / 86400);
    }

    return [
        'queue' => $queue,
        'capacity' => $capacity,
        'oldest_waiting_days' => $oldestDays,
        'awaiting_review' => $queue['submitted'] + $queue['under_review'],
    ];
}
