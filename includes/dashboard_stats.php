<?php

require_once __DIR__ . '/audit.php';

// Everything the admin dashboard shows, gathered in one place so the page stays short.

function adminDashboardStats(PDO $pdo): array
{
    $now = new DateTimeImmutable();
    $audit = auditTable();

    // Staff accounts by status (archived ones are not counted as "staff" on the dashboard).
    $statuses = $pdo->query(
        "SELECT u.status, COUNT(DISTINCT u.user_id) AS total
         FROM users u
         INNER JOIN user_roles ur ON ur.user_id = u.user_id
         INNER JOIN roles r ON r.role_id = ur.role_id
         WHERE r.name IN ('ADMIN', 'HR_ADMIN', 'HR', 'SUPERVISOR')
         GROUP BY u.status"
    )->fetchAll();

    $staff = ['invited' => 0, 'active' => 0, 'suspended' => 0, 'archived' => 0];

    foreach ($statuses as $row) {
        $staff[$row['status']] = (int) $row['total'];
    }

    // Invites still valid but unused after two days: someone probably never got the link.
    $stale = $pdo->prepare(
        "SELECT COUNT(*) FROM user_invites WHERE status = 'pending' AND created_at < :cutoff AND expires_at > :now"
    );
    $stale->execute([
        'cutoff' => $now->modify('-48 hours')->format('Y-m-d H:i:s'),
        'now' => $now->format('Y-m-d H:i:s'),
    ]);

    $failed = $pdo->prepare(
        "SELECT COUNT(*) AS total, COUNT(DISTINCT ip_address) AS ips, MAX(occurred_at) AS latest
         FROM {$audit} WHERE action = 'auth.login_failed' AND occurred_at >= :since"
    );
    $failed->execute(['since' => $now->modify('-24 hours')->format('Y-m-d H:i:s')]);
    $failedRow = $failed->fetch();

    $newDevices = $pdo->prepare(
        "SELECT actor_email, occurred_at, details FROM {$audit}
         WHERE action = 'auth.new_device_login' AND occurred_at >= :since
         ORDER BY audit_id DESC LIMIT 3"
    );
    $newDevices->execute(['since' => $now->modify('-7 days')->format('Y-m-d H:i:s')]);

    $recent = $pdo->query(
        "SELECT audit_id, occurred_at, actor_email, actor_roles, action, outcome, target_label
         FROM {$audit} ORDER BY audit_id DESC LIMIT 6"
    )->fetchAll();

    return [
        'staff' => $staff,
        'stale_invites' => (int) $stale->fetchColumn(),
        'failed_logins' => (int) ($failedRow['total'] ?? 0),
        'failed_ips' => (int) ($failedRow['ips'] ?? 0),
        'failed_latest' => (string) ($failedRow['latest'] ?? ''),
        'new_devices' => $newDevices->fetchAll(),
        'recent' => $recent,
    ];
}
