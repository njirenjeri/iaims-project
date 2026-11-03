<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/applications.php';

// Streams one application document to an HR officer or admin of the SAME department. Documents
// sit in storage/ with no public address; this is the only way to read them, and every opening
// is recorded in the audit log.
$department = requireHrAccess($pdo);

$documentId = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;

$statement = $pdo->prepare(
    'SELECT d.document_id, d.application_id, d.document_type, d.original_name, d.stored_name
     FROM application_documents d
     INNER JOIN applications a ON a.application_id = d.application_id
     WHERE d.document_id = :id AND a.preferred_department_id = :department_id
     LIMIT 1'
);
$statement->execute(['id' => $documentId, 'department_id' => $department['department_id']]);
$document = $statement->fetch();

// Unknown ids and other departments' documents look the same.
if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

// The path is built from values we generated ourselves, but check it stays inside storage anyway.
$base = realpath(STORAGE_PATH . '/applications');
$path = realpath(STORAGE_PATH . '/applications/' . $document['application_id'] . '/' . $document['stored_name']);

if ($base === false || $path === false || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    error_log('IAIMS document missing on disk: ' . $document['document_id']);
    http_response_code(404);
    exit('The file could not be found.');
}

auditLogSafe($pdo, 'document.viewed', 'success', [
    'target_type' => 'application',
    'target_id' => $document['application_id'],
    'details' => ['document' => $document['document_type']],
]);

$filename = preg_replace('/[^A-Za-z0-9._ -]/', '_', $document['original_name']);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
