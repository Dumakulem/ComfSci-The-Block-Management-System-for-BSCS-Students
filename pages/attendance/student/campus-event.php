<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/attendance-base.php';

attendance_guard(['student', 'rep']);

$studentId = $viewerId;
$blockId = (int) $studentRecord['blockID'];
$studentCourseId = (int) ($studentRecord['courseID'] ?? 0);
$currentUrl = $attendanceBase . '/student/campus-event.php';

// Filter ng status sa campus events
$statusFilter = (string) ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'Upcoming', 'Ongoing', 'Ended', 'Cancelled'], true)) {
    $statusFilter = 'all';
}

/* ------------------------------------------------------------------
   Pag-register at pag-cancel sa event
------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    attendance_check_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $registrationId = (int) ($_POST['registration_id'] ?? 0);

    try {
        if ($action === 'register') {
            $eventStatement = $pdo->prepare('SELECT * FROM tblevent WHERE eventID = ?');
            $eventStatement->execute([$eventId]);
            $event = $eventStatement->fetch();

            if ($event === false) {
                attendance_flash('error', 'That event could not be found.');
            } elseif (attendance_event_course($event) > 0 && attendance_event_course($event) !== $studentCourseId) {
                attendance_flash('error', 'That event is only open to students of another course.');
            } elseif (!in_array(attendance_event_status($event), ['Upcoming', 'Ongoing'], true)) {
                attendance_flash('error', 'Registration is closed for that event.');
            } else {
                $duplicate = $pdo->prepare(
                    'SELECT registrationID FROM tbleventregistration WHERE eventID = ? AND studentID = ?'
                );
                $duplicate->execute([$eventId, $studentId]);

                if ($duplicate->fetchColumn()) {
                    attendance_flash('error', 'You are already registered for that event.');
                } else {
                    $pdo->beginTransaction();

                    $insert = $pdo->prepare(
                        'INSERT INTO tbleventregistration (eventID, studentID, registrationStatus)
                         VALUES (?, ?, ?)'
                    );
                    $insert->execute([$eventId, $studentId, 'Registered']);
                    $newRegistrationId = (int) $pdo->lastInsertId();

                    $fee = (float) $event['participationFee'];
                    if ($fee > 0) {
                        $payment = $pdo->prepare(
                            'INSERT INTO tbleventpayment
                             (registrationID, amountDue, amountPaid, paymentStatus, paymentRemarks)
                             VALUES (?, ?, 0.00, ?, ?)'
                        );
                        $payment->execute([
                            $newRegistrationId,
                            $fee,
                            'Unpaid',
                            'Created when you registered for ' . $event['eventTitle'],
                        ]);
                    }

                    $pdo->commit();

                    // free event: walang payment record, register lang tapos sali
                    $successMessage = 'You are registered for ' . $event['eventTitle'] . '.';
                    if ($fee <= 0) {
                        $successMessage .= ' Free event ito, just show up on the day.';
                    }
                    attendance_flash('success', $successMessage);
                }
            }
        } elseif ($action === 'cancel') {
            $registration = $pdo->prepare(
                'SELECT r.registrationID, r.eventID, e.eventTitle, e.eventStatus,
                        p.paymentStatus
                 FROM tbleventregistration r
                 JOIN tblevent e ON e.eventID = r.eventID
                 LEFT JOIN tbleventpayment p ON p.registrationID = r.registrationID
                 WHERE r.registrationID = ? AND r.studentID = ?'
            );
            $registration->execute([$registrationId, $studentId]);
            $record = $registration->fetch();

            if ($record === false) {
                attendance_flash('error', 'That registration could not be found.');
            } elseif (in_array((string) $record['paymentStatus'], ['Paid', 'Pending Cash', 'For Verification'], true)) {
                attendance_flash('error', 'You cannot cancel after a payment has been submitted. Please see your block representative.');
            } else {
                $delete = $pdo->prepare(
                    'DELETE FROM tbleventregistration WHERE registrationID = ? AND studentID = ?'
                );
                $delete->execute([$registrationId, $studentId]);
                attendance_flash('success', 'Registration cancelled for ' . $record['eventTitle'] . '.');
            }
        } else {
            attendance_flash('error', 'Unknown action.');
        }
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        attendance_flash('error', 'The request could not be saved. Please try again.');
    }

    attendance_redirect($currentUrl . '?status=' . urlencode($statusFilter));
}

/* Data na kailangan ng page */
$eventsStatement = $pdo->prepare(
    "SELECT e.*, a.adminFirstName, a.adminLastName, c.courseName,
            (SELECT COUNT(*) FROM tbleventregistration x WHERE x.eventID = e.eventID) AS registeredCount
     FROM tblevent e
     JOIN tbladmin a ON a.adminID = e.adminID
     LEFT JOIN tblcourse c ON c.courseID = e.courseID
     WHERE e.archivedAt IS NULL AND (e.courseID IS NULL OR e.courseID = ?)
     ORDER BY (e.eventDate >= CURDATE()) DESC, e.eventDate ASC, e.eventStartTime ASC"
);
$eventsStatement->execute([$studentCourseId]);
$events = $eventsStatement->fetchAll();

