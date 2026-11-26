<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/attendance-base.php';

attendance_guard('rep');

$blockId = (int) $studentRecord['blockID'];
$blockCourseId = (int) ($studentRecord['courseID'] ?? 0);
$currentUrl = $attendanceBase . '/rep/event-attendance.php';

// registered = mga nakapag-register, unregistered = mga wala pang register
$view = (string) ($_GET['view'] ?? 'registered');
if (!in_array($view, ['registered', 'unregistered'], true)) {
    $view = 'registered';
}

// search ng student, pwede pangalan o student ID
$search = trim((string) ($_GET['q'] ?? ''));

/* ------------------------------------------------------------------
   Pag-mark ng attendance. Eksaktong oras ng server ang sinasave
   sa time in / time out at hindi na pwedeng i-edit pagkatapos.
------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    attendance_check_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $registrationId = (int) ($_POST['registration_id'] ?? 0);
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $redirectUrl = $currentUrl . '?event=' . $eventId . '&q=' . urlencode($search) . '&view=' . $view
        . '&page=' . max(1, (int) ($_GET['page'] ?? 1));

    try {
        $approvalStatement = $pdo->prepare(
            'SELECT approvalStatus FROM tbleventattendanceapproval WHERE eventID = ? AND blockID = ?'
        );
        $approvalStatement->execute([$eventId, $blockId]);
        $approvalStatus = (string) ($approvalStatement->fetchColumn() ?: '');

        if ($action === 'request_approval') {
            $eventStatement = $pdo->prepare(
                'SELECT eventID, eventDate, eventStartTime, eventEndTime, eventStatus, courseID
                 FROM tblevent
                 WHERE eventID = ? AND archivedAt IS NULL
                   AND (courseID IS NULL OR courseID = ?)'
            );
            $eventStatement->execute([$eventId, $blockCourseId]);
            $approvalEvent = $eventStatement->fetch();

            if ($approvalEvent === false) {
                attendance_flash('error', 'That event is not available to your block.');
            } elseif (attendance_event_status($approvalEvent) !== 'Ongoing') {
                attendance_flash('error', 'Attendance approval can only be requested while the event is in progress.');
            } elseif ($approvalStatus === 'Approved') {
                attendance_flash('error', 'Attendance is already approved for this block.');
            } elseif ($approvalStatus === 'Pending') {
                attendance_flash('error', 'An approval request is already pending.');
            } else {
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
                $requestStatement->execute([$eventId, $blockId, $viewerId]);
                attendance_flash('success', 'Attendance approval requested from an administrator.');
            }
        } elseif (in_array($action, ['mark_in', 'mark_out', 'mark_absent', 'undo_in'], true)
            && $approvalStatus !== 'Approved') {
            attendance_flash('error', 'An administrator must approve attendance for your block before you can record it.');
        } elseif (in_array($action, ['mark_in', 'mark_out', 'mark_absent', 'undo_in'], true)) {
            $check = $pdo->prepare(
                'SELECT r.registrationID, e.eventID, e.eventDate, e.eventStartTime, e.eventEndTime, e.eventStatus,
                        a.attendanceID, a.timeIn, a.timeOut
                 FROM tbleventregistration r
                 JOIN tblevent e ON e.eventID = r.eventID
                 JOIN tblstudent s ON s.studentID = r.studentID
                 LEFT JOIN tbleventattendance a ON a.registrationID = r.registrationID
                 WHERE r.registrationID = ? AND r.eventID = ? AND s.blockID = ?
                   AND s.archivedAt IS NULL AND (e.courseID IS NULL OR e.courseID = ?)'
            );
            $check->execute([$registrationId, $eventId, $blockId, $blockCourseId]);
            $record = $check->fetch();

            if ($record === false) {
                attendance_flash('error', 'That registration is not part of your active block or event.');
            } elseif (attendance_event_status($record) !== 'Ongoing') {
                attendance_flash('error', 'Attendance can only be recorded while the event is in progress.');
            } else {
                [$flashType, $message] = attendance_mark($record, $action, null, $viewerId);
                attendance_flash($flashType, $message);
            }
        } else {
            attendance_flash('error', 'Unknown attendance action.');
        }
    } catch (PDOException $exception) {
        attendance_flash('error', 'The attendance record could not be saved. Please try again.');
    }

    attendance_redirect($redirectUrl);
}

/* Data na kailangan ng page */
$eventsStatement = $pdo->prepare(
    'SELECT * FROM tblevent
     WHERE archivedAt IS NULL AND (courseID IS NULL OR courseID = ?)
     ORDER BY eventDate DESC, eventStartTime DESC'
);
$eventsStatement->execute([$blockCourseId]);
$events = $eventsStatement->fetchAll();

