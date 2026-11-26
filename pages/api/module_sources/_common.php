<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../../auth/roles.php';
require_once __DIR__ . '/../../../config/database.php';

function moduleSourcesRespond(int $status, array $payload): void
{
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
    if (!in_array('admin', $context['roles'], true)
        && !in_array('block_rep', $context['roles'], true)
    ) {
        moduleSourcesRespond(403, ['ok' => false, 'message' => 'Only administrators and block representatives can manage modules.']);
    }
}

function moduleSourcesRequireCsrf(): void
{
    $sessionToken = $_SESSION['moduleSourcesCsrf'] ?? '';
    $submittedToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
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
