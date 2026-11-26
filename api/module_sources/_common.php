<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// JSON endpoints set Content-Type themselves when needed.
// view.php deliberately does not set application/json.

require_once __DIR__ . '/../../../auth/roles.php';
require_once __DIR__ . '/../../../config/database.php';

/** Absolute path to the private upload directory for module files. */
function moduleSourcesUploadDir(): string
{
    $dir = realpath(__DIR__ . '/../../uploads/module_sources');
    if ($dir === false) {
        $dir = __DIR__ . '/../../uploads/module_sources';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Upload directory is not available.');
        }
        $dir = realpath($dir) ?: $dir;
    }
    return $dir;
}

function moduleSourcesRespond(int $status, array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function moduleSourcesContext(): array
{
    if (empty($_SESSION['authenticated']) || empty($_SESSION['user_type'])) {
        moduleSourcesRespond(401, ['ok' => false, 'message' => 'Sign in to access module sources.']);
    }

    try {
        return requireAnyRole(['student', 'block_rep', 'admin']);
    } catch (PDOException $exception) {
        error_log('Module sources user lookup failed (code ' . (string) $exception->getCode() . ').');
        moduleSourcesRespond(500, ['ok' => false, 'message' => 'Module sources are temporarily unavailable.']);
    } catch (RuntimeException $exception) {
        moduleSourcesRespond(403, ['ok' => false, 'message' => 'Your account cannot access module sources.']);
    }
}

function moduleSourcesRequireManager(array $context): void
{
    // Students may view modules for their block, but only admin / block rep may upload or edit.
    $isAdmin = in_array('admin', $context['roles'], true);
    $isBlockRep = in_array('block_rep', $context['roles'], true);
    if (!$isAdmin && !$isBlockRep) {
        moduleSourcesRespond(403, [
            'ok' => false,
            'message' => 'Students can view module sources but cannot upload or edit them. Only administrators and block representatives can manage modules.',
        ]);
    }
}

function moduleSourcesRequireCsrf(): void
{
    $sessionToken = $_SESSION['moduleSourcesCsrf'] ?? '';
    // Support header (JSON) and form field (multipart)
    $submittedToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? ''));
    if (!is_string($sessionToken) || strlen($sessionToken) !== 64
        || !hash_equals($sessionToken, $submittedToken)
    ) {
        moduleSourcesRespond(400, ['ok' => false, 'message' => 'Your session token expired. Refresh the page and try again.']);
    }
}

function moduleSourcesJsonBody(): array
{
    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody) || strlen($rawBody) > 20000) {
        moduleSourcesRespond(413, ['ok' => false, 'message' => 'The request is too large.']);
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
        moduleSourcesRespond(400, ['ok' => false, 'message' => 'The request is not valid JSON.']);
    }

    return $payload;
}

function moduleSourcesTextLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function moduleSourcesValidUrl(string $value): bool
{
    if ($value === '' || strlen($value) > 500 || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true)
        && (string) parse_url($value, PHP_URL_HOST) !== '';
}

/** Allowed MIME types for uploaded learning resources. */
function moduleSourcesAllowedMimes(): array
{
    return [
        'application/pdf' => 'pdf',
        'application/x-pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
    ];
}

/**
 * Validate and store an uploaded file.
 * Returns ['fileName' => stored name, 'originalName' => ..., 'mimeType' => ..., 'fileSize' => int]
 * or responds with an error.
 */
function moduleSourcesHandleUpload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        moduleSourcesRespond(400, ['ok' => false, 'message' => 'No file was uploaded.']);
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        moduleSourcesRespond(400, ['ok' => false, 'message' => 'The file upload failed. Please try again.']);
    }

    $maxBytes = 25 * 1024 * 1024; // 25 MB
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > $maxBytes) {
        moduleSourcesRespond(422, ['ok' => false, 'message' => 'File must be between 1 byte and 25 MB.']);
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        moduleSourcesRespond(400, ['ok' => false, 'message' => 'Invalid upload.']);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = (string) $finfo->file($tmp);
    $allowed = moduleSourcesAllowedMimes();
    if (!isset($allowed[$detectedMime])) {
        moduleSourcesRespond(422, [
            'ok' => false,
            'message' => 'Only PDF and common document/image files are allowed (PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, PNG, JPG).',
        ]);
    }

    $originalName = basename((string) ($file['name'] ?? 'file'));
    // Strip path and null bytes
    $originalName = preg_replace('/[\x00-\x1F\x7F]/', '', $originalName) ?: 'file';
    if (moduleSourcesTextLength($originalName) > 200) {
        $originalName = substr($originalName, 0, 200);
    }

    $ext = $allowed[$detectedMime];
    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;

    $destDir = moduleSourcesUploadDir();
    $destPath = $destDir . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($tmp, $destPath)) {
        moduleSourcesRespond(500, ['ok' => false, 'message' => 'Could not store the uploaded file.']);
    }
    @chmod($destPath, 0640);

    return [
        'fileName' => $storedName,
        'originalName' => $originalName,
        'mimeType' => $detectedMime,
        'fileSize' => $size,
    ];
}

function moduleSourcesDeleteFile(?string $storedName): void
{
    if ($storedName === null || $storedName === '') {
        return;
    }
    // Only allow simple filenames (no path traversal)
    if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/i', $storedName)) {
        return;
    }
    $path = moduleSourcesUploadDir() . DIRECTORY_SEPARATOR . $storedName;
    if (is_file($path)) {
        @unlink($path);
    }
}
