<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = db();
$userType = strtolower((string) ($_SESSION['user_type'] ?? ''));
$name = trim((string) ($_SESSION['user_name'] ?? ''));
$name = $name !== '' ? $name : ($userType === 'admin' ? 'Administrator' : 'Student');
$modulePath = preg_replace('/\/(scenes|studentScenes)$/', '', rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/'));
$pagesBaseUrl = preg_replace('/\/assignmenttracker$/', '', $modulePath);
$dashboardUrl = $pagesBaseUrl . '/dashboard.php';
$loginUrl = $pagesBaseUrl . '/../auth/login.php';

if (empty($_SESSION['authenticated']) || !in_array($userType, ['admin', 'student'], true)) {
    header('Location: ' . $loginUrl);
    exit;
}


if ($userType === 'student') {
    if (strpos((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/studentScenes/') === false) {
        http_response_code(403);
        exit('Student access only.');
    }
    $studentID = (int) ($_SESSION['user_id'] ?? 0);
    $studentStmt = $pdo->prepare('SELECT s.studentID, s.studentLastName, s.studentFirstName, s.studentMiddleName, s.studentExtensionName, s.studentEmail, s.blockID, b.classBlock FROM tblstudent s JOIN tblblock b ON b.blockID = s.blockID WHERE s.studentID = ? AND s.archivedAt IS NULL AND b.archivedAt IS NULL');
    $studentStmt->execute([$studentID]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        http_response_code(403);
        exit('Student record not found.');
    }
    $blockID = (int) $student['blockID'];
    $classCode = $blockID;
    $blockName = (string) $student['classBlock'];
    $_SESSION['currentStudent'] = $studentID;
    $_SESSION['student'] = $student;
    $_SESSION['asClassCode'] = $blockID;
    $_SESSION['asClassCodeGroup'] = $blockID;
    $_SESSION['currentStudentGroup'] = $studentID;
} else {
    if (strpos((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/scenes/') === false) {
        http_response_code(403);
        exit('Administrator access only.');
    }
    $blockID = (int) ($_SESSION['at_blockID'] ?? 0);
    $blockStmt = $pdo->prepare('SELECT blockID, classBlock FROM tblblock WHERE blockID = ? AND archivedAt IS NULL');
    $blockStmt->execute([$blockID]);
    $block = $blockStmt->fetch(PDO::FETCH_ASSOC);
    if (!$block) {
        header('Location: ' . $modulePath . '/scenes/AdminClassCodes.php');
        exit;
    }
    $classCode = $blockID;
    $blockName = (string) $block['classBlock'];
    $_SESSION['name'] = $name;
    $_SESSION['code'] = $blockID;
    $_SESSION['blockName'] = $block['classBlock'];
}
