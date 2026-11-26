<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    moduleSourcesRespond(405, ['ok' => false, 'message' => 'Use GET to list modules.']);
}

$context = moduleSourcesContext();
$rawSearch = $_GET['search'] ?? '';
if (!is_string($rawSearch)) {
    moduleSourcesRespond(400, ['ok' => false, 'message' => 'Search must be plain text.']);
}
$search = trim($rawSearch);
if (moduleSourcesTextLength($search) > 100) {
    moduleSourcesRespond(400, ['ok' => false, 'message' => 'Search must be 100 characters or fewer.']);
}

if (!in_array('admin', $context['roles'], true) && empty($context['blockID'])) {
    moduleSourcesRespond(200, ['ok' => true, 'count' => 0, 'emptyState' => true, 'modules' => []]);
}

$conditions = [];
$parameters = [];
if (!in_array('admin', $context['roles'], true)) {
    $conditions[] = 'blockId = ?';
    $parameters[] = (int) $context['blockID'];
}
if ($search !== '') {
    $conditions[] = '(title LIKE ? OR subject LIKE ? OR description LIKE ? OR course LIKE ? OR resourceUrl LIKE ?)';
    $like = '%' . $search . '%';
    array_push($parameters, $like, $like, $like, $like, $like);
}

$sql = 'SELECT moduleId, course, subject, title, description, resourceUrl, blockId, blockName, addedBy, dateAndTimeAdded
        FROM moduleSources';
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY dateAndTimeAdded DESC, moduleId DESC';

try {
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    $modules = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log('Module sources query failed (code ' . (string) $exception->getCode() . ').');
    moduleSourcesRespond(500, ['ok' => false, 'message' => 'Modules could not be loaded. Please try again.']);
}

foreach ($modules as &$module) {
    $module['moduleId'] = (int) $module['moduleId'];
    $module['addedBy'] = (int) $module['addedBy'];
    $module['blockId'] = $module['blockId'] === null ? null : (int) $module['blockId'];
}
unset($module);

moduleSourcesRespond(200, [
    'ok' => true,
    'count' => count($modules),
    'emptyState' => count($modules) === 0,
    'modules' => $modules,
]);
