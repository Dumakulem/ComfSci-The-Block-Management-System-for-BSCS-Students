<?php

declare(strict_types=1);

// Lets an approved block representative record event time-in/time-out and finalize absences.
require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/../auth/roles.php';

$userContext = requireAnyRole(['block_rep']);
$pdo = db();
$eventId = (int) ($_GET['eventID'] ?? $_POST['eventID'] ?? 0);
$blockId = $userContext['blockID'];
$studentId = $userContext['id'];

if ($eventId < 1 || $blockId === null) {
    http_response_code(400);
    exit('A valid event is required.');
}

if (empty($_SESSION['eventAttendanceCsrf'])) {
    $_SESSION['eventAttendanceCsrf'] = bin2hex(random_bytes(32));
}

$eventStatement = $pdo->prepare(
    'SELECT eventID, eventTitle, eventDate, eventStartTime, eventEndTime, eventStatus,
            (NOW() >= TIMESTAMP(eventDate, eventStartTime)) AS hasStarted,
            (NOW() > TIMESTAMP(eventDate, COALESCE(eventEndTime, eventStartTime))) AS hasEnded
     FROM tblevent
     WHERE eventID = ? AND archivedAt IS NULL'
);
$eventStatement->execute([$eventId]);
$event = $eventStatement->fetch();
if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$approvalStatement = $pdo->prepare(
    'SELECT approvalStatus
     FROM tbleventattendanceapproval
     WHERE eventID = ? AND blockID = ?'
);
$approvalStatement->execute([$eventId, $blockId]);
$approvalStatus = $approvalStatement->fetchColumn() ?: '';

$eventStarted = (bool) $event['hasStarted'];
$eventEnded = (bool) $event['hasEnded'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['eventAttendanceCsrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid form token. Reload the page and try again.');
    }

    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'request_approval') {
            if (!$eventStarted || $eventEnded) {
                throw new DomainException('Attendance approval can only be requested while the event is in progress.');
            }
            if ($approvalStatus === 'Approved') {
                throw new DomainException('Attendance is already approved for this block.');
            }
            if ($approvalStatus === 'Pending') {
                throw new DomainException('An approval request is already pending.');
            }

            $requestStatement = $pdo->prepare(
                "INSERT INTO tbleventattendanceapproval
                    (eventID, blockID, requestedByStudentID, approvalStatus)
                 VALUES (?, ?, ?, 'Pending')
                 ON DUPLICATE KEY UPDATE
                    requestedByStudentID = VALUES(requestedByStudentID),
                    approvalStatus = 'Pending',
                    requestedAt = CURRENT_TIMESTAMP,
                    reviewedByAdminID = NULL,
                    reviewedAt = NULL"
            );
            $requestStatement->execute([$eventId, $blockId, $studentId]);
            $_SESSION['eventAttendanceFlash'] = 'Attendance approval requested from an administrator.';
        } elseif (in_array($action, ['time_in', 'time_out'], true)) {
            if ($approvalStatus !== 'Approved') {
                throw new DomainException('An administrator must approve attendance before it can be recorded.');
            }
            if (!$eventStarted || $eventEnded) {
                throw new DomainException('Time-in and time-out can only be recorded while the event is in progress.');
            }

            $registrationId = (int) ($_POST['registrationID'] ?? 0);
            if ($registrationId < 1) {
                throw new DomainException('Select a valid event registration.');
            }

            $pdo->beginTransaction();
            $registrationStatement = $pdo->prepare(
                'SELECT r.registrationID, a.attendanceID, a.timeIn, a.timeOut
                 FROM tbleventregistration r
                 JOIN tblstudent s ON s.studentID = r.studentID
                 LEFT JOIN tbleventattendance a ON a.registrationID = r.registrationID
                 WHERE r.registrationID = ? AND r.eventID = ? AND s.blockID = ?
                   AND s.archivedAt IS NULL
                 FOR UPDATE'
            );
            $registrationStatement->execute([$registrationId, $eventId, $blockId]);
            $registration = $registrationStatement->fetch();
            if (!$registration) {
                throw new DomainException('That registration is not part of your block.');
            }

            if ($action === 'time_in') {
                if ($registration['timeIn'] !== null) {
                    throw new DomainException('Time-in has already been recorded.');
                }
                if ($registration['attendanceID'] === null) {
                    $attendanceStatement = $pdo->prepare(
                        "INSERT INTO tbleventattendance
                            (registrationID, markedByStudentID, attendanceStatus, timeIn)
                         VALUES (?, ?, 'present', NOW())"
                    );
                    $attendanceStatement->execute([$registrationId, $studentId]);
                } else {
                    $attendanceStatement = $pdo->prepare(
                        "UPDATE tbleventattendance
                         SET markedByStudentID = ?, attendanceStatus = 'present',
                             timeIn = NOW(), timeOut = NULL
                         WHERE registrationID = ?"
                    );
                    $attendanceStatement->execute([$studentId, $registrationId]);
                }
                $_SESSION['eventAttendanceFlash'] = 'Time-in recorded.';
            } else {
                if ($registration['timeIn'] === null || $registration['timeOut'] !== null) {
                    throw new DomainException('Time-out requires one recorded time-in and can only be recorded once.');
                }
                $attendanceStatement = $pdo->prepare(
                    'UPDATE tbleventattendance
                     SET markedByStudentID = ?, timeOut = NOW()
                     WHERE registrationID = ?'
                );
                $attendanceStatement->execute([$studentId, $registrationId]);
                $_SESSION['eventAttendanceFlash'] = 'Time-out recorded.';
            }

            $pdo->commit();
        } else {
            http_response_code(400);
            exit('Invalid attendance action.');
        }
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['eventAttendanceFlash'] = $exception->getMessage();
        $_SESSION['eventAttendanceFlashError'] = true;
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Event attendance action failed: ' . $exception->getMessage());
        $_SESSION['eventAttendanceFlash'] = 'Attendance could not be saved. Refresh and try again.';
        $_SESSION['eventAttendanceFlashError'] = true;
    }

    header('Location: ./event_attendance.php?eventID=' . $eventId);
    exit;
}

