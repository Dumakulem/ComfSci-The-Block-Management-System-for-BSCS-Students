<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/attendance-base.php';

attendance_guard('admin');

$currentUrl = $attendanceBase . '/admin/event-attendance.php';

$eventId = (int) ($_GET['event'] ?? 0);
$courseId = (int) ($_GET['course'] ?? 0);
// block name na lang ang hinohold natin (A, B, C, D) hindi blockID,
// kasi pag All courses dapat pagsamahin lahat ng block A sa isang filter
$blockName = trim((string) ($_GET['block'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
// registered = mga nakapag-register, unregistered = mga wala pang register
$view = (string) ($_GET['view'] ?? 'registered');
if (!in_array($view, ['registered', 'unregistered'], true)) {
    $view = 'registered';
}

$filterQuery = '?event=' . $eventId . '&course=' . $courseId
    . '&block=' . urlencode($blockName) . '&q=' . urlencode($search)
    . '&view=' . $view;

/* ------------------------------------------------------------------
   Pag-mark ng attendance. Final na ang naisave na oras,
   hindi na pwedeng i-edit.
------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    attendance_check_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $registrationId = (int) ($_POST['registration_id'] ?? 0);
    $recordEventId = (int) ($_POST['event_id'] ?? 0);

    try {
        $check = $pdo->prepare(
            'SELECT r.registrationID, e.eventDate, e.eventStartTime, e.eventEndTime, e.eventStatus,
                    a.attendanceID, a.timeIn, a.timeOut
             FROM tbleventregistration r
             JOIN tblevent e ON e.eventID = r.eventID
             LEFT JOIN tbleventattendance a ON a.registrationID = r.registrationID
             WHERE r.registrationID = ? AND r.eventID = ?'
        );
        $check->execute([$registrationId, $recordEventId]);
        $record = $check->fetch();

        if ($record === false) {
            attendance_flash('error', 'That registration could not be found.');
        } elseif (in_array($action, ['mark_in', 'mark_out', 'mark_absent'], true) && !attendance_event_started($record)) {
            attendance_flash(
                'error',
                'Attendance opens at the exact event start time ('
                    . attendance_datetime($record['eventDate'] . ' ' . $record['eventStartTime']) . ').'
            );
        } else {
            [$flashType, $message] = attendance_mark($record, $action, $viewerId);
            attendance_flash($flashType, $message);
        }
    } catch (PDOException $exception) {
        attendance_flash('error', 'The attendance record could not be saved. Please try again.');
    }

    attendance_redirect($currentUrl . $filterQuery . '&page=' . max(1, (int) ($_GET['page'] ?? 1)));
}

/* Filters ng event, course, block at search */
$events = $pdo->query(
    'SELECT eventID, eventTitle, eventDate
     FROM tblevent WHERE archivedAt IS NULL
     ORDER BY eventDate DESC, eventStartTime DESC'
)->fetchAll();

$courses = $pdo->query('SELECT courseID, courseName FROM tblcourse ORDER BY courseName')->fetchAll();

/* Blocks sa dropdown:
   - may napiling course -> blocks lang ng course na yun
   - All courses -> isa lang bawat name (A, B, C, D) para hindi
     dumoble yung A ng bawat course sa list */
$blockSql = 'SELECT DISTINCT b.classBlock FROM tblblock b';
$blockParams = [];
if ($courseId > 0) {
    $blockSql .= ' WHERE b.courseID = ?';
    $blockParams[] = $courseId;
}
$blockSql .= ' ORDER BY b.classBlock';
$blockStatement = $pdo->prepare($blockSql);
$blockStatement->execute($blockParams);
$blocks = $blockStatement->fetchAll();

// kung nawala yung dating napiling block (nagpalit ng course),
// balik sa All blocks para walang blangkong table
if ($blockName !== '' && !in_array($blockName, array_column($blocks, 'classBlock'), true)) {
    $blockName = '';
}

/* ------------------------------------------------------------------
   Registrations na pasok sa napiling event, course at block
------------------------------------------------------------------ */
$sql = 'SELECT r.registrationID, r.registrationStatus, r.eventID,
               e.eventTitle, e.eventDate, e.eventStartTime, e.eventEndTime, e.eventStatus,
               s.studentID, s.studentFirstName, s.studentLastName, s.studentMiddleName, s.studentExtensionName,
               b.classBlock, b.courseID, c.courseName,
               a.attendanceStatus, a.timeIn, a.timeOut,
               markerAdmin.adminFirstName AS markerAdminFirstName,
               markerAdmin.adminLastName AS markerAdminLastName,
               markerStudent.studentFirstName AS markerStudentFirstName,
               markerStudent.studentLastName AS markerStudentLastName
        FROM tbleventregistration r
        JOIN tblevent e ON e.eventID = r.eventID
        JOIN tblstudent s ON s.studentID = r.studentID
        LEFT JOIN tblblock b ON b.blockID = s.blockID
        LEFT JOIN tblcourse c ON c.courseID = b.courseID
        LEFT JOIN tbleventattendance a ON a.registrationID = r.registrationID
        LEFT JOIN tbladmin markerAdmin ON markerAdmin.adminID = a.markedByAdminID
        LEFT JOIN tblstudent markerStudent ON markerStudent.studentID = a.markedByStudentID
        WHERE e.archivedAt IS NULL';

$params = [];

if ($eventId > 0) {
    $sql .= ' AND e.eventID = ?';
    $params[] = $eventId;
}

if ($courseId > 0) {
    $sql .= ' AND b.courseID = ?';
    $params[] = $courseId;
}

if ($blockName !== '') {
    // classBlock ang compare para pag All courses + A, sama-sama lahat ng A
    $sql .= ' AND b.classBlock = ?';
    $params[] = $blockName;
}

// search: pwede pangalan o student ID
if ($search !== '') {
    $like = '%' . $search . '%';
    $sql .= " AND (s.studentFirstName LIKE ? OR s.studentLastName LIKE ?
                   OR CONCAT(s.studentLastName, ', ', s.studentFirstName) LIKE ?
                   OR CAST(s.studentID AS CHAR) LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= ' ORDER BY e.eventDate DESC, e.eventStartTime DESC, b.classBlock, s.studentLastName, s.studentFirstName';

$listStatement = $pdo->prepare($sql);
$listStatement->execute($params);
$registrations = $listStatement->fetchAll();

/* ------------------------------------------------------------------
   Mga estudyanteng hindi pa nakapag-register sa napiling event
   Kailangan ng specific event para may pagbabasehan kung sino ang
   wala pang registration. Sinusunod din yung course ng event kaya
   hindi kasama yung ibang course kapag limited lang ito.
------------------------------------------------------------------ */
$notRegistered = [];
if ($eventId > 0) {
    $pendingSql = 'SELECT s.studentID, s.studentFirstName, s.studentLastName,
                          s.studentMiddleName, s.studentExtensionName,
                          b.classBlock, b.courseID, c.courseName,
                          e.eventID, e.eventTitle, e.eventDate
                   FROM tblstudent s
                   JOIN tblblock b ON b.blockID = s.blockID
                   JOIN tblcourse c ON c.courseID = b.courseID
                   JOIN tblevent e ON e.eventID = ? AND e.archivedAt IS NULL
                   LEFT JOIN tbleventregistration r ON r.studentID = s.studentID AND r.eventID = e.eventID
                   WHERE r.registrationID IS NULL
                     AND (e.courseID IS NULL OR e.courseID = b.courseID)';

    $pendingParams = [$eventId];

    if ($courseId > 0) {
        $pendingSql .= ' AND b.courseID = ?';
        $pendingParams[] = $courseId;
    }

    if ($blockName !== '') {
        $pendingSql .= ' AND b.classBlock = ?';
        $pendingParams[] = $blockName;
    }

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

    $pendingSql .= ' ORDER BY b.classBlock, s.studentLastName, s.studentFirstName';
    $pendingStatement = $pdo->prepare($pendingSql);
    $pendingStatement->execute($pendingParams);
    $notRegistered = $pendingStatement->fetchAll();
}

$counts = attendance_status_counts($registrations);
$totalRegistrations = count($registrations);
$totalNotRegistered = count($notRegistered);

// Isang table lang ang nakikita, kaya isang pager lang ang kailangan
$pager = $view === 'unregistered'
    ? attendance_paginate($totalNotRegistered)
    : attendance_paginate($totalRegistrations);

// links ng dalawang tab, dala yung current filters para hindi mawala
$registeredTabUrl = $currentUrl . '?event=' . $eventId . '&course=' . $courseId
    . '&block=' . urlencode($blockName) . '&q=' . urlencode($search) . '&view=registered';
$unregisteredTabUrl = $currentUrl . '?event=' . $eventId . '&course=' . $courseId
    . '&block=' . urlencode($blockName) . '&q=' . urlencode($search) . '&view=unregistered';

attendance_page_start('attendance');
?>

<div class="stack">
    <div class="highlightGrid">
        <section class="highlight green">
            <div class="eyebrow">Present</div>
            <strong><?= $counts['present'] ?> student<?= $counts['present'] === 1 ? '' : 's' ?></strong>
            <small><?= $counts['late'] ?> late arrival<?= $counts['late'] === 1 ? '' : 's' ?></small>
        </section>
        <section class="highlight red">
            <div class="eyebrow">Absent</div>
            <strong><?= $counts['absent'] ?> student<?= $counts['absent'] === 1 ? '' : 's' ?></strong>
            <small><?= $counts['upcoming'] ?> still upcoming</small>
        </section>
        <section class="highlight light">
            <div>
                <div class="eyebrow">All registrations</div>
                <strong><?= $totalRegistrations ?> record<?= $totalRegistrations === 1 ? '' : 's' ?></strong>
                <small class="muted">Matching the filters below</small>
            </div>
            <img src="<?= h($attendanceAssets) ?>/clipboard-check.png" alt="" style="width:2.2rem;height:2.2rem;object-fit:contain;opacity:0.7;">
        </section>
    </div>

    <section class="card">
        <div class="cardHead">
            <img src="<?= h($attendanceAssets) ?>/clipboard-check.png" alt="">
            <h3>Event attendance</h3>
            <span class="spacer"></span>
            <span class="pillLink"><?= $eventId > 0 ? 'One event' : 'All events' ?></span>
        </div>

        <div class="filterRow tight">
            <div class="filterPills">
                <a class="<?= $view === 'registered' ? 'active' : '' ?>" href="<?= h($registeredTabUrl) ?>">
                    Registered
                    <span class="count"><?= $totalRegistrations ?></span>
                </a>
                <a class="<?= $view === 'unregistered' ? 'active' : '' ?>" href="<?= h($unregisteredTabUrl) ?>">
                    Not registered
                    <span class="count"><?= $eventId > 0 ? $totalNotRegistered : '-' ?></span>
                </a>
            </div>
        </div>

        <div class="filterRow">
            <form method="get" class="btnRow">
                <input type="hidden" name="view" value="<?= h($view) ?>">
                <select class="selectInline" name="event" aria-label="Filter by event">
                    <option value="0">All events</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?= (int) $event['eventID'] ?>" <?= (int) $event['eventID'] === $eventId ? 'selected' : '' ?>>
                            <?= h($event['eventTitle']) ?> (<?= h(attendance_long_date($event['eventDate'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <select class="selectInline" name="course" aria-label="Filter by course" onchange="this.form.submit()">
                    <option value="0">All courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= (int) $course['courseID'] ?>" <?= (int) $course['courseID'] === $courseId ? 'selected' : '' ?>>
                            <?= h($course['courseName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select class="selectInline" name="block" aria-label="Filter by block" onchange="this.form.submit()">
                    <option value="">All blocks</option>
                    <?php foreach ($blocks as $block): ?>
                        <?php $blockOption = (string) $block['classBlock']; ?>
                        <option value="<?= h($blockOption) ?>" <?= $blockOption === $blockName ? 'selected' : '' ?>>
                            <?= h($blockOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input class="searchInline" type="search" name="q" value="<?= h($search) ?>" placeholder="Search student name or ID">

                <button class="btn ghost tiny" type="submit">
                    <img src="<?= h($attendanceAssets) ?>/filter.png" alt="">
                    Apply
                </button>

                <?php if ($eventId > 0 || $courseId > 0 || $blockName !== '' || $search !== ''): ?>
                    <a class="btn gray tiny" href="<?= h($currentUrl . '?view=' . $view) ?>">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($view === 'unregistered'): ?>
            <?php if ($eventId === 0): ?>
                <div class="emptyState">
                    <img src="<?= h($attendanceAssets) ?>/ticket.png" alt="">
                    <strong>Select one event first</strong>
                    <p>Pick a specific event above to see which students have not registered yet.</p>
                </div>
            <?php elseif ($totalNotRegistered === 0): ?>
                <div class="emptyState">
                    <img src="<?= h($attendanceAssets) ?>/clipboard-check.png" alt="">
                    <strong>Everyone is registered</strong>
                    <p>All students in this filter already have a registration for the selected event.</p>
                </div>
            <?php else: ?>
                <div class="tableNote">
                    <p>These students have no registration yet, so there is no attendance to mark. Once they register, they move to the Registered tab.</p>
                </div>
                <div class="tableWrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course / Block</th>
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
                                    <td>
                                        <?= h((string) ($pending['courseName'] ?? '-')) ?>
                                        <span class="sub"><?= h((string) ($pending['classBlock'] ?? '-')) ?></span>
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
                <?= attendance_pagination($currentUrl, ['event' => $eventId, 'course' => $courseId, 'block' => $blockName, 'q' => $search, 'view' => $view], $pager['page'], $pager['totalPages']) ?>
            <?php endif; ?>
        <?php elseif ($totalRegistrations === 0): ?>
            <div class="emptyState">
                <img src="<?= h($attendanceAssets) ?>/ticket.png" alt="">
                <strong>No registrations for this filter</strong>
                <p>No students match the selected event, course, block or name.</p>
            </div>
        <?php else: ?>
            <div class="tableWrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course / Block</th>
                            <th>Student ID</th>
                            <th>Event</th>
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
                            <?php $eventStarted = attendance_event_started($registration); ?>
                            <tr>
                                <td class="strong">
                                    <?= h(attendance_student_name($registration)) ?>
                                    <span class="sub"><?= h((string) ($registration['registrationStatus'] ?? '')) ?></span>
                                </td>
                                <td>
                                    <?= h((string) ($registration['courseName'] ?? '-')) ?>
                                    <span class="sub"><?= h((string) ($registration['classBlock'] ?? '-')) ?></span>
                                </td>
                                <td class="nowrap"><?= attendance_student_id($registration) ?></td>
                                <td>
                                    <?= h((string) $registration['eventTitle']) ?>
                                    <span class="sub"><?= h(attendance_long_date((string) $registration['eventDate'])) ?></span>
                                </td>
                                <td class="timeCell"><?= h(attendance_datetime($registration['timeIn'])) ?></td>
                                <td class="timeCell"><?= h(attendance_datetime($registration['timeOut'])) ?></td>
                                <td><?= attendance_badge($attendanceStatus) ?></td>
                                <td class="small"><?= h(attendance_marker_name($registration)) ?></td>
                                <td>
                                    <div class="btnRow">
                                        <form method="post" style="margin:0;">
                                            <?= attendance_csrf_field() ?>
                                            <input type="hidden" name="action" value="mark_in">
                                            <input type="hidden" name="event_id" value="<?= (int) $registration['eventID'] ?>">
                                            <input type="hidden" name="registration_id" value="<?= (int) $registration['registrationID'] ?>">
                                            <button class="btn tiny" type="submit" <?= (!$eventStarted || !empty($registration['timeIn'])) ? 'disabled' : '' ?>>Time in</button>
                                        </form>
                                        <?php if (!empty($registration['timeIn']) && empty($registration['timeOut'])): ?>
                                            <form method="post" style="margin:0;">
                                                <?= attendance_csrf_field() ?>
                                                <input type="hidden" name="action" value="undo_in">
                                                <input type="hidden" name="event_id" value="<?= (int) $registration['eventID'] ?>">
                                                <input type="hidden" name="registration_id" value="<?= (int) $registration['registrationID'] ?>">
                                                <button class="btn ghost tiny" type="submit" onclick="return confirm('Undo this time in?');">Undo</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" style="margin:0;">
                                            <?= attendance_csrf_field() ?>
                                            <input type="hidden" name="action" value="mark_out">
                                            <input type="hidden" name="event_id" value="<?= (int) $registration['eventID'] ?>">
                                            <input type="hidden" name="registration_id" value="<?= (int) $registration['registrationID'] ?>">
                                            <button class="btn ghost tiny" type="submit" <?= (!$eventStarted || empty($registration['timeIn']) || !empty($registration['timeOut'])) ? 'disabled' : '' ?>>Time out</button>
                                        </form>
                                        <form method="post" style="margin:0;">
                                            <?= attendance_csrf_field() ?>
                                            <input type="hidden" name="action" value="mark_absent">
                                            <input type="hidden" name="event_id" value="<?= (int) $registration['eventID'] ?>">
                                            <input type="hidden" name="registration_id" value="<?= (int) $registration['registrationID'] ?>">
                                            <button class="btn red tiny" type="submit" <?= (!$eventStarted || !empty($registration['timeIn'])) ? 'disabled' : '' ?>>Absent</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= attendance_pagination($currentUrl, ['event' => $eventId, 'course' => $courseId, 'block' => $blockName, 'q' => $search, 'view' => $view], $pager['page'], $pager['totalPages']) ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="infoBox">
            <h4><img src="<?= h($attendanceAssets) ?>/info.png" alt=""> Attendance rules</h4>
            <ul>
                <li>Attendance is listed per block. Use the course and block filters to see one class at a time.</li>
                <li>Pick a course first and the block list shows only that course&rsquo;s blocks. With All courses, choosing block A combines the A blocks of every course.</li>
                <li>The search box accepts a student name or a student ID.</li>
                <li>The Not registered tab lists the students who have no registration yet. Pick one event first so we know which event to check.</li>
                <li>All events shows every active event, with the present and absent totals for the current filter.</li>
                <li>Time in and time out always use the exact current server time. A wrong time in can be undone with the Undo button until a time out is recorded.</li>
                <li>Students who time in more than 2 hours after the event start time are automatically marked as late.</li>
                <li>A student who timed in but never timed out shows Time In Only once the event has ended.</li>
                <li>A student without a time in shows Upcoming until the event start time, then Absent.</li>
                <li>Block representatives are assigned on the Block Reps tab, one per block.</li>
            </ul>
        </div>
    </section>
</div>

<?php attendance_page_end(); ?>
