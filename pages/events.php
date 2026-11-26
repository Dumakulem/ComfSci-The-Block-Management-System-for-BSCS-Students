<?php

declare(strict_types=1);

// Allows students to browse events and register, while showing their payment and attendance status.
require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/../auth/roles.php';

$userContext = requireAnyRole(['student', 'block_rep']);
$studentId = $userContext['id'];
$pdo = db();
$message = '';
$isError = false;

if (empty($_SESSION['eventCsrf'])) {
    $_SESSION['eventCsrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['eventCsrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid form token. Reload the page and try again.');
    }

    $eventId = (int) ($_POST['eventID'] ?? 0);
    if ($eventId < 1) {
        http_response_code(400);
        exit('Invalid event.');
    }

    try {
        $pdo->beginTransaction();
        $eventStatement = $pdo->prepare(
            "SELECT eventID, participationFee
             FROM tblevent
             WHERE eventID = ? AND archivedAt IS NULL
               AND eventStatus IN ('Upcoming', 'Ongoing')
               AND TIMESTAMP(eventDate, eventStartTime) > NOW()
             FOR UPDATE"
        );
        $eventStatement->execute([$eventId]);
        $event = $eventStatement->fetch();

        if (!$event) {
            throw new DomainException('Registration is closed or this event is unavailable.');
        }

        $registrationStatement = $pdo->prepare(
            'INSERT INTO tbleventregistration (eventID, studentID) VALUES (?, ?)'
        );
        $registrationStatement->execute([$eventId, $studentId]);
        $registrationId = (int) $pdo->lastInsertId();

        $paymentStatement = $pdo->prepare(
            "INSERT INTO tbleventpayment (registrationID, amountDue, amountPaid, paymentStatus)
             VALUES (?, ?, 0, IF(? = 0, 'Paid', 'Unpaid'))"
        );
        $paymentStatement->execute([
            $registrationId,
            $event['participationFee'],
            $event['participationFee'],
        ]);
        $pdo->commit();
        $_SESSION['eventFlash'] = 'You are registered for the event.';
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['eventFlash'] = $exception->getMessage();
        $_SESSION['eventFlashError'] = true;
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Event registration failed: ' . $exception->getMessage());
        $_SESSION['eventFlash'] = 'Registration could not be completed. Refresh and try again.';
        $_SESSION['eventFlashError'] = true;
    }

    header('Location: ./events.php');
    exit;
}

$message = (string) ($_SESSION['eventFlash'] ?? '');
$isError = !empty($_SESSION['eventFlashError']);
unset($_SESSION['eventFlash'], $_SESSION['eventFlashError']);

$eventStatement = $pdo->prepare(
    "SELECT e.eventID, e.eventTitle, e.eventDescription, e.eventDate,
            e.eventStartTime, e.eventEndTime, e.eventLocation, e.participationFee,
            r.registrationID, r.registrationStatus, p.amountDue, p.amountPaid,
            p.paymentStatus, a.attendanceStatus, a.timeIn, a.timeOut
     FROM tblevent e
     LEFT JOIN tbleventregistration r
       ON r.eventID = e.eventID AND r.studentID = ?
     LEFT JOIN tbleventpayment p ON p.registrationID = r.registrationID
     LEFT JOIN tbleventattendance a ON a.registrationID = r.registrationID
     WHERE e.archivedAt IS NULL AND e.eventStatus <> 'Cancelled'
     ORDER BY e.eventDate DESC, e.eventStartTime DESC"
);
$eventStatement->execute([$studentId]);
$events = $eventStatement->fetchAll();

function eventsEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-sidebar="closed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Events</title>
    <link rel="stylesheet" href="./assets/dashboard.css?v=10">
    <link rel="stylesheet" href="./assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="content" style="max-width:1100px;margin:30px auto">
    <p><a href="./dashboard.php">← Back to dashboard</a></p>
    <section class="hero">
        <h1>Campus events</h1>
        <p>Browse event details, register, and check your payment and attendance status.</p>
    </section>
    <?php if ($message !== ''): ?>
        <p class="toast" role="status"<?= $isError ? ' style="background:#fff0ee;color:#8f2d22"' : '' ?>><?= eventsEscape($message) ?></p>
    <?php endif; ?>
    <?php if (!$events): ?>
        <section class="card"><p class="empty">There are no events to show.</p></section>
    <?php endif; ?>
    <?php foreach ($events as $event): ?>
        <article class="card" style="margin:16px 0;padding:20px">
            <h2><?= eventsEscape($event['eventTitle']) ?></h2>
            <p><?= nl2br(eventsEscape($event['eventDescription'])) ?></p>
            <p><strong>Date:</strong> <?= eventsEscape(date('D, M j, Y', strtotime((string) $event['eventDate']))) ?>
                · <?= eventsEscape(date('g:i A', strtotime((string) $event['eventStartTime']))) ?>
                <?php if (!empty($event['eventEndTime'])): ?>–<?= eventsEscape(date('g:i A', strtotime((string) $event['eventEndTime']))) ?><?php endif; ?>
            </p>
            <p><strong>Location:</strong> <?= eventsEscape($event['eventLocation']) ?>
                · <strong>Fee:</strong> PHP <?= eventsEscape(number_format((float) $event['participationFee'], 2)) ?></p>
            <?php if ($event['registrationID'] === null): ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= eventsEscape((string) $_SESSION['eventCsrf']) ?>">
                    <input type="hidden" name="eventID" value="<?= (int) $event['eventID'] ?>">
                    <button class="btn btn-solid" type="submit">Register for event</button>
                </form>
            <?php else: ?>
                <p><strong>Registration:</strong> <?= eventsEscape($event['registrationStatus']) ?>
                    · <strong>Payment:</strong> <?= eventsEscape($event['paymentStatus'] ?? 'Unpaid') ?>
                    (PHP <?= eventsEscape(number_format((float) ($event['amountPaid'] ?? 0), 2)) ?> of PHP <?= eventsEscape(number_format((float) ($event['amountDue'] ?? 0), 2)) ?>)
                    · <strong>Attendance:</strong> <?= eventsEscape($event['attendanceStatus'] ?? 'Not recorded') ?>
                </p>
                <?php if (in_array('block_rep', $userContext['roles'], true)): ?>
                    <a class="btn btn-solid" href="./event_attendance.php?eventID=<?= (int) $event['eventID'] ?>">Manage block attendance</a>
                <?php endif; ?>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</main>
<?php require __DIR__ . '/includes/ai_chat_widget.php'; ?>
</body>
</html>
