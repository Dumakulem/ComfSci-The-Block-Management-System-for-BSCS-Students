<?php

declare(strict_types=1);

/**
 * Authenticated viewer / downloader for module source files (PDFs, documents, etc.).
 * Access is restricted to the same rules as the list endpoint.
 */

require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Method not allowed');
}

// Use a soft context check that can still emit JSON on auth failure
$context = moduleSourcesContext();

$rawId = $_GET['id'] ?? null;
if ((!is_string($rawId) && !is_int($rawId)) || !ctype_digit((string) $rawId) || (int) $rawId < 1) {
    http_response_code(400);
    exit('Invalid module id');
}
$moduleId = (int) $rawId;
$forceDownload = isset($_GET['download']) && (string) $_GET['download'] === '1';

try {
    $pdo = db();
    $statement = $pdo->prepare(
        'SELECT moduleId, blockId, fileName, originalName, mimeType, fileSize
         FROM moduleSources WHERE moduleId = ? LIMIT 1'
    );
    $statement->execute([$moduleId]);
    $module = $statement->fetch();
} catch (PDOException $exception) {
    error_log('Module file view query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load file');
}

if (!$module || empty($module['fileName'])) {
    http_response_code(404);
    exit('File not found');
}

$isAdmin = in_array('admin', $context['roles'], true);
if (!$isAdmin) {
    $userBlock = (int) ($context['blockID'] ?? 0);
    if ($userBlock < 1 || (int) $module['blockId'] !== $userBlock) {
        http_response_code(403);
        exit('You do not have access to this file');
    }
}

$storedName = (string) $module['fileName'];
if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/i', $storedName)) {
    http_response_code(404);
    exit('File not found');
}

$path = moduleSourcesUploadDir() . DIRECTORY_SEPARATOR . $storedName;
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('File not found');
}

$mime = (string) ($module['mimeType'] ?: 'application/octet-stream');
$original = (string) ($module['originalName'] ?: 'resource');
// Sanitize for Content-Disposition
$safeName = preg_replace('/[^\w.\- ()\[\]]+/u', '_', $original) ?: 'resource';

$fileSize = filesize($path);
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Content-Length: ' . (string) $fileSize);

if ($forceDownload || strpos($mime, 'pdf') === false) {
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
} else {
    // Inline so the browser can display the PDF
    header('Content-Disposition: inline; filename="' . $safeName . '"');
}

// Clear any previous output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($path);
exit;
