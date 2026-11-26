<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$session = requireModuleSession();
$search = trim((string) ($_GET['search'] ?? ''));
$seeAll = filter_var($_GET['seeAll'] ?? false, FILTER_VALIDATE_BOOLEAN);

$sql = 'SELECT moduleId, course, subject, title, description, resourceUrl, blockName, addedBy, dateAndTimeAdded
        FROM moduleSources';
$conditions = [];
$params = [];

if (!$seeAll && $session['role'] !== 'admin' && $session['blockName'] !== '') {
    $conditions[] = 'blockName = ?';
    $params[] = $session['blockName'];
}

if ($search !== '') {
    $conditions[] = '(title LIKE ? OR subject LIKE ? OR description LIKE ? OR course LIKE ? OR resourceUrl LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (count($conditions) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY dateAndTimeAdded DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$modules = $stmt->fetchAll();

foreach ($modules as &$module) {
    $module['moduleId'] = (int) $module['moduleId'];
    $module['addedBy'] = (int) $module['addedBy'];
}
unset($module);

sendJson([
    'ok'         => true,
    'count'      => count($modules),
    'emptyState' => count($modules) === 0,
    'modules'    => $modules,
]);
