<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/require_auth.php';

$userType = strtolower((string) ($_SESSION['user_type'] ?? ''));
if ($userType === 'admin') {
    header('Location: scenes/AdminLoginScene.php');
    exit;
}
if ($userType === 'student') {
    header('Location: studentScenes/StudentLoginScene.php');
    exit;
}
http_response_code(403);
exit('Your account role cannot access the Assignment Tracker.');
