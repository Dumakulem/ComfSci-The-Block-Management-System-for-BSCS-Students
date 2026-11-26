<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';
$pdo = db();
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$upcomingUntil = $now->modify('+24 hours');
$sentCount = 0;
$failedCount = 0;


function atNotificationAlreadySent(PDO $pdo, int $assignmentID, int $studentID, string $type): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM at_email_notifications WHERE assignmentID = ? AND studentID = ? AND notificationType = ? LIMIT 1');
    $stmt->execute([$assignmentID, $studentID, $type]);
    return (bool) $stmt->fetchColumn();
}
function atRecordNotification(PDO $pdo, int $assignmentID, int $studentID, string $type): void
{
    $stmt = $pdo->prepare('INSERT IGNORE INTO at_email_notifications (assignmentID, studentID, notificationType) VALUES (?, ?, ?)');
    $stmt->execute([$assignmentID, $studentID, $type]);
}
function atGetName(array $student): string
{
    return trim(($student['studentFirstName'] ?? '') . ' ' . ($student['studentLastName'] ?? ''));
}

// Mark incomplete work overdue when its deadline has passed.
$markOverdue = $pdo->prepare("UPDATE at_studentassignments SET as_status = 'Overdue' WHERE as_deadline < ? AND as_status = 'Pending'");
$markOverdue->execute([$now->format('Y-m-d H:i:s')]);

// Upcoming deadline reminder: send once per student/assignment, in the 24-hour window.
$upcoming = $pdo->prepare("SELECT a.as_id, a.as_title, a.as_subject, a.as_deadline, sa.studentID, sa.as_status,
                                 s.studentEmail, s.studentFirstName, s.studentLastName
                          FROM at_assignments a
                          JOIN at_studentassignments sa ON sa.as_id = a.as_id AND sa.blockID = a.blockID
                          JOIN tblstudent s ON s.studentID = sa.studentID AND s.archivedAt IS NULL
                          WHERE a.as_deadline > ? AND a.as_deadline <= ? AND sa.as_status NOT IN ('Completed', 'Late')");
$upcoming->execute([$now->format('Y-m-d H:i:s'), $upcomingUntil->format('Y-m-d H:i:s')]);
foreach ($upcoming->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (!$row['studentEmail'] || atNotificationAlreadySent($pdo, (int) $row['as_id'], (int) $row['studentID'], 'upcoming_24h')) continue;
    $due = (new DateTime($row['as_deadline'], new DateTimeZone('Asia/Manila')))->format('M j, Y, g:i A');
    $body = buildUpcomingEmail(atGetName($row), $row['as_title'], $row['as_subject'], $due);
    if (sendMail($row['studentEmail'], 'Assignment deadline approaching: ' . $row['as_title'], $body)) {
        atRecordNotification($pdo, (int) $row['as_id'], (int) $row['studentID'], 'upcoming_24h');
        $sentCount++;
    } else {
        $failedCount++;
    }
}

// Overdue notice: send once per student/assignment after deadline, unless submitted.
$overdue = $pdo->prepare("SELECT a.as_id, a.as_title, a.as_subject, a.as_deadline, sa.studentID, sa.as_status,
                                s.studentEmail, s.studentFirstName, s.studentLastName
                         FROM at_assignments a
                         JOIN at_studentassignments sa ON sa.as_id = a.as_id AND sa.blockID = a.blockID
                         JOIN tblstudent s ON s.studentID = sa.studentID AND s.archivedAt IS NULL
                         WHERE a.as_deadline <= ? AND sa.as_status IN ('Pending', 'Overdue')");
$overdue->execute([$now->format('Y-m-d H:i:s')]);
foreach ($overdue->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (!$row['studentEmail'] || atNotificationAlreadySent($pdo, (int) $row['as_id'], (int) $row['studentID'], 'overdue')) continue;
    $due = (new DateTime($row['as_deadline'], new DateTimeZone('Asia/Manila')))->format('M j, Y, g:i A');
    $body = buildOverdueEmail($row['as_subject'], $row['as_title'], $due);
    if (sendMail($row['studentEmail'], 'Overdue assignment: ' . $row['as_title'], $body)) {
        atRecordNotification($pdo, (int) $row['as_id'], (int) $row['studentID'], 'overdue');
        $sentCount++;
    } else {
        $failedCount++;
    }
}
echo 'Assignment Tracker notifications complete. Sent: ' . $sentCount . ', failed: ' . $failedCount . PHP_EOL;
