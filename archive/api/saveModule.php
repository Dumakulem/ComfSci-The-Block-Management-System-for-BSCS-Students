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

$rawId = $_POST['moduleId'] ?? '';
$moduleId = null;
if ($rawId !== '' && $rawId !== null) {
    if (!ctype_digit((string) $rawId) || (int) $rawId <= 0) {
        sendJson(['ok' => false, 'message' => 'Invalid moduleId.'], 400);
    }
    $moduleId = (int) $rawId;
}

$course = trim((string) ($_POST['course'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$title = trim((string) ($_POST['title'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$requestedBlock = trim((string) ($_POST['blockName'] ?? ''));
$resourceUrl = normalizeResourceUrl((string) ($_POST['resourceUrl'] ?? $_POST['link'] ?? ''));

if ($title === '' || mb_strlen($title) > 180) {
    sendJson(['ok' => false, 'message' => 'Title is required (max 180 characters).'], 422);
}
if ($subject === '' || mb_strlen($subject) > 100) {
    sendJson(['ok' => false, 'message' => 'Subject is required (max 100 characters).'], 422);
}
if (mb_strlen($course) > 125) {
    sendJson(['ok' => false, 'message' => 'Course must be 125 characters or fewer.'], 422);
}
if ($resourceUrl === null) {
    sendJson(['ok' => false, 'message' => 'A valid http or https link is required.'], 422);
}

$pdo = db();

$blockId = $session['blockId'];
$blockName = $session['blockName'];

if ($session['role'] === 'admin' && $requestedBlock !== '') {
    if (ctype_digit($requestedBlock)) {
        $bStmt = $pdo->prepare('SELECT blockID, classBlock FROM tblblock WHERE blockID = ? LIMIT 1');
        $bStmt->execute([(int) $requestedBlock]);
    } else {
        $bStmt = $pdo->prepare('SELECT blockID, classBlock FROM tblblock WHERE classBlock = ? LIMIT 1');
        $bStmt->execute([$requestedBlock]);
    }
    $foundBlock = $bStmt->fetch();
    if ($foundBlock) {
        $blockId = (int) $foundBlock['blockID'];
        $blockName = (string) $foundBlock['classBlock'];
    } else {
        $blockId = null;
        $blockName = mb_substr($requestedBlock, 0, 30);
    }
}

if ($blockName === '') {
    sendJson(['ok' => false, 'message' => 'Block assignment is missing.'], 422);
}

if ($moduleId !== null) {
    $findStmt = $pdo->prepare('SELECT moduleId, blockName FROM moduleSources WHERE moduleId = ?');
    $findStmt->execute([$moduleId]);
    $existing = $findStmt->fetch();

    if (!$existing) {
        sendJson(['ok' => false, 'message' => 'Module not found.'], 404);
    }
    if ($session['role'] !== 'admin' && $existing['blockName'] !== $session['blockName']) {
        sendJson(['ok' => false, 'message' => 'Forbidden: module belongs to another block.'], 403);
    }

    $stmt = $pdo->prepare(
        'UPDATE moduleSources
         SET course = ?, subject = ?, title = ?, description = ?, resourceUrl = ?, blockId = ?, blockName = ?
         WHERE moduleId = ?'
    );
    $stmt->execute([
        $course !== '' ? $course : null,
        $subject,
        $title,
        $description !== '' ? $description : null,
        $resourceUrl,
        $blockId,
        $blockName,
        $moduleId,
    ]);

    sendJson(['ok' => true, 'message' => 'Module updated.', 'moduleId' => $moduleId]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO moduleSources
         (course, subject, title, description, resourceUrl, blockId, blockName, addedBy, addedByType)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $course !== '' ? $course : null,
        $subject,
        $title,
        $description !== '' ? $description : null,
        $resourceUrl,
        $blockId,
        $blockName,
        $session['userId'],
        $session['userType'],
    ]);

    sendJson([
        'ok'       => true,
        'message'  => 'Module added.',
        'moduleId' => (int) $pdo->lastInsertId(),
    ], 201);
}
