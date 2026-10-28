<?php

// Usage (from the project folder):  php scripts/verify_audit_chain.php
//
// Checks every audit entry against its HMAC and the previous entry's hash, then prints the
// newest entry's id and hash. Record that anchor somewhere the application cannot write to
// (a separate machine, a printed log): it is the only thing that reveals entries deleted
// from the END of the log. Schedule this with cron or Windows Task Scheduler.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script runs from the command line only.');
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

try {
    $result = verifyAuditChain($pdo);
    $anchor = latestAuditAnchor($pdo);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Could not verify the audit log: ' . $exception->getMessage() . PHP_EOL);
    exit(2);
}

if ($result['valid']) {
    echo "OK: {$result['checked']} entries verified." . PHP_EOL;
} else {
    echo "FAILED: the chain is broken at entry {$result['broken_at']} "
        . "({$result['checked']} entries were fine before it)." . PHP_EOL;
}

echo "Anchor: entry {$anchor['audit_id']}, hash {$anchor['row_hash']}" . PHP_EOL;

exit($result['valid'] ? 0 : 1);