// Display filter (counts/preview stay based on all events)
$displayEvents = $events;
if ($statusFilter !== 'all') {
    $displayEvents = array_values(array_filter($events, static function (array $event) use ($statusFilter): bool {
        return attendance_event_status($event) === $statusFilter;
    }));
}

$registrationsStatement = $pdo->prepare(
    'SELECT r.registrationID, r.eventID, r.registrationStatus, p.paymentStatus, p.amountDue, p.amountPaid
     FROM tbleventregistration r
     LEFT JOIN tbleventpayment p ON p.registrationID = r.registrationID
     WHERE r.studentID = ?'
);
$registrationsStatement->execute([$studentId]);
$myRegistrations = [];
foreach ($registrationsStatement->fetchAll() as $row) {
    $myRegistrations[(int) $row['eventID']] = $row;
}

$eventAnnouncements = [];
$announcementStatement = $pdo->query(
    'SELECT an.announcementID, an.eventID, an.announcementTitle, an.announcementContent, an.createdAt
     FROM tblannouncement an
     JOIN tblevent e ON e.eventID = an.eventID
     WHERE e.archivedAt IS NULL
     ORDER BY an.createdAt DESC'
);
foreach ($announcementStatement->fetchAll() as $announcement) {
    $eventAnnouncements[(int) $announcement['eventID']][] = $announcement;
}

$upcomingEvents = array_values(array_filter($events, static function (array $event): bool {
    return in_array(attendance_event_status($event), ['Upcoming', 'Ongoing'], true);
}));
$upcomingPreview = array_slice($upcomingEvents, 0, 3);

attendance_page_start('events');
?>

