<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    moduleSourcesRespond(405, ['ok' => false, 'message' => 'Use POST to delete a module.']);
}

$context = moduleSourcesContext();
moduleSourcesRequireManager($context);
moduleSourcesRequireCsrf();
$payload = moduleSourcesJsonBody();
$rawModuleId = $payload['moduleId'] ?? null;
if ((!is_int($rawModuleId) && !(is_string($rawModuleId) && ctype_digit($rawModuleId)))
    || (int) $rawModuleId < 1
) {
    moduleSourcesRespond(400, ['ok' => false, 'message' => 'Choose a valid module to delete.']);
}
$moduleId = (int) $rawModuleId;
$isAdmin = in_array('admin', $context['roles'], true);

try {
    $pdo = db();
    $statement = $pdo->prepare('SELECT blockId FROM moduleSources WHERE moduleId = ? LIMIT 1');
    $statement->execute([$moduleId]);
    $module = $statement->fetch();
    if (!$module) {
        moduleSourcesRespond(404, ['ok' => false, 'message' => 'Module not found.']);
    }
    if (!$isAdmin && (int) $module['blockId'] !== (int) $context['blockID']) {
        moduleSourcesRespond(403, ['ok' => false, 'message' => 'You cannot delete a module from another block.']);
    }

    $deleteStatement = $pdo->prepare('DELETE FROM moduleSources WHERE moduleId = ?');
    $deleteStatement->execute([$moduleId]);
} catch (PDOException $exception) {
    error_log('Module source delete failed (code ' . (string) $exception->getCode() . ').');
    moduleSourcesRespond(500, ['ok' => false, 'message' => 'The module could not be deleted. Please try again.']);
}

moduleSourcesRespond(200, ['ok' => true, 'message' => 'Module deleted.', 'moduleId' => $moduleId]);
