<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/require_auth.php';

$userType = strtolower((string) $_SESSION['user_type']);
$userName = (string) $_SESSION['user_name'];
$userEmail = (string) $_SESSION['user_email'];
$userId = (int) $_SESSION['user_id'];

if ($userType === 'admin') {
    require __DIR__ . '/renderers/dashboard_admin.php';
} else {
    require __DIR__ . '/renderers/dashboard_student.php';
}
