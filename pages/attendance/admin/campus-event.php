<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/attendance-base.php';

attendance_guard('admin');

/* Lock na ang event kapag nagsimula na (date + start time ang basehan).
   Halimbawa: 1:00 PM ang start, 1:01 PM na -> hindi na pwedeng i-edit o i-cancel. */
function event_has_started(array $event): bool
{
    $date = trim((string) ($event['eventDate'] ?? ''));
    $startTime = trim((string) ($event['eventStartTime'] ?? ''));

    if ($date === '') {
        return false;
    }

    $startTimestamp = strtotime($date . ' ' . ($startTime !== '' ? $startTime : '00:00:00'));

    return $startTimestamp !== false && $startTimestamp <= time();
}

$currentUrl = $attendanceBase . '/admin/campus-event.php';
$view = ($_GET['view'] ?? 'active') === 'archived' ? 'archived' : 'active';

// Filter ng status sa active events: Upcoming, Ongoing, Ended, Cancelled
$statusFilter = (string) ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'Upcoming', 'Ongoing', 'Ended', 'Cancelled'], true)) {
    $statusFilter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    attendance_check_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $eventId = (int) ($_POST['event_id'] ?? 0);

    try {
        if ($action === 'create') {
            $title = trim((string) ($_POST['event_title'] ?? ''));
            $date = trim((string) ($_POST['event_date'] ?? ''));
            $startTime = trim((string) ($_POST['event_start_time'] ?? ''));
            $endTime = trim((string) ($_POST['event_end_time'] ?? ''));
            $location = trim((string) ($_POST['event_location'] ?? ''));
            $description = trim((string) ($_POST['event_description'] ?? ''));
            $fee = (float) ($_POST['participation_fee'] ?? 0);
            $eventCourse = (int) ($_POST['course_id'] ?? 0);
            $paymentStart = trim((string) ($_POST['payment_start_date'] ?? ''));
            $paymentDeadline = trim((string) ($_POST['payment_deadline'] ?? ''));

            // required lahat maliban sa payment dates (paid event lang yun)
            if ($title === '' || $date === '' || $startTime === '' || $endTime === ''
                || $location === '' || $description === '') {
                attendance_flash('error', 'Title, date, start time, end time, location and description are required.');
            } elseif ($fee > 0 && ($paymentStart === '' || $paymentDeadline === '')) {
                attendance_flash('error', 'A paid event needs a payment start date and a payment deadline.');
            } elseif ($paymentStart !== '' && $paymentDeadline !== '' && $paymentDeadline < $paymentStart) {
                attendance_flash('error', 'The payment deadline cannot be earlier than the payment start date.');
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO tblevent
                     (adminID, courseID, eventTitle, eventDescription, eventDate, eventStartTime,
                      eventEndTime, eventLocation, participationFee, paymentStartDate, paymentDeadline, eventStatus)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([
                    $viewerId,
                    $eventCourse > 0 ? $eventCourse : null,
                    $title,
                    $description !== '' ? $description : null,
                    $date,
                    $startTime,
                    $endTime !== '' ? $endTime : null,
                    $location,
                    max(0, $fee),
                    // free event: huwag nang lagyan ng payment dates, sali lang
                    $fee > 0 && $paymentStart !== '' ? $paymentStart : null,
                    $fee > 0 && $paymentDeadline !== '' ? $paymentDeadline : null,
                    // status ay automatic na base sa date, hindi na pinipili
                    'Upcoming',
                ]);
                attendance_flash('success', 'Event created.');
            }
        } elseif ($action === 'update') {
            $title = trim((string) ($_POST['event_title'] ?? ''));
            $date = trim((string) ($_POST['event_date'] ?? ''));
            $startTime = trim((string) ($_POST['event_start_time'] ?? ''));
            $endTime = trim((string) ($_POST['event_end_time'] ?? ''));
            $location = trim((string) ($_POST['event_location'] ?? ''));
            $description = trim((string) ($_POST['event_description'] ?? ''));
            $fee = (float) ($_POST['participation_fee'] ?? 0);
            $eventCourse = (int) ($_POST['course_id'] ?? 0);
            $paymentStart = trim((string) ($_POST['payment_start_date'] ?? ''));
            $paymentDeadline = trim((string) ($_POST['payment_deadline'] ?? ''));

            // tanong muna kung kelan ang event: kapag nagsimula na sya
            // (date + start time), lock na, hindi na pwedeng i-edit
            $existingEventStatement = $pdo->prepare('SELECT eventDate, eventStartTime FROM tblevent WHERE eventID = ? AND archivedAt IS NULL');
            $existingEventStatement->execute([$eventId]);
            $existingEvent = $existingEventStatement->fetch();

            if ($eventId <= 0 || $existingEvent === false) {
                attendance_flash('error', 'That event could not be found.');
            } elseif (event_has_started($existingEvent)) {
                attendance_flash('error', 'This event has already started, so it can no longer be edited.');
            } elseif ($title === '' || $date === '' || $startTime === '' || $endTime === ''
                || $location === '' || $description === '') {
                attendance_flash('error', 'Title, date, start time, end time, location and description are required.');
            } elseif ($fee > 0 && ($paymentStart === '' || $paymentDeadline === '')) {
                attendance_flash('error', 'A paid event needs a payment start date and a payment deadline.');
            } elseif ($paymentStart !== '' && $paymentDeadline !== '' && $paymentDeadline < $paymentStart) {
                attendance_flash('error', 'The payment deadline cannot be earlier than the payment start date.');
            } else {
                $update = $pdo->prepare(
                    'UPDATE tblevent
                     SET courseID = ?, eventTitle = ?, eventDescription = ?, eventDate = ?,
                         eventStartTime = ?, eventEndTime = ?, eventLocation = ?, participationFee = ?,
                         paymentStartDate = ?, paymentDeadline = ?
                     WHERE eventID = ? AND archivedAt IS NULL'
                );
                $update->execute([
                    $eventCourse > 0 ? $eventCourse : null,
                    $title,
                    $description !== '' ? $description : null,
                    $date,
                    $startTime,
                    $endTime !== '' ? $endTime : null,
                    $location,
                    max(0, $fee),
                    // free event: huwag nang lagyan ng payment dates, sali lang
                    $fee > 0 && $paymentStart !== '' ? $paymentStart : null,
                    $fee > 0 && $paymentDeadline !== '' ? $paymentDeadline : null,
                    $eventId,
                ]);

                attendance_flash('success', 'Event updated.');
            }
        } elseif ($action === 'cancel') {
            // cancel = hindi na archive, naiiwan pa rin sa table at nakikita pa rin
            // ng students at rep. Lahat ng bayad na, nire-refund.
            $check = $pdo->prepare('SELECT eventStatus, eventDate, eventStartTime FROM tblevent WHERE eventID = ? AND archivedAt IS NULL');
            $check->execute([$eventId]);
            $cancelRow = $check->fetch();
            $currentStatus = $cancelRow === false ? false : (string) $cancelRow['eventStatus'];

            if ($cancelRow === false) {
                attendance_flash('error', 'That event could not be found.');
            } elseif (event_has_started($cancelRow)) {
                // nagsimula na ang event: sarado na ang cancel, ongoing na eh
                attendance_flash('error', 'This event has already started, so it can no longer be cancelled.');
            } elseif ($currentStatus === 'Cancelled') {
                attendance_flash('error', 'That event is already cancelled.');
            } else {
                $pdo->beginTransaction();

                $cancel = $pdo->prepare(
                    "UPDATE tblevent SET eventStatus = 'Cancelled'
                     WHERE eventID = ? AND archivedAt IS NULL"
                );
                $cancel->execute([$eventId]);

                // refund: hindi agad Refunded, kailangan pa i-approve
                $refund = $pdo->prepare(
                    "UPDATE tbleventpayment p
                     JOIN tbleventregistration r ON r.registrationID = p.registrationID
                     SET p.paymentStatus = 'Refund Pending',
                         p.processedByAdminID = ?, p.processedByStudentID = NULL,
                         p.paymentRemarks = CONCAT(LEFT(COALESCE(p.paymentRemarks, ''), 160), ' | Event cancelled - refund needs approval')
                     WHERE r.eventID = ? AND p.paymentStatus = 'Paid'"
                );
                $refund->execute([$viewerId, $eventId]);
                $refunded = $refund->rowCount();

                // pending pa lang, wala pang natanggap na pera
                $resetPending = $pdo->prepare(
                    "UPDATE tbleventpayment p
                     JOIN tbleventregistration r ON r.registrationID = p.registrationID
                     SET p.paymentStatus = 'Unpaid',
                         p.processedByAdminID = ?, p.processedByStudentID = NULL,
                         p.paymentRemarks = 'Event cancelled before payment'
                     WHERE r.eventID = ? AND p.paymentStatus IN ('Unpaid', 'Pending Cash', 'For Verification')"
                );
                $resetPending->execute([$viewerId, $eventId]);

                $pdo->commit();

                attendance_flash(
                    'success',
                    $refunded > 0
                        ? 'Event cancelled. ' . $refunded . ' paid fee' . ($refunded === 1 ? '' : 's') . ' waiting for refund approval. The event stays in the table.'
                        : 'Event cancelled. It stays in the table and students still see it as Cancelled.'
                );
            }
        } elseif ($action === 'uncancel') {
            // restore ng cancelled event: balik sa table at balik sa Paid
            // yung refund na hindi pa na-approve
            $check = $pdo->prepare('SELECT eventStatus FROM tblevent WHERE eventID = ? AND archivedAt IS NULL');
            $check->execute([$eventId]);
            $currentStatus = $check->fetchColumn();

            if ($currentStatus === false) {
                attendance_flash('error', 'That event could not be found.');
            } elseif ((string) $currentStatus !== 'Cancelled') {
                attendance_flash('error', 'That event is not cancelled.');
            } else {
                $pdo->beginTransaction();

                $restore = $pdo->prepare(
                    "UPDATE tblevent SET eventStatus = 'Upcoming'
                     WHERE eventID = ? AND archivedAt IS NULL"
                );
                $restore->execute([$eventId]);

                // balik sa simula ang bayad: Unpaid ulit, walang binayad,
                // tapos yung student ang pipili kung cash or Online Payment bago
                // ma-approve ng rep o admin
                $resetPayments = $pdo->prepare(
                    "UPDATE tbleventpayment p
                     JOIN tbleventregistration r ON r.registrationID = p.registrationID
                     SET p.paymentStatus = 'Unpaid',
                         p.amountPaid = 0,
                         p.receivedByAdminID = NULL,
                        p.processedByAdminID = ?,
                         p.processedByStudentID = NULL,
                         p.paymentDate = NULL,
                         p.paymentRemarks = 'Event restored - pay again and choose cash or Online Payment.'
                     WHERE r.eventID = ?"
                );
                $resetPayments->execute([$viewerId, $eventId]);
                $reset = $resetPayments->rowCount();

                $pdo->commit();

                attendance_flash(
                    'success',
                    $reset > 0
                        ? 'Event restored. ' . $reset . ' fee' . ($reset === 1 ? '' : 's') . ' back to Unpaid, so students pay again and choose cash or Online Payment.'
                        : 'Event restored. It is active again.'
                );
            }
        } elseif ($action === 'register_all') {
            $eventStatement = $pdo->prepare('SELECT eventTitle FROM tblevent WHERE eventID = ? AND archivedAt IS NULL');
            $eventStatement->execute([$eventId]);
            $eventTitle = $eventStatement->fetchColumn();

            if ($eventTitle === false) {
                attendance_flash('error', 'That event could not be found.');
            } else {
                $registered = attendance_register_all_students($eventId);
                attendance_flash(
                    'success',
                    $registered > 0
                        ? $registered . ' student' . ($registered === 1 ? '' : 's') . ' registered for ' . $eventTitle . '. They only need to pay the fee now.'
                        : 'All eligible students are already registered for ' . $eventTitle . '.'
                );
            }
        } elseif ($action === 'archive') {
            $update = $pdo->prepare(
                'UPDATE tblevent SET archivedAt = NOW() WHERE eventID = ? AND archivedAt IS NULL'
            );
            $update->execute([$eventId]);
            attendance_flash('success', 'Event deleted to the archive. Nothing was erased, and you can restore it from the archive list.');
        } elseif ($action === 'restore') {
            $eventStatement = $pdo->prepare('SELECT eventDate, eventStartTime, eventEndTime FROM tblevent WHERE eventID = ?');
            $eventStatement->execute([$eventId]);
            $restoredEvent = $eventStatement->fetch();

            if ($restoredEvent === false) {
                attendance_flash('error', 'That event could not be found.');
            } else {
                // Apat lang ang status: Upcoming, Ongoing, Ended, Cancelled
                $restoredStatus = attendance_event_status([
                    'eventStatus' => 'Upcoming',
                    'eventDate' => (string) $restoredEvent['eventDate'],
                    'eventStartTime' => (string) $restoredEvent['eventStartTime'],
                    'eventEndTime' => (string) ($restoredEvent['eventEndTime'] ?? ''),
                ]);

                $update = $pdo->prepare(
                    'UPDATE tblevent SET archivedAt = NULL, eventStatus = ? WHERE eventID = ? AND archivedAt IS NOT NULL'
                );
                $update->execute([$restoredStatus, $eventId]);
                attendance_flash('success', 'Event restored with status ' . $restoredStatus . '.');
            }
        } elseif ($action === 'delete_permanent') {
            $archivedCheck = $pdo->prepare(
                'SELECT eventID FROM tblevent WHERE eventID = ? AND archivedAt IS NOT NULL'
            );
            $archivedCheck->execute([$eventId]);

            if (!$archivedCheck->fetchColumn()) {
                attendance_flash('error', 'Only archived events can be deleted permanently.');
            } else {
                $delete = $pdo->prepare('DELETE FROM tblevent WHERE eventID = ?');
                $delete->execute([$eventId]);
                attendance_flash('success', 'Event deleted permanently with its registrations, attendance, payments and announcements.');
            }
        } else {
            attendance_flash('error', 'Unknown action.');
        }
    } catch (PDOException $exception) {
        attendance_flash('error', 'The event could not be saved. Please check the details and try again.');
    }

    attendance_redirect($currentUrl . '?view=' . $view . '&status=' . urlencode($statusFilter));
}

