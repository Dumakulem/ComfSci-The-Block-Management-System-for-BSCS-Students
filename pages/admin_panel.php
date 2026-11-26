<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/renderers/dashboard_helpers.php';

if (strtolower((string) $_SESSION['user_type']) !== 'admin') {
    http_response_code(403);
    exit('This page is only available to administrators.');
}

$managementConfig = require __DIR__ . '/renderers/admin_management_config.php';
if (!isset($adminEntity) || !isset($managementConfig[$adminEntity])) {
    http_response_code(404);
    exit('Admin page not found.');
}

$userName = (string) $_SESSION['user_name'];
$showArchived = ($_GET['archived'] ?? '') === '1';
$adminData = dashboard_load_admin($showArchived, $adminEntity);
$entity = $managementConfig[$adminEntity];
$entity['rows'] = $adminData['records'][$adminEntity];
$options = $adminData['options'];
$flashMessage = (string) ($_SESSION['dashboard_flash'] ?? '');
unset($_SESSION['dashboard_flash']);
$pageTitle = $entity['title'] . ' Management';

require __DIR__ . '/renderers/admin_panel_view.php';