$selectedEvent = null;
$requestedEvent = (int) ($_GET['event'] ?? 0);
foreach ($events as $event) {
    if ((int) $event['eventID'] === $requestedEvent) {
        $selectedEvent = $event;
        break;
    }
}
if ($selectedEvent === null && count($events) > 0) {
    $selectedEvent = $events[0];
}

$eventId = $selectedEvent !== null ? (int) $selectedEvent['eventID'] : 0;
$eventStatus = $selectedEvent !== null ? attendance_event_status($selectedEvent) : '';
$approvalStatus = '';
if ($eventId > 0) {
    $approvalStatement = $pdo->prepare(
        'SELECT approvalStatus FROM tbleventattendanceapproval WHERE eventID = ? AND blockID = ?'
    );
    $approvalStatement->execute([$eventId, $blockId]);
    $approvalStatus = (string) ($approvalStatement->fetchColumn() ?: '');
}
$canMarkAttendance = $approvalStatus === 'Approved' && $eventStatus === 'Ongoing';

$registrations = [];
if ($eventId > 0) {
    $listSql = 'SELECT r.registrationID, r.registrationStatus,
                s.studentID, s.studentFirstName, s.studentLastName, s.studentMiddleName, s.studentExtensionName,
                b.classBlock,
                e.eventDate, e.eventStartTime, e.eventEndTime, e.eventStatus,
                a.attendanceStatus, a.timeIn, a.timeOut,
                markerAdmin.adminFirstName AS markerAdminFirstName,
                markerAdmin.adminLastName AS markerAdminLastName,
                markerStudent.studentFirstName AS markerStudentFirstName,
                markerStudent.studentLastName AS markerStudentLastName
         FROM tbleventregistration r
         JOIN tblevent e ON e.eventID = r.eventID
         JOIN tblstudent s ON s.studentID = r.studentID
         LEFT JOIN tblblock b ON b.blockID = s.blockID
         LEFT JOIN tbleventattendance a ON a.registrationID = r.registrationID
         LEFT JOIN tbladmin markerAdmin ON markerAdmin.adminID = a.markedByAdminID
         LEFT JOIN tblstudent markerStudent ON markerStudent.studentID = a.markedByStudentID
         WHERE r.eventID = ? AND s.blockID = ?';

    $listParams = [$eventId, $blockId];

    // search: pangalan o student ID
    if ($search !== '') {
        $like = '%' . $search . '%';
        $listSql .= " AND (s.studentFirstName LIKE ? OR s.studentLastName LIKE ?
                       OR CONCAT(s.studentLastName, ', ', s.studentFirstName) LIKE ?
                       OR CAST(s.studentID AS CHAR) LIKE ?)";
        $listParams[] = $like;
        $listParams[] = $like;
        $listParams[] = $like;
        $listParams[] = $like;
    }

    $listSql .= ' ORDER BY s.studentLastName, s.studentFirstName';

    $listStatement = $pdo->prepare($listSql);
    $listStatement->execute($listParams);
    $registrations = $listStatement->fetchAll();
}