if ($approvalStatus === 'Approved' && $eventEnded) {
    $absentStatement = $pdo->prepare(
        "INSERT IGNORE INTO tbleventattendance
            (registrationID, attendanceStatus)
         SELECT r.registrationID, 'absent'
         FROM tbleventregistration r
         JOIN tblstudent s ON s.studentID = r.studentID
         WHERE r.eventID = ? AND r.registrationStatus = 'Registered'
           AND s.blockID = ? AND s.archivedAt IS NULL"
    );
    $absentStatement->execute([$eventId, $blockId]);
}

$message = (string) ($_SESSION['eventAttendanceFlash'] ?? '');
$isError = !empty($_SESSION['eventAttendanceFlashError']);
unset($_SESSION['eventAttendanceFlash'], $_SESSION['eventAttendanceFlashError']);

$attendanceStatement = $pdo->prepare(
    'SELECT r.registrationID, s.studentID, s.studentFirstName, s.studentLastName,
            a.attendanceStatus, a.timeIn, a.timeOut
     FROM tbleventregistration r
     JOIN tblstudent s ON s.studentID = r.studentID
     LEFT JOIN tbleventattendance a ON a.registrationID = r.registrationID
     WHERE r.eventID = ? AND s.blockID = ? AND s.archivedAt IS NULL
     ORDER BY s.studentLastName, s.studentFirstName'
);
$attendanceStatement->execute([$eventId, $blockId]);
$attendees = $attendanceStatement->fetchAll();

function eventAttendanceEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-sidebar="closed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Attendance</title>
    <link rel="stylesheet" href="./assets/dashboard.css?v=10">
    <link rel="stylesheet" href="./assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="content" style="max-width:1100px;margin:30px auto">
    <p><a href="./events.php">← Back to events</a></p>
    <section class="hero">
        <h1>Attendance · <?= eventAttendanceEscape($event['eventTitle']) ?></h1>
        <p>Attendance is block-scoped and requires administrator approval.</p>
    </section>
    <?php if ($message !== ''): ?>
        <p class="toast" role="status"<?= $isError ? ' style="background:#fff0ee;color:#8f2d22"' : '' ?>><?= eventAttendanceEscape($message) ?></p>
    <?php endif; ?>
    <section class="card" style="margin:16px 0;padding:20px">
        <p><strong>Approval:</strong> <?= eventAttendanceEscape($approvalStatus ?: 'Not requested') ?></p>
        <?php if ($approvalStatus !== 'Approved'): ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= eventAttendanceEscape((string) $_SESSION['eventAttendanceCsrf']) ?>">
                <input type="hidden" name="eventID" value="<?= $eventId ?>">
                <input type="hidden" name="action" value="request_approval">
                <button class="btn btn-solid" type="submit"<?= (!$eventStarted || $eventEnded || $approvalStatus === 'Pending') ? ' disabled' : '' ?>>Request attendance approval</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="card" style="margin:16px 0;padding:20px">
        <h2>Registered students in your block</h2>
        <?php if (!$attendees): ?><p class="empty">No students from your block are registered.</p><?php endif; ?>
        <?php foreach ($attendees as $attendee): ?>
            <article style="padding:12px 0;border-bottom:1px solid #e8e8e8">
                <strong><?= eventAttendanceEscape($attendee['studentFirstName'] . ' ' . $attendee['studentLastName']) ?></strong>
                <span> · <?= eventAttendanceEscape($attendee['attendanceStatus'] ?? 'Not recorded') ?></span>
                <?php if (!empty($attendee['timeIn'])): ?><span> · In <?= eventAttendanceEscape((string) $attendee['timeIn']) ?></span><?php endif; ?>
                <?php if (!empty($attendee['timeOut'])): ?><span> · Out <?= eventAttendanceEscape((string) $attendee['timeOut']) ?></span><?php endif; ?>
                <?php if ($approvalStatus === 'Approved' && $eventStarted && !$eventEnded): ?>
                    <form method="post" style="display:inline-flex;gap:8px;margin-left:12px">
                        <input type="hidden" name="csrf" value="<?= eventAttendanceEscape((string) $_SESSION['eventAttendanceCsrf']) ?>">
                        <input type="hidden" name="eventID" value="<?= $eventId ?>">
                        <input type="hidden" name="registrationID" value="<?= (int) $attendee['registrationID'] ?>">
                        <?php if (empty($attendee['timeIn'])): ?>
                            <button class="btn btn-solid" name="action" value="time_in" type="submit">Time in</button>
                        <?php elseif (empty($attendee['timeOut'])): ?>
                            <button class="btn btn-solid" name="action" value="time_out" type="submit">Time out</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/ai_chat_widget.php'; ?>
</body>
</html>
