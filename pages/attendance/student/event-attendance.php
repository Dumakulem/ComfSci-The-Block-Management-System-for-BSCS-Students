<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/attendance-base.php';

attendance_guard('student');

$studentId = $viewerId;

$recordsStatement = $pdo->prepare(
    'SELECT e.*, r.registrationID, r.registrationStatus,
            p.paymentStatus,
            a.attendanceStatus, a.timeIn, a.timeOut, a.markedAt,
            markerAdmin.adminFirstName AS markerAdminFirstName,
            markerAdmin.adminLastName AS markerAdminLastName,
            markerStudent.studentFirstName AS markerStudentFirstName,
            markerStudent.studentLastName AS markerStudentLastName
     FROM tbleventregistration r
     JOIN tblevent e ON e.eventID = r.eventID
     LEFT JOIN tbleventpayment p ON p.registrationID = r.registrationID
     LEFT JOIN tbleventattendance a ON a.registrationID = r.registrationID
     LEFT JOIN tbladmin markerAdmin ON markerAdmin.adminID = a.markedByAdminID
     LEFT JOIN tblstudent markerStudent ON markerStudent.studentID = a.markedByStudentID
     WHERE r.studentID = ? AND e.archivedAt IS NULL
     ORDER BY e.eventDate DESC, e.eventStartTime DESC'
);
$recordsStatement->execute([$studentId]);
$records = $recordsStatement->fetchAll();

$total = count($records);
$counts = attendance_status_counts($records);
$attended = $counts['present'] + $counts['late'];
$absent = $counts['absent'];
$percentage = $total > 0 ? (int) round(($attended / $total) * 100) : 0;

$nextEvent = null;
$previousEvent = null;
$timeLogs = [];

foreach ($records as $record) {
    $status = attendance_event_status($record);

    if ($nextEvent === null && in_array($status, ['Upcoming', 'Ongoing'], true)) {
        $nextEvent = $record;
    }

    if ($previousEvent === null && in_array($status, ['Ended', 'Completed'], true)) {
        $previousEvent = $record;
    }

    if (!empty($record['timeIn']) || !empty($record['timeOut'])) {
        $timeLogs[] = $record;
    }
}

$timeLogs = array_slice($timeLogs, 0, 4);

attendance_page_start('attendance');
?>

<div class="stack">
    <div class="highlightGrid">
        <section class="highlight green">
            <div class="eyebrow">Next event</div>
            <?php if ($nextEvent !== null): ?>
                <strong><?= h($nextEvent['eventTitle']) ?></strong>
                <small><?= h(attendance_long_date($nextEvent['eventDate'])) ?> &middot; <?= h(attendance_time($nextEvent['eventStartTime'])) ?></small>
            <?php else: ?>
                <strong>No upcoming event</strong>
                <small>Register for an event to see it here.</small>
            <?php endif; ?>
        </section>

        <section class="highlight red">
            <div class="eyebrow">Previous event</div>
            <?php if ($previousEvent !== null): ?>
                <strong><?= h($previousEvent['eventTitle']) ?></strong>
                <small><?= h(attendance_long_date($previousEvent['eventDate'])) ?></small>
            <?php else: ?>
                <strong>No previous event</strong>
                <small>Your past events will be listed here.</small>
            <?php endif; ?>
        </section>

        <section class="highlight light">
            <div>
                <div class="eyebrow">My attendance</div>
                <ul class="statList">
                    <li><?= $total ?> total event<?= $total === 1 ? '' : 's' ?></li>
                    <li><?= $attended ?> total attended</li>
                    <li><?= $absent ?> not attended</li>
                    <?php if ($counts['upcoming'] > 0): ?>
                        <li><?= $counts['upcoming'] ?> upcoming</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="statRing"><?= $percentage ?>%</div>
        </section>
    </div>

    <div class="layout">
        <section class="card">
            <div class="cardHead">
                <img src="<?= h($attendanceAssets) ?>/clipboard-check.png" alt="">
                <h3>Event attendance</h3>
                <span class="spacer"></span>
                <span class="pillLink">My records</span>
            </div>

            <?php if ($total === 0): ?>
                <div class="emptyState">
                    <img src="<?= h($attendanceAssets) ?>/ticket.png" alt="">
                    <strong>No event registrations yet</strong>
                    <p>Register for a campus event and your attendance record will appear here.</p>
                </div>
            <?php else: ?>
                <div class="tableWrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Time in</th>
                                <th>Time out</th>
                                <th>Recorded by</th>
                                <th>Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <?php $attendanceStatus = attendance_display_status($record); ?>
                                <tr>
                                    <td class="strong">
                                        <?= h($record['eventTitle']) ?>
                                        <span class="sub"><?= h($record['eventLocation']) ?></span>
                                    </td>
                                    <td class="nowrap"><?= h(attendance_long_date($record['eventDate'])) ?></td>
                                    <td><?= attendance_badge($attendanceStatus) ?></td>
                                    <td class="timeCell"><?= h(attendance_datetime($record['timeIn'])) ?></td>
                                    <td class="timeCell"><?= h(attendance_datetime($record['timeOut'])) ?></td>
                                    <td class="small"><?= h(attendance_marker_name($record)) ?></td>
                                    <td class="small muted">
                                        <?php if (strtolower($attendanceStatus) === 'late'): ?>
                                            Timed in after the event started.
                                        <?php elseif (strtolower($attendanceStatus) === 'time in only'): ?>
                                            Timed in, but no time out was recorded.
                                        <?php elseif (strtolower($attendanceStatus) === 'present'): ?>
                                            On time.
                                        <?php elseif (strtolower($attendanceStatus) === 'upcoming'): ?>
                                            Event has not started yet.
                                        <?php else: ?>
                                            No attendance recorded.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <div class="stack">
            <section class="card">
                <div class="cardHead">
                    <img src="<?= h($attendanceAssets) ?>/clock.png" alt="">
                    <h3>Time in / out</h3>
                </div>

                <?php if (count($timeLogs) === 0): ?>
                    <div class="infoBox">
                        <p>Your check-in and check-out times appear here once an event is held.</p>
                    </div>
                <?php else: ?>
                    <div class="tableWrap">
                        <table class="table">
                            <tbody>
                                <?php foreach ($timeLogs as $log): ?>
                                    <tr>
                                        <td>
                                            <span class="strong"><?= h($log['eventTitle']) ?></span>
                                            <span class="sub"><?= h(attendance_long_date($log['eventDate'])) ?></span>
                                        </td>
                                        <td class="small nowrap">
                                            <span class="statusDot green"></span>In: <?= h(attendance_time($log['timeIn'])) ?><br>
                                            <span class="statusDot"></span>Out: <?= h($log['timeOut'] ? attendance_time($log['timeOut']) : 'Not yet') ?>
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
                    <h4><img src="<?= h($attendanceAssets) ?>/info.png" alt=""> Good to know</h4>
                    <ul>
                        <li>Your attendance is marked by the administrator or your block representative.</li>
                        <li>A record shows as present once your time in is saved.</li>
                        <li>Time in and time out use the exact time they were recorded and cannot be edited.</li>
                        <li>If you timed in but never timed out, the record shows Time In Only once the event has ended.</li>
                        <li>Attendance is stored per event registration.</li>
                    </ul>
                </div>
            </section>
        </div>
    </div>
</div>

<?php attendance_page_end(); ?>
