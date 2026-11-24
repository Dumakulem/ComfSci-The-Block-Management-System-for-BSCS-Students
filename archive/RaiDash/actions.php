<?php
// Handles every form POST (saves to the db_comfysci database), then redirects back to the dashboard.
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php';

$ym   = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_POST['m'] ?? '') ? $_POST['m'] : date('Y-m');
$back = 'index.php?m=' . $ym;
$go   = function (string $extra = '') use ($back) { header('Location: ' . $back . $extra); exit; };
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) { $go(); }

$text  = fn($v, int $max) => mb_substr(trim((string)$v), 0, $max);
$valid = function (string $fmt, $v): string {            // exact date/time check, '' when invalid
    $d = DateTime::createFromFormat('!' . $fmt, (string)$v);
    return ($d && $d->format($fmt) === (string)$v) ? (string)$v : '';
};
$pdo = db();
$sid = current_student_id();

try {
switch ($_POST['action'] ?? '') {
    case 'toggle_theme':
        setcookie('theme', ($_COOKIE['theme'] ?? 'light') === 'dark' ? 'light' : 'dark', ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
        break;

    case 'toggle_sidebar':   // only used on narrow screens without JavaScript
        setcookie('sidebar', ($_COOKIE['sidebar'] ?? 'open') === 'closed' ? 'open' : 'closed', ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
        break;

    case 'clear_notifications':
        // "Clear" only hides what is older than now for this student; the shared events/messages stay untouched.
        $pdo->prepare('INSERT INTO tblnotificationclear (studentID, clearedAt) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE clearedAt = NOW()')->execute([$sid]);
        flash('Notifications cleared.');
        $go('&panel=notif');   // keep the dropdown open

    case 'toggle_task':
        $pdo->prepare('UPDATE tbltask SET isDone = 1 - isDone WHERE taskID = ? AND studentID = ?')->execute([(int)($_POST['id'] ?? 0), $sid]);
        break;

    case 'add_task':
    case 'update_task':
        $title = $text($_POST['title'] ?? '', 60);
        $date  = $valid('Y-m-d', $_POST['date'] ?? '');
        $time  = $valid('H:i', $_POST['time'] ?? '');
        $id    = (int)($_POST['id'] ?? -1);
        $isNew = $_POST['action'] === 'add_task';
        if ($title === '' || $date === '' || $time === '') {
            flash('Enter a task name, deadline date and deadline time.');
            $go($isNew ? '&edit=addtask' : '&edit=tasks&pick=' . $id);
        }
        if ($isNew) {
            $pdo->prepare('INSERT INTO tbltask (studentID, taskTitle, taskDate, taskTime, isDone) VALUES (?, ?, ?, ?, 0)')
                ->execute([$sid, $title, $date, $time]);
            flash('Added to your to-do list.');
        } else {
            $pdo->prepare('UPDATE tbltask SET taskTitle = ?, taskDate = ?, taskTime = ?, isDone = ? WHERE taskID = ? AND studentID = ?')
                ->execute([$title, $date, $time, empty($_POST['done']) ? 0 : 1, $id, $sid]);
            flash('To-do updated.');
        }
        $go($isNew ? '' : '&edit=tasks');   // a new to-do returns to the dashboard; an edit returns to the list

    case 'delete_tasks':
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
        if (!$ids) { flash('Select at least one to-do to delete.'); $go('&edit=tasks'); }
        $st = $pdo->prepare('DELETE FROM tbltask WHERE studentID = ? AND taskID IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $st->execute(array_merge([$sid], $ids));
        flash(plural($st->rowCount(), 'to-do') . ' deleted.');
        $go('&edit=tasks');

    case 'add_schedule':
    case 'update_schedule':
        $isNew   = $_POST['action'] === 'add_schedule';
        $id      = (int)($_POST['id'] ?? -1);
        $subject = $text($_POST['subject'] ?? '', 60);
        $prof    = $text($_POST['professor'] ?? '', 60);
        $days    = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['days'] ?? [])), fn($d) => $d >= 0 && $d <= 6)));
        sort($days);
        $hhmm    = fn($v) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$v) ? (string)$v : '';
        $start   = $hhmm($_POST['start'] ?? '');
        $end     = $hhmm($_POST['end'] ?? '');
        $link    = $text($_POST['link'] ?? '', 300);
        if ($link !== '' && !preg_match('#^[a-z][a-z0-9+.\-]*://#i', $link)) { $link = 'https://' . $link; }   // "meet.google.com/abc" -> https://...
        $err = '';
        if ($subject === '' || $prof === '')      { $err = 'Enter the subject and the professor.'; }
        elseif (!$days)                           { $err = 'Pick at least one day.'; }
        elseif ($start === '' || $end === '')     { $err = 'Enter a start time and an end time.'; }
        elseif ($end <= $start)                   { $err = 'The end time must be after the start time.'; }
        elseif ($link !== '' && (!preg_match('#^https?://[^\s/]+#i', $link) || !filter_var($link, FILTER_VALIDATE_URL))) { $err = 'The meeting link must be a valid http(s) link.'; }
        if ($err) { flash($err); $go($isNew ? '&edit=addschedule' : '&edit=schedules&pick=' . $id); }
        if (!$isNew) {   // the class must exist and belong to this student
            $own = $pdo->prepare('SELECT COUNT(*) FROM tblclassschedule WHERE scheduleID = ? AND studentID = ?');
            $own->execute([$id, $sid]);
            if (!$own->fetchColumn()) { $go('&edit=schedules'); }
        }
        $pdo->beginTransaction();
        if ($isNew) {
            $pdo->prepare('INSERT INTO tblclassschedule (studentID, subject, professorName, startTime, endTime, meetingLink) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$sid, $subject, $prof, $start, $end, $link]);
            $id = (int)$pdo->lastInsertId();
            flash('Class schedule added.');
        } else {
            $pdo->prepare('UPDATE tblclassschedule SET subject = ?, professorName = ?, startTime = ?, endTime = ?, meetingLink = ? WHERE scheduleID = ? AND studentID = ?')
                ->execute([$subject, $prof, $start, $end, $link, $id, $sid]);
            $pdo->prepare('DELETE FROM tblclassscheduleday WHERE scheduleID = ?')->execute([$id]);
            flash('Class schedule updated.');
        }
        $ins = $pdo->prepare('INSERT INTO tblclassscheduleday (scheduleID, dayOfWeek) VALUES (?, ?)');
        foreach ($days as $d) { $ins->execute([$id, $d]); }
        $pdo->commit();
        $go($isNew ? '' : '&edit=schedules');   // a new class returns to the dashboard; an edit returns to the list

    case 'delete_schedules':
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($n) => $n > 0));
        if (!$ids) { flash('Select at least one class to delete.'); $go('&edit=schedules'); }
        $st = $pdo->prepare('DELETE FROM tblclassschedule WHERE studentID = ? AND scheduleID IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $st->execute(array_merge([$sid], $ids));   // tblclassscheduleday rows are removed automatically (ON DELETE CASCADE)
        flash(plural($st->rowCount(), 'class schedule') . ' deleted.');
        $go('&edit=schedules');

    case 'update_assignment':   // only the student's own priority; title/deadline belong to the Assignments module
        $i   = (int)($_POST['id'] ?? -1);
        $lvl = ['High priority' => 'High', 'Medium priority' => 'Medium', 'Low priority' => 'Low'][$_POST['priority'] ?? ''] ?? null;
        $own = $pdo->prepare('SELECT COUNT(*) FROM tblassignment WHERE assignmentID = ? AND blockID = (SELECT blockID FROM tblstudent WHERE studentID = ?)');
        $own->execute([$i, $sid]);
        if (!$lvl || !$own->fetchColumn()) { break; }
        $pdo->prepare('INSERT INTO tblassignmentpriority (studentID, assignmentID, priority) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE priority = VALUES(priority)')
            ->execute([$sid, $i, $lvl]);
        flash('Priority updated.');
        break;
}
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('Dashboard DB error: ' . $e->getMessage());
    flash('Something went wrong while saving. Please try again.');
}
$go();