/* Mga kaklase sa block na wala pang registration sa napiling event */
$notRegistered = [];
if ($eventId > 0) {
    $pendingSql = 'SELECT s.studentID, s.studentFirstName, s.studentLastName,
                s.studentMiddleName, s.studentExtensionName,
                b.classBlock, e.eventID, e.eventTitle, e.eventDate
         FROM tblstudent s
         JOIN tblblock b ON b.blockID = s.blockID
         JOIN tblevent e ON e.eventID = ? AND e.archivedAt IS NULL
         LEFT JOIN tbleventregistration r ON r.studentID = s.studentID AND r.eventID = e.eventID
         WHERE s.blockID = ? AND r.registrationID IS NULL
           AND (e.courseID IS NULL OR e.courseID = b.courseID)';

    $pendingParams = [$eventId, $blockId];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $pendingSql .= " AND (s.studentFirstName LIKE ? OR s.studentLastName LIKE ?
                             OR CONCAT(s.studentLastName, ', ', s.studentFirstName) LIKE ?
                             OR CAST(s.studentID AS CHAR) LIKE ?)";
        $pendingParams[] = $like;
        $pendingParams[] = $like;
        $pendingParams[] = $like;
        $pendingParams[] = $like;
    }

    $pendingSql .= ' ORDER BY s.studentLastName, s.studentFirstName';

    $pendingStatement = $pdo->prepare($pendingSql);
    $pendingStatement->execute($pendingParams);
    $notRegistered = $pendingStatement->fetchAll();
}

$counts = attendance_status_counts($registrations);
$totalNotRegistered = count($notRegistered);

// Isang table lang ang nakikita, kaya isang pager lang ang kailangan
$pager = $view === 'unregistered'
    ? attendance_paginate($totalNotRegistered)
    : attendance_paginate(count($registrations));
$presentCount = $counts['present'];
$lateCount = $counts['late'];
$absentCount = $counts['absent'];

attendance_page_start('attendance');
?>

