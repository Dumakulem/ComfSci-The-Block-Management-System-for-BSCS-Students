<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/renderers/dashboard_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (strtolower((string) $_SESSION['user_type']) !== 'student') {
    http_response_code(403);
    exit('This action is only available to students.');
}

$submittedToken = (string) ($_POST['csrf'] ?? '');
if (!hash_equals((string) ($_SESSION['dashboard_csrf'] ?? ''), $submittedToken)) {
    http_response_code(400);
    exit('Invalid form token. Reload the dashboard and try again.');
}

$studentId = (int) $_SESSION['user_id'];
$returnMonth = (string) ($_POST['m'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $returnMonth)) {
    $returnMonth = date('Y-m');
}
$redirect = static function (string $query = '') use ($returnMonth) {
    header('Location: ./dashboard.php?m=' . rawurlencode($returnMonth) . $query);
    exit;
};

try {
    $pdo = db();
    $activeStudent = $pdo->prepare(
        'SELECT COUNT(*) FROM tblstudent WHERE studentID = ? AND archivedAt IS NULL'
    );
    $activeStudent->execute([$studentId]);
    if (!(int) $activeStudent->fetchColumn()) {
        http_response_code(403);
        exit('Archived student accounts cannot use dashboard actions.');
    }

    switch ((string) ($_POST['action'] ?? '')) {
        case 'clear_notifications':
            $statement = $pdo->prepare(
                'INSERT INTO tblnotificationclear (studentID, clearedAt)
                 VALUES (?, NOW())
                 ON DUPLICATE KEY UPDATE clearedAt = NOW()'
            );
            $statement->execute([$studentId]);
            $_SESSION['dashboard_flash'] = 'Notifications cleared.';
            break;

        case 'toggle_task':
            $statement = $pdo->prepare(
                'UPDATE tbltask SET isDone = 1 - isDone WHERE taskID = ? AND studentID = ?'
            );
            $statement->execute([(int) ($_POST['task_id'] ?? 0), $studentId]);
            break;

        case 'add_task':
            $title = trim((string) ($_POST['title'] ?? ''));
            $title = mb_substr($title, 0, 60);
            $date = (string) ($_POST['date'] ?? '');
            $time = (string) ($_POST['time'] ?? '');
            $validDate = DateTime::createFromFormat('!Y-m-d', $date);
            $validTime = DateTime::createFromFormat('!H:i', $time);
            if ($title === '' || !$validDate || $validDate->format('Y-m-d') !== $date
                || !$validTime || $validTime->format('H:i') !== $time) {
                $_SESSION['dashboard_flash'] = 'Enter a task, date, and time to add a to-do.';
                $redirect('#todo');
            }
            $statement = $pdo->prepare(
                'INSERT INTO tbltask (studentID, taskTitle, taskDate, taskTime, isDone)
                 VALUES (?, ?, ?, ?, 0)'
            );
            $statement->execute([$studentId, $title, $date, $time]);
            $_SESSION['dashboard_flash'] = 'Added to your to-do list.';
            break;

        case 'update_priority':
            $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
            $priority = (string) ($_POST['priority'] ?? '');
            if (!in_array($priority, ['High', 'Medium', 'Low'], true)) {
                http_response_code(400);
                exit('Invalid assignment priority.');
            }
            $ownsAssignment = $pdo->prepare(
                'SELECT COUNT(*) FROM tblassignment
                 WHERE assignmentID = ? AND archivedAt IS NULL
                   AND blockID = (SELECT blockID FROM tblstudent WHERE studentID = ?)'
            );
            $ownsAssignment->execute([$assignmentId, $studentId]);
            if (!(int) $ownsAssignment->fetchColumn()) {
                http_response_code(404);
                exit('Assignment not found for this student.');
            }
            $statement = $pdo->prepare(
                'INSERT INTO tblassignmentpriority (studentID, assignmentID, priority)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE priority = VALUES(priority)'
            );
            $statement->execute([$studentId, $assignmentId, $priority]);
            $_SESSION['dashboard_flash'] = 'Assignment priority updated.';
            $redirect('&edit=assignments');
    }
} catch (PDOException $exception) {
    error_log('Dashboard action failed: ' . $exception->getMessage());
    $_SESSION['dashboard_flash'] = 'The dashboard could not save that change. Please try again.';
}

$redirect();
