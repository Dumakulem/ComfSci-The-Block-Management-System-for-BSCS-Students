<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    moduleSourcesRespond(405, ['ok' => false, 'message' => 'Use POST to save a module.']);
}

$context = moduleSourcesContext();
moduleSourcesRequireManager($context);
moduleSourcesRequireCsrf();
$payload = moduleSourcesJsonBody();

$moduleId = null;
if (array_key_exists('moduleId', $payload) && $payload['moduleId'] !== null && $payload['moduleId'] !== '') {
    $rawModuleId = $payload['moduleId'];
    if ((!is_int($rawModuleId) && !(is_string($rawModuleId) && ctype_digit($rawModuleId)))
        || (int) $rawModuleId < 1
    ) {
        moduleSourcesRespond(400, ['ok' => false, 'message' => 'Choose a valid module.']);
    }
    $moduleId = (int) $rawModuleId;
}

foreach (['course', 'subject', 'title', 'description', 'resourceUrl'] as $field) {
    if (!isset($payload[$field]) || !is_string($payload[$field])) {
        moduleSourcesRespond(400, ['ok' => false, 'message' => 'Enter valid values for all module fields.']);
    }
}

$course = trim($payload['course']);
$subject = trim($payload['subject']);
$title = trim($payload['title']);
$description = trim($payload['description']);
$resourceUrl = trim($payload['resourceUrl']);

if ($title === '' || moduleSourcesTextLength($title) > 180) {
    moduleSourcesRespond(422, ['ok' => false, 'message' => 'Title is required and must be 180 characters or fewer.']);
}
if ($subject === '' || moduleSourcesTextLength($subject) > 100) {
    moduleSourcesRespond(422, ['ok' => false, 'message' => 'Subject is required and must be 100 characters or fewer.']);
}
if (moduleSourcesTextLength($course) > 125) {
    moduleSourcesRespond(422, ['ok' => false, 'message' => 'Course must be 125 characters or fewer.']);
}
if (moduleSourcesTextLength($description) > 4000) {
    moduleSourcesRespond(422, ['ok' => false, 'message' => 'Description must be 4,000 characters or fewer.']);
}
if (!moduleSourcesValidUrl($resourceUrl)) {
    moduleSourcesRespond(422, ['ok' => false, 'message' => 'Enter a valid HTTP or HTTPS resource link.']);
}

$isAdmin = in_array('admin', $context['roles'], true);
$blockId = $isAdmin
    ? (filter_var($payload['blockId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0)
    : (int) ($context['blockID'] ?? 0);
if ($blockId < 1) {
    moduleSourcesRespond(422, ['ok' => false, 'message' => 'Choose a valid block for this module.']);
}

try {
    $pdo = db();
    $blockStatement = $pdo->prepare('SELECT blockID, classBlock FROM tblblock WHERE blockID = ? LIMIT 1');
    $blockStatement->execute([$blockId]);
    $block = $blockStatement->fetch();
    if (!$block) {
        moduleSourcesRespond(422, ['ok' => false, 'message' => 'The selected block does not exist.']);
    }

    if ($moduleId !== null) {
        $existingStatement = $pdo->prepare('SELECT moduleId, blockId FROM moduleSources WHERE moduleId = ? LIMIT 1');
        $existingStatement->execute([$moduleId]);
        $existing = $existingStatement->fetch();
        if (!$existing) {
            moduleSourcesRespond(404, ['ok' => false, 'message' => 'Module not found.']);
        }
        if (!$isAdmin && (int) $existing['blockId'] !== (int) $context['blockID']) {
            moduleSourcesRespond(403, ['ok' => false, 'message' => 'You cannot edit a module from another block.']);
        }

        $statement = $pdo->prepare(
            'UPDATE moduleSources
             SET course = ?, subject = ?, title = ?, description = ?, resourceUrl = ?, blockId = ?, blockName = ?
             WHERE moduleId = ?'
        );
        $statement->execute([
            $course !== '' ? $course : null,
            $subject,
            $title,
            $description !== '' ? $description : null,
            $resourceUrl,
            (int) $block['blockID'],
            (string) $block['classBlock'],
            $moduleId,
        ]);
        moduleSourcesRespond(200, ['ok' => true, 'message' => 'Module updated.', 'moduleId' => $moduleId]);
    }

    $statement = $pdo->prepare(
        'INSERT INTO moduleSources
            (course, subject, title, description, resourceUrl, blockId, blockName, addedBy, addedByType)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $statement->execute([
        $course !== '' ? $course : null,
        $subject,
        $title,
        $description !== '' ? $description : null,
        $resourceUrl,
        (int) $block['blockID'],
        (string) $block['classBlock'],
        (int) $context['id'],
        strtolower((string) $_SESSION['user_type']),
    ]);

    moduleSourcesRespond(201, [
        'ok' => true,
        'message' => 'Module added.',
        'moduleId' => (int) $pdo->lastInsertId(),
    ]);
} catch (PDOException $exception) {
    error_log('Module source save failed (code ' . (string) $exception->getCode() . ').');
    moduleSourcesRespond(500, ['ok' => false, 'message' => 'The module could not be saved. Please try again.']);
}