/* Events na active at events na nasa archive */
$courses = $pdo->query('SELECT courseID, courseName FROM tblcourse ORDER BY courseName')->fetchAll();

$events = $pdo->query(
    'SELECT e.*, a.adminFirstName, a.adminLastName, c.courseName,
            (SELECT COUNT(*) FROM tbleventregistration r WHERE r.eventID = e.eventID) AS registrations,
            (SELECT COUNT(*) FROM tbleventpayment p
                JOIN tbleventregistration r ON r.registrationID = p.registrationID
                WHERE r.eventID = e.eventID AND p.paymentStatus = \'Paid\') AS paidPayments,
            (SELECT COUNT(*) FROM tbleventpayment p
                JOIN tbleventregistration r ON r.registrationID = p.registrationID
                WHERE r.eventID = e.eventID
                  AND p.paymentStatus IN (\'Refund Pending\', \'Refunded\')) AS refundedPayments,
            (SELECT COALESCE(SUM(p.amountPaid), 0) FROM tbleventpayment p
                JOIN tbleventregistration r ON r.registrationID = p.registrationID
                WHERE r.eventID = e.eventID) AS collected,
            (SELECT COUNT(*) FROM tblannouncement an WHERE an.eventID = e.eventID) AS announcements,
            (SELECT COUNT(*) FROM tblstudent s
                LEFT JOIN tblblock b ON b.blockID = s.blockID
                WHERE (e.courseID IS NULL OR b.courseID = e.courseID)
                  AND NOT EXISTS (SELECT 1 FROM tbleventregistration r
                                  WHERE r.eventID = e.eventID AND r.studentID = s.studentID)) AS unregisteredCount
     FROM tblevent e
     JOIN tbladmin a ON a.adminID = e.adminID
     LEFT JOIN tblcourse c ON c.courseID = e.courseID
     WHERE e.archivedAt IS NULL
     ORDER BY e.eventDate DESC, e.eventStartTime DESC'
)->fetchAll();

$archivedEvents = $pdo->query(
    'SELECT e.*, a.adminFirstName, a.adminLastName,
            (SELECT COUNT(*) FROM tbleventregistration r WHERE r.eventID = e.eventID) AS registrations,
            (SELECT COUNT(*) FROM tblannouncement an WHERE an.eventID = e.eventID) AS announcements
     FROM tblevent e
     JOIN tbladmin a ON a.adminID = e.adminID
     WHERE e.archivedAt IS NOT NULL
     ORDER BY e.archivedAt DESC'
)->fetchAll();

$totalEvents = count($events);
$upcomingCount = 0;
$collected = 0.00;
foreach ($events as $event) {
    if (attendance_event_status($event) === 'Upcoming') {
        $upcomingCount++;
    }
    $collected += (float) $event['collected'];
}

$archivedCount = count($archivedEvents);

// Apply status filter for display only (counts above stay based on all active events)
$displayEvents = $events;
if ($view !== 'archived' && $statusFilter !== 'all') {
    $displayEvents = array_values(array_filter($events, static function (array $event) use ($statusFilter): bool {
        return attendance_event_status($event) === $statusFilter;
    }));
}

attendance_page_start('events');
?>

<div class="stack">
    <div class="highlightGrid">
        <section class="highlight light">
            <div>
                <div class="eyebrow">Active events</div>
                <strong><?= $totalEvents ?></strong>
                <small class="muted">Not yet archived</small>
            </div>
        </section>
        <section class="highlight light">
            <div>
                <div class="eyebrow">Upcoming</div>
                <strong><?= $upcomingCount ?></strong>
                <small class="muted">Still open for registration</small>
            </div>
        </section>
        <section class="highlight light">
            <div>
                <div class="eyebrow">Total collected</div>
                <strong><?= attendance_money($collected) ?></strong>
                <small class="muted">Approved payments</small>
            </div>
            <button class="btn" type="button" data-modal-open="createEventModal">
                <img src="<?= h($attendanceAssets) ?>/plus.png" alt="">
                Create event
            </button>
        </section>
    </div>

    <section class="card">
        <div class="cardHead">
            <img src="<?= h($attendanceAssets) ?>/calendar.png" alt="">
            <h3><?= $view === 'archived' ? 'Archived events' : 'Campus events' ?></h3>
            <span class="spacer"></span>

            <?php if ($view === 'archived'): ?>
                <a class="pillLink" href="<?= h($currentUrl) ?>">
                    Active events
                    <img src="<?= h($attendanceAssets) ?>/chevron-right.png" alt="">
                </a>
            <?php else: ?>
                <a class="pillLink" href="<?= h($currentUrl) ?>?view=archived">
                    Archive (<?= $archivedCount ?>)
                    <img src="<?= h($attendanceAssets) ?>/chevron-right.png" alt="">
                </a>
                <a class="pillLink" href="<?= h($attendanceBase) ?>/admin/event-announcement.php">
                    Announcements
                    <img src="<?= h($attendanceAssets) ?>/chevron-right.png" alt="">
                </a>
            <?php endif; ?>
        </div>

        <?php if ($view === 'archived'): ?>
            <?php if ($archivedCount === 0): ?>
                <div class="emptyState">
                    <img src="<?= h($attendanceAssets) ?>/file.png" alt="">
                    <strong>The archive is empty</strong>
                    <p>Deleted events are kept here so you can restore them.</p>
                </div>
            <?php else: ?>
                <div class="tableWrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Schedule</th>
                                <th>Status</th>
                                <th>Registrations</th>
                                <th>Archived on</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($archivedEvents as $event): ?>
                                <tr>
                                    <td class="strong">
                                        <?= h($event['eventTitle']) ?>
                                        <span class="sub">By <?= h(trim($event['adminFirstName'] . ' ' . $event['adminLastName'])) ?></span>
                                    </td>
                                    <td>
                                        <?= h(attendance_long_date($event['eventDate'])) ?>
                                        <span class="sub"><?= h(attendance_time($event['eventStartTime'])) ?></span>
                                    </td>
                                    <td><?= attendance_badge(attendance_event_status($event)) ?></td>
                                    <td>
                                        <?= (int) $event['registrations'] ?> registered
                                        <span class="sub"><?= (int) $event['announcements'] ?> announcements</span>
                                    </td>
                                    <td class="nowrap"><?= h(attendance_datetime($event['archivedAt'])) ?></td>
                                    <td>
                                        <div class="actionGrid">
                                            <form method="post" style="margin:0;" onsubmit="return confirm('Restore this event from the archive? It goes back to the Campus Events list and follows its own date and time for the status.');">
                                                <?= attendance_csrf_field() ?>
                                                <input type="hidden" name="action" value="restore">
                                                <input type="hidden" name="event_id" value="<?= (int) $event['eventID'] ?>">
                                                <button class="btn tiny" type="submit">Restore</button>
                                            </form>
                                            <form method="post" style="margin:0;" onsubmit="return confirm('Delete this event permanently? Its registrations, attendance, payments and announcements will be removed and cannot be restored.');">
                                                <?= attendance_csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_permanent">
                                                <input type="hidden" name="event_id" value="<?= (int) $event['eventID'] ?>">
                                                <button class="btn red tiny" type="submit">Delete permanently</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php elseif ($totalEvents === 0): ?>
            <div class="emptyState">
                <img src="<?= h($attendanceAssets) ?>/calendar.png" alt="">
                <strong>No campus events yet</strong>
                <p>Create the first event so students can register and pay.</p>
            </div>
        <?php elseif (count($displayEvents) === 0): ?>
            <div class="emptyState">
                <img src="<?= h($attendanceAssets) ?>/calendar.png" alt="">
                <strong>No events match this filter</strong>
                <p>Try another status or reset the filter.</p>
            </div>
        <?php else: ?>
            <div class="filterRow">
                <form method="get" class="btnRow">
                    <input type="hidden" name="view" value="active">
                    <select class="selectInline" name="status" aria-label="Filter by status" onchange="this.form.submit()">
                        <?php foreach (['all' => 'All statuses', 'Upcoming' => 'Upcoming', 'Ongoing' => 'Ongoing', 'Ended' => 'Ended', 'Cancelled' => 'Cancelled'] as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($statusFilter !== 'all'): ?>
                        <a class="btn gray tiny" href="<?= h($currentUrl) ?>?view=active">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="tableWrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Schedule</th>
                            <th>Location</th>
                            <th>Fee</th>
                            <th>Registrations</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayEvents as $event): ?>
                            <?php
                            $status = attendance_event_status($event);
                            // kapag nagsimula na ang event (date + start time), locked na ang edit at cancel
                            $eventLocked = event_has_started($event);
                            ?>
                            <tr>
                                <td class="strong">
                                    <?= h($event['eventTitle']) ?>
                                    <span class="sub">
                                        By <?= h(trim($event['adminFirstName'] . ' ' . $event['adminLastName'])) ?>
                                        &middot; <?= h(attendance_event_audience($event)) ?>
                                        <?php if ((int) $event['announcements'] > 0): ?>
                                            &middot; <?= (int) $event['announcements'] ?> announcement<?= (int) $event['announcements'] === 1 ? '' : 's' ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?= h(attendance_long_date($event['eventDate'])) ?>
                                    <span class="sub"><?= h(attendance_time($event['eventStartTime'])) ?><?= !empty($event['eventEndTime']) ? ' - ' . h(attendance_time($event['eventEndTime'])) : '' ?></span>
                                </td>
                                <td><?= h($event['eventLocation']) ?></td>
                                <td class="nowrap">
                                    <?= (float) $event['participationFee'] > 0 ? attendance_money((float) $event['participationFee']) : 'Free' ?>
                                    <span class="sub">
                                        <?php if ((float) $event['participationFee'] <= 0): ?>
                                            No payment &dash; participate only
                                        <?php elseif (!empty($event['paymentDeadline'])): ?>
                                            Pay by <?= h(attendance_long_date((string) $event['paymentDeadline'])) ?>
                                        <?php else: ?>
                                            No deadline
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?= (int) $event['registrations'] ?> registered
                                    <span class="sub">
                                        <?= (int) $event['paidPayments'] ?> paid<?= (int) $event['refundedPayments'] > 0 ? ' &middot; ' . (int) $event['refundedPayments'] . ' refund' : '' ?>
                                    </span>
                                </td>
                                <td><?= attendance_badge($status) ?></td>
                                <td>
                                    <div class="actionGrid">
                                        <?php if ((string) $event['eventStatus'] !== 'Cancelled'): ?>
                                            <?php if ($eventLocked): ?>
                                                <button class="btn gray tiny" type="button" disabled title="This event has already started, so it can no longer be cancelled.">Cancel event</button>
                                            <?php else: ?>
                                                <form method="post" style="margin:0;" onsubmit="return confirm('Cancel this event? It stays in the table and students still see it as Cancelled. Every paid fee goes to Refund Pending and needs approval on the Event Payment page.');">
                                                    <?= attendance_csrf_field() ?>
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="event_id" value="<?= (int) $event['eventID'] ?>">
                                                    <button class="btn red tiny" type="submit">Cancel event</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <form method="post" style="margin:0;" onsubmit="return confirm('Restore this cancelled event? It goes back to active. Every fee starts over as Unpaid, so students pick cash or Online Payment again before the block rep or admin approves the payment.');">
                                                <?= attendance_csrf_field() ?>
                                                <input type="hidden" name="action" value="uncancel">
                                                <input type="hidden" name="event_id" value="<?= (int) $event['eventID'] ?>">
                                                <button class="btn tiny" type="submit">Restore event</button>
                                            </form>
                                        <?php endif; ?>
                                        <button class="btn ghost tiny" type="button" data-modal-open="viewEventModal<?= (int) $event['eventID'] ?>">View</button>
                                        <?php if ($eventLocked): ?>
                                            <button class="btn gray tiny" type="button" disabled title="This event has already started, so it can no longer be edited.">Edit</button>
                                        <?php else: ?>
                                            <button class="btn ghost tiny" type="button" data-modal-open="editEventModal<?= (int) $event['eventID'] ?>">Edit</button>
                                        <?php endif; ?>
                                        <?php if ((int) ($event['unregisteredCount'] ?? 0) > 0): ?>
                                            <form method="post" style="margin:0;" onsubmit="return confirm('Register all students in this event\'s course? Students who are already registered will be skipped.');">
                                                <?= attendance_csrf_field() ?>
                                                <input type="hidden" name="action" value="register_all">
                                                <input type="hidden" name="event_id" value="<?= (int) $event['eventID'] ?>">
                                                <button class="btn tiny" type="submit">Register all students</button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn gray tiny" type="button" disabled>Registered</button>
                                        <?php endif; ?>
                                        <a class="btn ghost tiny" href="<?= h($attendanceBase) ?>/admin/event-announcement.php?event=<?= (int) $event['eventID'] ?>">Announce</a>
                                        <form method="post" style="margin:0;" onsubmit="return confirm('Delete (archive) this event?\n\nIt will be hidden from students and attendance marking, but nothing is erased. Restore it anytime from Archive.');">
                                            <?= attendance_csrf_field() ?>
                                            <input type="hidden" name="action" value="archive">
                                            <input type="hidden" name="event_id" value="<?= (int) $event['eventID'] ?>">
                                            <button class="btn red tiny" type="submit" title="Moves the event to the archive. You can restore it later.">Delete (archive)</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="infoBox">
            <h4><img src="<?= h($attendanceAssets) ?>/info.png" alt=""> Event archive</h4>
            <ul>
                <li>Edit lets you change any detail of an existing event, including its course, fee and payment dates.</li>
                <li>Once the event starts (its date and start time), the event is locked: both Edit and Cancel event are disabled, so an event that is already ongoing or finished stays as it is. Before it starts, you can still edit or cancel it.</li>
                <li>An event opened to one course is only visible to students of that course. All courses shows it to everyone.</li>
                <li>Students can pay from the payment opens date until the payment deadline. Unpaid fees after the deadline show as Late Payment.</li>
                <li>Delete (archive) hides an event from students and attendance marking, but keeps everything so you can restore it.</li>
                <li>Cancelling an event keeps it in the table. Students and block reps still see it marked as Cancelled, and every paid fee goes to Refund Pending.</li>
                <li>A refund is only final after it is approved on the Event Payment page. Restore event brings a cancelled event back and starts its fees over as Unpaid, so students choose cash or Online Payment again.</li>
                <li>Archived events are hidden from students, block representatives and attendance marking. Cancelled events are not archived, so they stay visible.</li>
                <li>Restore brings the event back with a status based on its date and time (Upcoming, Ongoing or Ended), and its registrations, attendance and payments come back too.</li>
                <li>Delete permanently is only available inside the archive and cannot be undone.</li>
            </ul>
        </div>
    </section>
</div>

<?php foreach ($displayEvents as $event): ?>
    <?php if (event_has_started($event)): ?>
        <?php continue; // nagsimula na ang event, wala nang edit modal ?>
    <?php endif; ?>
    <div class="modal" id="editEventModal<?= (int) $event['eventID'] ?>" hidden>
        <div class="modalBackdrop"></div>
        <div class="modalPanel">
            <div class="modalHead">
                <div>
                    <h3>Edit event</h3>
                    <p>Update the details of <?= h($event['eventTitle']) ?>.</p>
                </div>
                <button class="closeBtn" type="button" data-modal-close aria-label="Close">
                    <img src="<?= h($attendanceAssets) ?>/close.png" alt="">
                </button>
            </div>
            <div class="modalBody">
                <form method="post" data-event-form>
                    <?= attendance_csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="event_id" value="<?= (int) $event['eventID'] ?>">

                    <div class="formGrid">
                        <label class="field wide">
                            <span>Event title</span>
                            <input type="text" name="event_title" maxlength="150" value="<?= h((string) $event['eventTitle']) ?>" required>
                        </label>
                        <label class="field">
                            <span>Date</span>
                            <input type="date" name="event_date" value="<?= h((string) $event['eventDate']) ?>" required>
                        </label>
                        <label class="field">
                            <span>Start time</span>
                            <input type="time" name="event_start_time" value="<?= h(substr((string) $event['eventStartTime'], 0, 5)) ?>" required>
                        </label>
                        <label class="field">
                            <span>End time</span>
                            <input type="time" name="event_end_time" value="<?= h(!empty($event['eventEndTime']) ? substr((string) $event['eventEndTime'], 0, 5) : '') ?>" required>
                        </label>
                        <label class="field">
                            <span>Location</span>
                            <input type="text" name="event_location" maxlength="150" value="<?= h((string) $event['eventLocation']) ?>" required>
                        </label>
                        <label class="field">
                            <span>Participation fee</span>
                            <input type="number" name="participation_fee" min="0" step="0.01" value="<?= h(number_format((float) $event['participationFee'], 2, '.', '')) ?>">
                        </label>
                        <label class="field">
                            <span>Open to</span>
                            <select name="course_id">
                                <option value="0" <?= (int) $event['courseID'] === 0 ? 'selected' : '' ?>>All courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= (int) $course['courseID'] ?>" <?= (int) $event['courseID'] === (int) $course['courseID'] ? 'selected' : '' ?>><?= h($course['courseName']) ?> only</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?= attendance_program_guide_html() ?>
                        <label class="field">
                            <span>Payment opens</span>
                            <input type="date" name="payment_start_date" value="<?= h((string) ($event['paymentStartDate'] ?? '')) ?>">
                        </label>
                        <label class="field">
                            <span>Payment deadline</span>
                            <input type="date" name="payment_deadline" value="<?= h((string) ($event['paymentDeadline'] ?? '')) ?>">
                        </label>
                        <label class="field wide">
                            <span>Description</span>
                            <textarea name="event_description" placeholder="What is this event about?" required><?= h((string) ($event['eventDescription'] ?? '')) ?></textarea>
                        </label>
                    </div>

                    <p class="hint">Payment opens and payment deadline are only needed for a paid event. A free event (fee 0) needs no payment dates. A cancelled event stays in the table, and every paid fee goes to Refund Pending first until it is approved.</p>

                    <div class="formActions">
                        <button class="btn" type="submit">Save changes</button>
                        <button class="btn gray" type="button" data-modal-close>Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php foreach ($displayEvents as $event): ?>
    <?php $viewStatus = attendance_event_status($event); ?>
    <div class="modal" id="viewEventModal<?= (int) $event['eventID'] ?>" hidden>
        <div class="modalBackdrop"></div>
        <div class="modalPanel">
            <div class="modalHead">
                <div>
                    <h3><?= h($event['eventTitle']) ?></h3>
                    <p><?= h($viewStatus) ?> event &middot; Organized by <?= h(trim($event['adminFirstName'] . ' ' . $event['adminLastName'])) ?></p>
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
                    <div class="field"><span>Participation fee</span><div class="value"><?= (float) $event['participationFee'] > 0 ? attendance_money((float) $event['participationFee']) : 'Free' ?></div></div>
                    <div class="field"><span>Open to</span><div class="value"><?= h(attendance_event_audience($event)) ?></div></div>
                    <?php if ((float) $event['participationFee'] > 0): ?>
                        <div class="field"><span>Payment opens</span><div class="value"><?= !empty($event['paymentStartDate']) ? h(attendance_long_date((string) $event['paymentStartDate'])) : 'No start date' ?></div></div>
                        <div class="field"><span>Payment deadline</span><div class="value"><?= !empty($event['paymentDeadline']) ? h(attendance_long_date((string) $event['paymentDeadline'])) : 'No deadline' ?></div></div>
                    <?php endif; ?>
                    <div class="field"><span>Registrations</span><div class="value"><?= (int) $event['registrations'] ?> registered &middot; <?= (int) $event['paidPayments'] ?> paid<?= (int) $event['refundedPayments'] > 0 ? ' &middot; ' . (int) $event['refundedPayments'] . ' refund' : '' ?></div></div>
                    <div class="field"><span>Announcements</span><div class="value"><?= (int) $event['announcements'] ?></div></div>
                    <div class="field"><span>Status</span><div class="value"><?= h($viewStatus) ?></div></div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal" id="createEventModal" hidden>
    <div class="modalBackdrop"></div>
    <div class="modalPanel">
        <div class="modalHead">
            <div>
                <h3>Create campus event</h3>
                <p>Students can register once the event is saved.</p>
            </div>
            <button class="closeBtn" type="button" data-modal-close aria-label="Close">
                <img src="<?= h($attendanceAssets) ?>/close.png" alt="">
            </button>
        </div>
        <div class="modalBody">
            <form method="post" data-event-form>
                <?= attendance_csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="formGrid">
                    <label class="field wide">
                        <span>Event title</span>
                        <input type="text" name="event_title" maxlength="150" required>
                    </label>
                    <label class="field">
                        <span>Date</span>
                        <input type="date" name="event_date" required>
                    </label>
                    <label class="field">
                        <span>Start time</span>
                        <input type="time" name="event_start_time" required>
                    </label>
                    <label class="field">
                        <span>End time</span>
                        <input type="time" name="event_end_time" required>
                    </label>
                    <label class="field">
                        <span>Location</span>
                        <input type="text" name="event_location" maxlength="150" required>
                    </label>
                    <label class="field">
                        <span>Participation fee</span>
                        <input type="number" name="participation_fee" min="0" step="0.01" value="0">
                    </label>
                    <label class="field">
                        <span>Open to</span>
                        <select name="course_id">
                            <option value="0">All courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= (int) $course['courseID'] ?>"><?= h($course['courseName']) ?> only</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?= attendance_program_guide_html() ?>
                    <label class="field">
                        <span>Payment opens</span>
                        <input type="date" name="payment_start_date">
                    </label>
                    <label class="field">
                        <span>Payment deadline</span>
                        <input type="date" name="payment_deadline">
                    </label>
                    <label class="field wide">
                        <span>Description</span>
                        <textarea name="event_description" placeholder="What is this event about?" required></textarea>
                    </label>
                </div>

                <p class="hint">Payment opens and payment deadline are only needed for a paid event. Choose one course to keep the event for CS only, IT only, BSEMC, or ACT.</p>

                <div class="formActions">
                    <button class="btn" type="submit">Create event</button>
                    <button class="btn gray" type="button" data-modal-close>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* free event (fee 0) = walang payment dates, paid event = required */
document.querySelectorAll('[data-event-form]').forEach(function (form) {
    var feeInput = form.querySelector('[name="participation_fee"]');
    var paymentFields = form.querySelectorAll('[name="payment_start_date"], [name="payment_deadline"]');

    if (!feeInput) {
        return;
    }

    function syncPaymentFields() {
        var isPaid = parseFloat(feeInput.value || '0') > 0;
        paymentFields.forEach(function (field) {
            field.required = isPaid;
            field.disabled = !isPaid;
        });
    }

    feeInput.addEventListener('input', syncPaymentFields);
    syncPaymentFields();
});
</script>

<?php attendance_page_end(); ?>
