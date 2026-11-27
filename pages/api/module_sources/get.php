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
    $conditions[] = '(title LIKE ? OR subject LIKE ? OR description LIKE ? OR course LIKE ? OR resourceUrl LIKE ? OR originalName LIKE ?)';
    $like = '%' . $search . '%';
    array_push($parameters, $like, $like, $like, $like, $like, $like);
}

$sql = 'SELECT moduleId, course, subject, title, description, resourceUrl,
               fileName, originalName, mimeType, fileSize,
               blockId, blockName, addedBy, dateAndTimeAdded
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
    // Fallback if new columns do not exist yet (migration not run)
    error_log('Module sources query failed (code ' . (string) $exception->getCode() . '): ' . $exception->getMessage());
    if (strpos($exception->getMessage(), 'fileName') !== false
        || strpos($exception->getMessage(), 'Unknown column') !== false
    ) {
        $sqlFallback = 'SELECT moduleId, course, subject, title, description, resourceUrl,
                               blockId, blockName, addedBy, dateAndTimeAdded
                        FROM moduleSources';
        if ($conditions) {
            $sqlFallback .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sqlFallback .= ' ORDER BY dateAndTimeAdded DESC, moduleId DESC';
        try {
            $statement = db()->prepare($sqlFallback);
            $statement->execute($parameters);
            $modules = $statement->fetchAll();
            foreach ($modules as &$m) {
                $m['fileName'] = null;
                $m['originalName'] = null;
                $m['mimeType'] = null;
                $m['fileSize'] = null;
            }
            unset($m);
        } catch (PDOException $e2) {
            moduleSourcesRespond(500, ['ok' => false, 'message' => 'Modules could not be loaded. Please try again.']);
        }
    } else {
        moduleSourcesRespond(500, ['ok' => false, 'message' => 'Modules could not be loaded. Please try again.']);
    }
}

foreach ($modules as &$module) {
    $module['moduleId'] = (int) $module['moduleId'];
    $module['addedBy'] = (int) $module['addedBy'];
    $module['blockId'] = $module['blockId'] === null ? null : (int) $module['blockId'];
    $module['fileSize'] = isset($module['fileSize']) && $module['fileSize'] !== null
        ? (int) $module['fileSize']
        : null;
    $hasFile = !empty($module['fileName']);
    $module['hasFile'] = $hasFile;
    $module['viewUrl'] = $hasFile
        ? './api/module_sources/view.php?id=' . $module['moduleId']
        : null;
    $module['downloadUrl'] = $hasFile
        ? './api/module_sources/view.php?id=' . $module['moduleId'] . '&download=1'
        : null;
    // Do not expose internal stored filename to clients
    unset($module['fileName']);
}
unset($module);

moduleSourcesRespond(200, [
    'ok' => true,
    'count' => count($modules),
    'emptyState' => count($modules) === 0,
    'modules' => $modules,
]);
