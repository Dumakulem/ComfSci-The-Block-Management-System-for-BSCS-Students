<?php
// Connection to the shared class database (db_comfysci) + everything the dashboard shows.
// Shared tables (owned by teammates, read-only here): tblstudent, tblblock, tblassignment, tblevent, tblmessage.
// Dashboard tables (owned by this module): tbltask, tblclassschedule, tblclassscheduleday,
//                                          tblassignmentpriority, tblnotificationclear  (see sql/dashboard_tables.sql)
// One shared connection for the whole project: db() comes from the team's database.php (ComfySci),
// so the dashboard and every other module talk to db_comfysci through the same function.
// If you move that file, change the path below.
require_once __DIR__ . '/../database.php';

const DEV_STUDENT_ID = 1;        // used only until the login module sets $_SESSION['studentID']

/** The logged-in student. The login module must store the student's id in $_SESSION['studentID']. */
function current_student_id(): int {
    // TODO when login is merged: if it is not set, redirect to the login page instead of using the dev id.
    return (int)($_SESSION['studentID'] ?? DEV_STUDENT_ID);
}

/**
 * Loads the dashboard data. Tasks, assignments and schedules are keyed by their database id, so the
 * views keep using ?pick=ID, ?del=ID and the hidden "id" fields.
 */
function load_dashboard(): array {
    $pdo = db();
    $sid = current_student_id();
    $q = function (string $sql, array $args = []) use ($pdo): PDOStatement { $st = $pdo->prepare($sql); $st->execute($args); return $st; };

    // ---- who is logged in (name shows in the sidebar / welcome banner, "Student Number" = studentID)
    $me = $q('SELECT studentFirstName AS name, studentID AS number, blockID FROM tblstudent WHERE studentID = ?', [$sid])->fetch();
    if (!$me) { http_response_code(500); exit('Student #' . $sid . ' was not found in tblstudent. Log in first, or run sql/dashboard_sample_data.sql.'); }
    $student = ['name' => $me['name'], 'number' => $me['number']];
    $block   = (int)$me['blockID'];

    // ---- notifications: upcoming events + messages in my block, newer than the last "Clear"
    $cleared = $q('SELECT clearedAt FROM tblnotificationclear WHERE studentID = ?', [$sid])->fetchColumn() ?: '2000-01-01 00:00:00';
    $notifications = [];
    foreach ($q("SELECT eventTitle, eventDate, eventStartTime, eventEndTime FROM tblevent
                 WHERE eventStatus = 'Upcoming' AND eventDate >= CURDATE() AND createdAt > ?
                 ORDER BY eventDate, eventStartTime LIMIT 5", [$cleared])->fetchAll() as $ev) {
        $notifications[] = ['kind' => 'event', 'title' => 'Upcoming event', 'subtitle' => $ev['eventTitle'],
                            'date' => date('D, j M', strtotime($ev['eventDate'])),
                            'time' => fmt_time($ev['eventStartTime']) . ($ev['eventEndTime'] ? ' - ' . fmt_time($ev['eventEndTime']) : '')];
    }
    foreach ($q("SELECT m.messageContent, m.createdAt, s.studentFirstName
                 FROM tblmessage m JOIN tblstudent s ON s.studentID = m.studentID
                 WHERE m.blockID = ? AND m.studentID <> ? AND m.messageType IN ('message', 'note') AND m.createdAt > ?
                 ORDER BY m.createdAt DESC LIMIT 5", [$block, $sid, $cleared])->fetchAll() as $m) {
        $notifications[] = ['kind' => 'message', 'title' => 'Message | ' . $m['studentFirstName'],
                            'subtitle' => mb_strimwidth($m['messageContent'], 0, 60, '…'), 'date' => '', 'time' => time_ago($m['createdAt'])];
    }

    // ---- announcements: posted for my block (or for everyone), newest first.
    // Table / column names below are assumed (tblannouncement) - change them to match the Announcement module's table.
    // If the query fails, the card just shows "No announcements yet" and the reason goes to the PHP error log.
    $announcements = [];
    try {
        foreach ($q('SELECT announcementID, announcementTitle, announcementContent, createdAt
                     FROM tblannouncement
                     WHERE (blockID = ? OR blockID IS NULL)
                     ORDER BY createdAt DESC, announcementID DESC LIMIT 10', [$block])->fetchAll() as $an) {
            $announcements[] = ['title'    => $an['announcementTitle'],
                                'subtitle' => mb_strimwidth(trim((string)$an['announcementContent']), 0, 90, '…'),
                                'date'     => date('D, j M', strtotime($an['createdAt'])),
                                'time'     => fmt_time(date('H:i', strtotime($an['createdAt'])))];
        }
    } catch (PDOException $e) {
        error_log('Announcements query failed: ' . $e->getMessage());
    }

    // ---- assignments posted for my block (not past the deadline). Priority is mine: saved, or guessed from the deadline.
    $assignments = $q("SELECT a.assignmentID, a.assignmentTitle AS title, a.assignmentSubject AS subject,
                              DATE_FORMAT(a.assignmentDeadline, '%Y-%m-%dT%H:%i') AS due, p.priority AS savedPriority
                       FROM tblassignment a
                       LEFT JOIN tblassignmentpriority p ON p.assignmentID = a.assignmentID AND p.studentID = ?
                       WHERE a.blockID = ? AND a.assignmentDeadline >= NOW()
                       ORDER BY a.assignmentDeadline, a.assignmentID", [$sid, $block])
                   ->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    foreach ($assignments as &$a) {
        $days = (strtotime($a['due']) - time()) / 86400;
        $a['priority'] = ($a['savedPriority'] ?: ($days <= 3 ? 'High' : ($days <= 7 ? 'Medium' : 'Low'))) . ' priority';
        unset($a['savedPriority']);
    }
    unset($a);

    // ---- my to-dos
    $tasks = $q("SELECT taskID, taskTitle AS title, DATE_FORMAT(taskDate, '%Y-%m-%d') AS `date`, TIME_FORMAT(taskTime, '%H:%i') AS `time`, isDone AS done
                 FROM tbltask WHERE studentID = ? ORDER BY taskDate, taskTime, taskID", [$sid])
             ->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    foreach ($tasks as &$t) { $t['done'] = (bool)$t['done']; $t['when'] = ''; }
    unset($t);

    // ---- my online class schedules (weekly)
    $schedules = $q("SELECT s.scheduleID, s.subject, s.professorName AS professor, s.meetingLink AS link,
                            TIME_FORMAT(s.startTime, '%H:%i') AS `start`, TIME_FORMAT(s.endTime, '%H:%i') AS `end`,
                            COALESCE(GROUP_CONCAT(d.dayOfWeek ORDER BY d.dayOfWeek), '') AS days
                     FROM tblclassschedule s LEFT JOIN tblclassscheduleday d ON d.scheduleID = s.scheduleID
                     WHERE s.studentID = ? GROUP BY s.scheduleID", [$sid])
                 ->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    foreach ($schedules as &$s) { $s['days'] = $s['days'] === '' ? [] : array_map('intval', explode(',', $s['days'])); }
    unset($s);
    $schedules = sort_schedules($schedules);

    return compact('student', 'notifications', 'announcements', 'assignments', 'tasks', 'schedules');
}
