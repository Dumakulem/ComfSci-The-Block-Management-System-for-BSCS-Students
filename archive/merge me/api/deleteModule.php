<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$session = requireModuleSession();
if ($session['role'] === 'student') {
    sendJson(['ok' => false, 'message' => 'Forbidden: class rep or admin privileges required.'], 403);
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$rawId = $payload['moduleId'] ?? $_POST['moduleId'] ?? '';

if (!ctype_digit((string) $rawId) || (int) $rawId <= 0) {
    sendJson(['ok' => false, 'message' => 'Valid integer moduleId is required.'], 400);
}
$moduleId = (int) $rawId;

$pdo = db();
$stmt = $pdo->prepare('SELECT moduleId, blockName FROM moduleSources WHERE moduleId = ?');
$stmt->execute([$moduleId]);
$module = $stmt->fetch();

if (!$module) {
    sendJson(['ok' => false, 'message' => 'Module not found.'], 404);
}
if ($session['role'] !== 'admin' && $module['blockName'] !== $session['blockName']) {
    sendJson(['ok' => false, 'message' => 'Forbidden: module belongs to another block.'], 403);
}

$deleteStmt = $pdo->prepare('DELETE FROM moduleSources WHERE moduleId = ?');
$deleteStmt->execute([$moduleId]);

sendJson(['ok' => true, 'message' => 'Module deleted.', 'moduleId' => $moduleId]);
