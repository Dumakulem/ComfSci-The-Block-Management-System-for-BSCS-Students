<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function sendJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function requireModuleSession(): array
{
    $authenticated = !empty($_SESSION['authenticated']);
    $rawUserId = $_SESSION['user_id'] ?? null;
    $userType = strtolower(trim((string) ($_SESSION['user_type'] ?? '')));

    $validId = is_int($rawUserId) || (is_string($rawUserId) && ctype_digit($rawUserId));
    if (!$authenticated || !$validId || !in_array($userType, ['admin', 'student'], true)) {
        sendJson(['ok' => false, 'message' => 'Unauthorized: active session required.'], 401);
    }

    $userId = (int) $rawUserId;
    $role = $userType === 'admin' ? 'admin' : 'student';
    $blockId = null;
    $blockName = '';

    if ($userType === 'student') {
        $stmt = db()->prepare(
            'SELECT s.isClassRep, s.blockID, b.classBlock
             FROM tblstudent s
             LEFT JOIN tblblock b ON b.blockID = s.blockID
             WHERE s.studentID = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $student = $stmt->fetch();

        if (!$student) {
            sendJson(['ok' => false, 'message' => 'Student record not found.'], 401);
        }

        if ((int) ($student['isClassRep'] ?? 0) === 1) {
            $role = 'blockrep';
        }
        if ($student['blockID'] !== null) {
            $blockId = (int) $student['blockID'];
        }
        if (!empty($student['classBlock'])) {
            $blockName = (string) $student['classBlock'];
        }
    }

    return [
        'userId'    => $userId,
        'userType'  => $userType,
        'role'      => $role,
        'userName'  => (string) ($_SESSION['user_name'] ?? ''),
        'userEmail' => (string) ($_SESSION['user_email'] ?? ''),
        'blockId'   => $blockId,
        'blockName' => $blockName,
    ];
}

function normalizeResourceUrl(string $rawUrl): ?string
{
    $url = trim($rawUrl);
    if ($url === '' || mb_strlen($url) > 500) {
        return null;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }
    return $url;
}
