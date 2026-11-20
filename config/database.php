<?php
// config/database.php

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPassword = '';

try {
    // 1. Main Database (Your existing iaims_db)
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname=iaims_db;charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // 2. Audit Logs Database (Using your existing iaims_log)
    $auditPdo = new PDO(
        "mysql:host={$dbHost};dbname=iaims_audit;charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to connect to the database. Error: ' . $exception->getMessage());
}

// 3. Logging Helper Function
function logAction($auditPdo, $directorId, $directorName, $action) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    
    // Generate a unique hash for this specific log entry
    // We combine the current time and the action to ensure it's 100% unique
    $uniqueData = time() . $action . $directorId . microtime();
    $rowHash = hash('sha256', $uniqueData);
    
    // We insert the generated hash into the `row_hash` column
    $sql = "INSERT INTO audit_log 
            (occurred_at, actor_user_id, actor_email, actor_roles, action, outcome, ip_address, request_method, request_path, row_hash) 
            VALUES 
            (NOW(), ?, ?, 'Director', ?, 'Success', ?, 'GET', '/pages/Director/dashboard.php', ?)";
            
    $stmt = $auditPdo->prepare($sql);
    $stmt->execute([$directorId, $directorName, $action, $ip, $rowHash]);
}

?>