<div class="layout">
    <div class="stack">
        <section class="card">
            <div class="cardHead">
                <img src="<?= h($attendanceAssets) ?>/calendar.png" alt="">
                <h3>Campus Events</h3>
                <span class="spacer"></span>
                <a class="pillLink" href="<?= h($attendanceBase) ?>/student/event-attendance.php">
                    My registrations
                    <img src="<?= h($attendanceAssets) ?>/chevron-right.png" alt="">
                </a>
            </div>

            <?php if (count($events) > 0): ?>
                <div class="filterRow">
                    <form method="get" class="btnRow">
                        <select class="selectInline" name="status" aria-label="Filter by status" onchange="this.form.submit()">
                            <?php foreach (['all' => 'All statuses', 'Upcoming' => 'Upcoming', 'Ongoing' => 'Ongoing', 'Ended' => 'Ended', 'Cancelled' => 'Cancelled'] as $value => $label): ?>
                                <option value="<?= h($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($statusFilter !== 'all'): ?>
                            <a class="btn gray tiny" href="<?= h($currentUrl) ?>">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (count($events) === 0): ?>
                <div class="emptyState">
                    <img src="<?= h($attendanceAssets) ?>/calendar.png" alt="">
                    <strong>No campus events yet</strong>
                    <p>New events will appear here once the administrators publish them.</p>
                </div>
            <?php elseif (count($displayEvents) === 0): ?>
                <div class="emptyState">
                    <img src="<?= h($attendanceAssets) ?>/calendar.png" alt="">
                    <strong>No events match this filter</strong>
                    <p>Try another status or reset the filter.</p>
                </div>
            <?php else: ?>
                <div class="cardBody">
                    <div class="eventGrid">
                        <?php foreach ($displayEvents as $event): ?>
                            <?php
                            $status = attendance_event_status($event);
                            $myRegistration = $myRegistrations[(int) $event['eventID']] ?? null;
                            $fee = (float) $event['participationFee'];
                            // refund: pending muna habang wala pang approval, Refunded kapag approved na
                            $myPaymentStatus = (string) ($myRegistration['paymentStatus'] ?? '');
                            $refundNote = $myPaymentStatus === 'Refunded'
                                ? ' — your payment has been returned'
                                : ($myPaymentStatus === 'Refund Pending' ? ' — refund pending approval' : '');
                            ?>
                            <article class="eventCard">
                                <div class="topRow">
                                    <h4><?= h($event['eventTitle']) ?></h4>
                                    <?= attendance_badge($status) ?>
                                </div>

                                <p class="eventText">
                                    <?= h(mb_strimwidth((string) ($event['eventDescription'] ?? 'No description provided.'), 0, 140, '...')) ?>
                                </p>

                                <div class="metaList">
                                    <span>
                                        <img src="<?= h($attendanceAssets) ?>/calendar.png" alt="">
                                        <?= h(attendance_long_date($event['eventDate'])) ?>
                                    </span>
                                    <span>
                                        <img src="<?= h($attendanceAssets) ?>/clock.png" alt="">
                                        <?= h(attendance_time($event['eventStartTime'])) ?>
                                        <?php if (!empty($event['eventEndTime'])): ?>
                                            &ndash; <?= h(attendance_time($event['eventEndTime'])) ?>
                                        <?php endif; ?>
                                    </span>
                                    <span>
                                        <img src="<?= h($attendanceAssets) ?>/pin.png" alt="">
                                        <?= h($event['eventLocation']) ?>
                                    </span>
                                    <span>
                                        <img src="<?= h($attendanceAssets) ?>/users.png" alt="">
                                        Open to <?= h(attendance_event_audience($event)) ?>
                                    </span>
                                    <span>
                                        <img src="<?= h($attendanceAssets) ?>/users.png" alt="">
                                        <?= (int) $event['registeredCount'] ?> registered
                                        <?php if ($fee > 0): ?>
                                            &middot; <span class="feeTag"><?= attendance_money($fee) ?></span>
                                        <?php else: ?>
                                            &middot; <span class="badge present">Free</span> no payment needed
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($fee > 0 && !empty($event['paymentDeadline'])): ?>
                                        <span>
                                            <img src="<?= h($attendanceAssets) ?>/clock.png" alt="">
                                            Pay by <?= h(attendance_long_date((string) $event['paymentDeadline'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($status === 'Cancelled'): ?>
                                        <span>
                                            <img src="<?= h($attendanceAssets) ?>/info.png" alt="">
                                            Event cancelled<?= h($refundNote) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($eventAnnouncements[(int) $event['eventID']])): ?>
                                        <span>
                                            <img src="<?= h($attendanceAssets) ?>/bell.png" alt="">
                                            <?= count($eventAnnouncements[(int) $event['eventID']]) ?> announcement<?= count($eventAnnouncements[(int) $event['eventID']]) === 1 ? '' : 's' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="cardActions">
                                    <button class="btn ghost tiny" type="button" data-modal-open="event-<?= (int) $event['eventID'] ?>">
                                        View details
                                    </button>

                                    <?php if ($myRegistration !== null): ?>
                                        <?php if ($myPaymentStatus === 'Refund Pending'): ?>
                                            <span class="badge late">Refund pending</span>
                                        <?php elseif ($myPaymentStatus === 'Refunded'): ?>
                                            <span class="badge refunded">Fee refunded</span>
                                        <?php elseif ($myPaymentStatus !== '' && $myPaymentStatus !== 'Paid' && $fee > 0 && (string) $event['eventStatus'] !== 'Cancelled'): ?>
                                            <a class="btn tiny" href="<?= h($attendanceBase) ?>/student/event-payment.php">Pay fee</a>
                                        <?php elseif ($fee > 0): ?>
                                            <span class="badge registered">Registered</span>
                                        <?php else: ?>
                                            <span class="badge registered">Registered &middot; free</span>
                                        <?php endif; ?>
                                    <?php elseif (in_array($status, ['Upcoming', 'Ongoing'], true)): ?>
                                        <form method="post" style="margin:0;">
                                            <?= attendance_csrf_field() ?>
                                            <input type="hidden" name="action" value="register">
                                            <input type="hidden" name="event_id" value="<?= (int) $event['eventID'] ?>">
                                            <button class="btn tiny" type="submit"><?= $fee > 0 ? 'Register' : 'Join free event' ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge">Closed</span>
                                    <?php endif; ?>
                                </div>
                            </article>

                            <div class="modal" id="event-<?= (int) $event['eventID'] ?>" hidden>
                                <div class="modalBackdrop"></div>
                                <div class="modalPanel">
                                    <div class="modalHead">
                                        <div>
                                            <h3><?= h($event['eventTitle']) ?></h3>
                                            <p><?= h($status) ?> event &middot; Organized by <?= h(trim($event['adminFirstName'] . ' ' . $event['adminLastName'])) ?></p>
                                        </div>
                                        <button class="closeBtn" type="button" data-modal-close aria-label="Close">
                                            <img src="<?= h($attendanceAssets) ?>/close.png" alt="">
                                        </button>
                                    </div>
                                    <div class="modalBody">
                                        <p class="detailDescription"><?= h((string) ($event['eventDescription'] ?? 'No description provided.')) ?></p>

                                        <div class="detailGrid">
                                            <div class="field"><span>Date</span><div class="value"><?= h(attendance_long_date($event['eventDate'])) ?></div></div>
                                            <div class="field"><span>Time</span><div class="value"><?= h(attendance_time($event['eventStartTime'])) ?><?= !empty($event['eventEndTime']) ? ' - ' . h(attendance_time($event['eventEndTime'])) : '' ?></div></div>
                                            <div class="field"><span>Location</span><div class="value"><?= h($event['eventLocation']) ?></div></div>
                                            <div class="field"><span>Participation fee</span><div class="value"><?= $fee > 0 ? attendance_money($fee) : 'Free &mdash; just participate, no payment needed' ?></div></div>
                                            <div class="field"><span>Open to</span><div class="value"><?= h(attendance_event_audience($event)) ?></div></div>
                                            <?php if ($fee > 0): ?>
                                                <div class="field"><span>Payment deadline</span><div class="value"><?= !empty($event['paymentDeadline']) ? h(attendance_long_date((string) $event['paymentDeadline'])) : 'No deadline' ?></div></div>
                                            <?php endif; ?>
                                            <?php if ($myPaymentStatus === 'Refund Pending'): ?>
                                                <div class="field"><span>Refund</span><div class="value">Pending approval &mdash; the event was cancelled</div></div>
                                            <?php elseif ($myPaymentStatus === 'Refunded'): ?>
                                                <div class="field"><span>Refund</span><div class="value">Refunded &mdash; the event was cancelled</div></div>
                                            <?php endif; ?>
                                            <div class="field"><span>Registration</span><div class="value"><?= $myRegistration !== null ? 'You are registered' : 'Not registered yet' ?></div></div>
                                            <div class="field"><span>Status</span><div class="value"><?= h($status) ?></div></div>
                                        </div>

                                        <?php $linkedAnnouncements = $eventAnnouncements[(int) $event['eventID']] ?? []; ?>
                                        <?php if (count($linkedAnnouncements) > 0): ?>
                                            <div class="divider"></div>
                                            <h4 class="modalSectionTitle">Event announcements</h4>
                                            <div class="miniAnnounceList">
                                                <?php foreach ($linkedAnnouncements as $announcement): ?>
                                                    <div class="miniAnnounce">
                                                        <strong><?= h($announcement['announcementTitle']) ?></strong>
                                                        <p><?= h($announcement['announcementContent']) ?></p>
                                                        <span class="aMeta"><?= h(attendance_datetime((string) $announcement['createdAt'])) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($myRegistration !== null): ?>
                                            <div class="btnRow">
                                                <span class="badge registered">Registered</span>
                                                <?php if (in_array($status, ['Upcoming', 'Ongoing'], true) && in_array((string) ($myRegistration['paymentStatus'] ?? ''), ['', 'Unpaid', 'Declined'], true)): ?>
                                                    <form method="post" style="margin:0;">
                                                        <?= attendance_csrf_field() ?>
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="registration_id" value="<?= (int) $myRegistration['registrationID'] ?>">
                                                        <button class="btn red tiny" type="submit">Cancel registration</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif (in_array($status, ['Upcoming', 'Ongoing'], true)): ?>
                                            <form method="post" style="margin:0;">
                                                <?= attendance_csrf_field() ?>
                                                <input type="hidden" name="action" value="register">
                                                <input type="hidden" name="event_id" value="<?= (int) $event['eventID'] ?>">
                                                <button class="btn" type="submit">Register for this event</button>
                                            </form>
                                        <?php else: ?>
                                            <p class="hint">Registration is closed for this event.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="stack">
        <section class="sideCard orange">
            <div class="miniTitle">
                <img src="<?= h($attendanceAssets) ?>/bell.png" alt="">
                Upcoming Events
            </div>

            <?php if (count($upcomingPreview) === 0): ?>
                <div class="sideEmpty">
                    <strong>No upcoming events scheduled yet.</strong>
                </div>
            <?php else: ?>
                <ul class="upcomingList">
                    <?php foreach ($upcomingPreview as $event): ?>
                        <li>
                            <a href="<?= h($attendanceBase) ?>/student/event-announcement.php">
                                <span class="dateBox">
                                    <b><?= h(date('d', strtotime($event['eventDate']))) ?></b>
                                    <small><?= h(date('M', strtotime($event['eventDate']))) ?></small>
                                </span>
                                <span class="upText">
                                    <strong><?= h($event['eventTitle']) ?></strong>
                                    <small><?= h(attendance_time($event['eventStartTime'])) ?> &middot; <?= h($event['eventLocation']) ?></small>
                                </span>
                                <span class="arrow"><img src="<?= h($attendanceAssets) ?>/arrow-right.png" alt=""></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="joinCard">
            <div class="joinArt">
                <img src="<?= h($attendanceAssets) ?>/megaphone.png" alt="">
                <img src="<?= h($attendanceAssets) ?>/users.png" alt="">
            </div>
            <strong>Be part of our<br>Campus Events!</strong>
            <p>Join, participate, and make memories with your peers.</p>
            <a class="btn" href="<?= h($attendanceBase) ?>/student/event-attendance.php">
                View All Events
                <img src="<?= h($attendanceAssets) ?>/arrow-right.png" alt="">
            </a>
        </section>
    </div>
</div>

<?php attendance_page_end(); ?>