<div class="stack">
    <div class="highlightGrid">
        <section class="highlight green">
            <div class="eyebrow">Present</div>
            <strong><?= $presentCount ?> student<?= $presentCount === 1 ? '' : 's' ?></strong>
            <small>On time in your block</small>
        </section>
        <section class="highlight red">
            <div class="eyebrow">Late / Absent</div>
            <strong><?= $lateCount + $absentCount ?> student<?= $lateCount + $absentCount === 1 ? '' : 's' ?></strong>
            <small><?= $lateCount ?> late &middot; <?= $absentCount ?> absent<?= $counts['upcoming'] > 0 ? ' &middot; ' . $counts['upcoming'] . ' upcoming' : '' ?></small>
        </section>
        <section class="highlight light">
            <div>
                <div class="eyebrow">My block</div>
                <strong><?= h((string) ($studentRecord['classBlock'] ?? '-')) ?></strong>
                <small class="muted"><?= h((string) ($studentRecord['courseName'] ?? '')) ?></small>
            </div>
            <img src="<?= h($attendanceAssets) ?>/users.png" alt="" style="width:2.2rem;height:2.2rem;object-fit:contain;opacity:0.7;">
        </section>
    </div>

    <section class="card">
        <div class="cardHead">
            <img src="<?= h($attendanceAssets) ?>/clipboard-check.png" alt="">
            <h3>Event attendance</h3>
            <span class="spacer"></span>
            <?php if ($selectedEvent !== null): ?>
                <span class="pillLink"><?= h(attendance_long_date($selectedEvent['eventDate'])) ?> · <?= h(attendance_time($selectedEvent['eventStartTime'])) ?></span>
            <?php endif; ?>
            <form method="get" class="selectInline">
                <label for="eventSelect">Event</label>
                <select id="eventSelect" name="event" onchange="this.form.submit()">
                    <?php if (count($events) === 0): ?>
                        <option value="0">No events yet</option>
                    <?php endif; ?>
                    <?php foreach ($events as $event): ?>
                        <option value="<?= (int) $event['eventID'] ?>" <?= (int) $event['eventID'] === $eventId ? 'selected' : '' ?>>
                            <?= h($event['eventTitle']) ?> (<?= h(attendance_long_date($event['eventDate'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selectedEvent !== null): ?>
            <div class="tableNote">
                <p>
                    Administrator approval:
                    <strong><?= h($approvalStatus === '' ? 'Not requested' : $approvalStatus) ?></strong>
                    <?php if ($approvalStatus === 'Approved' && $eventStatus === 'Ongoing'): ?>
                        — attendance actions are now enabled and will appear in the students&rsquo; records.
                    <?php elseif ($approvalStatus === 'Approved'): ?>
                        — approval is recorded, but attendance actions are closed because the event is not in progress.
                    <?php elseif ($approvalStatus === 'Pending'): ?>
                        — waiting for an administrator to review this block&rsquo;s request.
                    <?php elseif ($eventStatus === 'Ongoing'): ?>
                        — request approval before recording attendance for this event.
                    <?php else: ?>
                        — requests are available while the event is in progress.
                    <?php endif; ?>
                </p>
                <?php if ($eventStatus === 'Ongoing' && !in_array($approvalStatus, ['Pending', 'Approved'], true)): ?>
                    <form method="post" class="btnRow">
                        <?= attendance_csrf_field() ?>
                        <input type="hidden" name="action" value="request_approval">
                        <input type="hidden" name="event_id" value="<?= $eventId ?>">
                        <button class="btn tiny" type="submit">Request admin approval</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="filterRow tight">
                <div class="filterPills">
                    <a class="<?= $view === 'registered' ? 'active' : '' ?>" href="<?= h($currentUrl . '?event=' . $eventId . '&q=' . urlencode($search) . '&view=registered') ?>">
                        Registered
                        <span class="count"><?= count($registrations) ?></span>
                    </a>
                    <a class="<?= $view === 'unregistered' ? 'active' : '' ?>" href="<?= h($currentUrl . '?event=' . $eventId . '&q=' . urlencode($search) . '&view=unregistered') ?>">
                        Not registered
                        <span class="count"><?= $totalNotRegistered ?></span>
                    </a>
                </div>

                <span class="spacer"></span>

                <form method="get" class="btnRow">
                    <input type="hidden" name="event" value="<?= $eventId ?>">
                    <input type="hidden" name="view" value="<?= h($view) ?>">
                    <input class="searchInline" type="search" name="q" value="<?= h($search) ?>" placeholder="Search student name or ID">
                    <button class="btn ghost tiny" type="submit">
                        <img src="<?= h($attendanceAssets) ?>/filter.png" alt="">
                        Apply
                    </button>
                    <?php if ($search !== ''): ?>
                        <a class="btn gray tiny" href="<?= h($currentUrl . '?event=' . $eventId . '&view=' . $view) ?>">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($selectedEvent === null): ?>
            <div class="emptyState">
                <img src="<?= h($attendanceAssets) ?>/calendar.png" alt="">
                <strong>No events yet</strong>
                <p>Event attendance can be marked once the administrator publishes an event.</p>
            </div>
        <?php elseif ($view === 'unregistered'): ?>
            <?php if ($totalNotRegistered === 0): ?>
                <div class="emptyState">
                    <img src="<?= h($attendanceAssets) ?>/clipboard-check.png" alt="">
                    <strong>Everyone in your block is registered</strong>
                    <p>No student from <?= h((string) ($studentRecord['classBlock'] ?? 'your block')) ?> is missing a registration for this event.</p>
                </div>
            <?php else: ?>
                <div class="tableNote">
                    <p>These students from your block have no registration yet, so there is no attendance to mark. Remind them to register first.</p>
                </div>
                <div class="tableWrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Event</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($notRegistered, $pager['offset'], $pager['perPage']) as $pending): ?>
                                <tr>
                                    <td class="strong">
                                        <?= h(attendance_student_name($pending)) ?>
                                        <span class="sub">No registration</span>
                                    </td>
                                    <td class="nowrap"><?= attendance_student_id($pending) ?></td>
                                    <td>
                                        <?= h((string) $pending['eventTitle']) ?>
                                        <span class="sub"><?= h(attendance_long_date((string) $pending['eventDate'])) ?></span>
                                    </td>
                                    <td><?= attendance_badge('Not registered') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?= attendance_pagination($currentUrl, ['event' => $eventId, 'q' => $search, 'view' => $view], $pager['page'], $pager['totalPages']) ?>
            <?php endif; ?>
        <?php elseif (count($registrations) === 0): ?>
            <div class="emptyState">
                <img src="<?= h($attendanceAssets) ?>/ticket.png" alt="">
                <strong>No registrations in your block</strong>
                <p>Students from <?= h((string) ($studentRecord['classBlock'] ?? 'your block')) ?> have not registered for this event yet. Open the Not registered tab to see them.</p>
            </div>
        <?php else: ?>
            <?php if (!$canMarkAttendance): ?>
                <div class="tableNote">
                    <p>
                        <?php if ($eventStatus !== 'Ongoing'): ?>
                            Attendance can only be recorded while the event is in progress.
                        <?php elseif ($approvalStatus === 'Pending'): ?>
                            Attendance actions unlock after an administrator approves this block&rsquo;s request.
                        <?php else: ?>
                            Request and receive administrator approval before recording attendance.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            <div class="tableWrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Registration</th>
                            <th>Time in</th>
                            <th>Time out</th>
                            <th>Status</th>
                            <th>Recorded by</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($registrations, $pager['offset'], $pager['perPage']) as $registration): ?>
                            <?php $attendanceStatus = attendance_display_status($registration); ?>
                            <tr>
                                <td class="strong">
                                    <?= h(attendance_student_name($registration)) ?>
                                    <span class="sub"><?= attendance_student_sub($registration) ?></span>
                                </td>
                                <td><?= attendance_badge((string) $registration['registrationStatus']) ?></td>
                                <td class="timeCell"><?= h(attendance_datetime($registration['timeIn'])) ?></td>
                                <td class="timeCell"><?= h(attendance_datetime($registration['timeOut'])) ?></td>
                                <td><?= attendance_badge($attendanceStatus) ?></td>
                                <td class="small"><?= h(attendance_marker_name($registration)) ?></td>
                                <td>
                                    <div class="btnRow">
                                        <form method="post" style="margin:0;">
                                            <?= attendance_csrf_field() ?>
                                            <input type="hidden" name="action" value="mark_in">
                                            <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                            <input type="hidden" name="registration_id" value="<?= (int) $registration['registrationID'] ?>">
                                            <button class="btn tiny" type="submit" <?= (!$canMarkAttendance || !empty($registration['timeIn'])) ? 'disabled' : '' ?>>Time in</button>
                                        </form>
                                        <?php if (!empty($registration['timeIn']) && empty($registration['timeOut'])): ?>
                                            <form method="post" style="margin:0;">
                                                <?= attendance_csrf_field() ?>
                                                <input type="hidden" name="action" value="undo_in">
                                                <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                                <input type="hidden" name="registration_id" value="<?= (int) $registration['registrationID'] ?>">
                                                <button class="btn ghost tiny" type="submit" onclick="return confirm('Undo this time in?');" <?= !$canMarkAttendance ? 'disabled' : '' ?>>Undo</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" style="margin:0;">
                                            <?= attendance_csrf_field() ?>
                                            <input type="hidden" name="action" value="mark_out">
                                            <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                            <input type="hidden" name="registration_id" value="<?= (int) $registration['registrationID'] ?>">
                                            <button class="btn ghost tiny" type="submit" <?= (!$canMarkAttendance || empty($registration['timeIn']) || !empty($registration['timeOut'])) ? 'disabled' : '' ?>>Time out</button>
                                        </form>
                                        <form method="post" style="margin:0;">
                                            <?= attendance_csrf_field() ?>
                                            <input type="hidden" name="action" value="mark_absent">
                                            <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                            <input type="hidden" name="registration_id" value="<?= (int) $registration['registrationID'] ?>">
                                            <button class="btn red tiny" type="submit" <?= (!$canMarkAttendance || !empty($registration['timeIn'])) ? 'disabled' : '' ?>>Absent</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= attendance_pagination($currentUrl, ['event' => $eventId, 'q' => $search, 'view' => $view], $pager['page'], $pager['totalPages']) ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="infoBox">
            <h4><img src="<?= h($attendanceAssets) ?>/info.png" alt=""> Reminders for block representatives</h4>
            <ul>
                <li>Time in and time out are saved with the exact current time. A wrong time in can be undone with the Undo button until a time out is recorded.</li>
                <li>Request administrator approval for this event before recording attendance; the approval and marker are shared with the admin and student records.</li>
                <li>A student who times in more than 2 hours after the event start time is automatically marked as late.</li>
                <li>A student who timed in but never timed out shows Time In Only once the event has ended.</li>
                <li>Time in and time out stay disabled until the event start time, and a student without a time in shows Upcoming until then.</li>
                <li>Mark a student absent only after the event has started and they have no time in yet.</li>
                <li>Cash payments from your block are approved on the Event Payment tab.</li>
                <li>The Not registered tab shows the students in your block who still have no registration for the selected event.</li>
            </ul>
        </div>
    </section>
</div>

<?php attendance_page_end(); ?>
