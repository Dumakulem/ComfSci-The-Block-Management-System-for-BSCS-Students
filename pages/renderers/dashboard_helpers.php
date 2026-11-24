<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

function dashboard_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function dashboard_icon(string $name, int $size = 18, string $class = ''): string
{
    if (!preg_match('/^[a-z-]+$/', $name)) {
        throw new InvalidArgumentException('Invalid dashboard icon name.');
    }

    $className = $class === '' ? 'ic' : 'ic ' . dashboard_escape($class);
    $url = './assets/rai-icons/' . $name . '.png';

    return '<img class="' . $className . '" src="' . dashboard_escape($url)
        . '" alt="" aria-hidden="true" width="' . $size . '" height="' . $size . '">';
}

function dashboard_csrf_token(): string
{
    if (!isset($_SESSION['dashboard_csrf'])) {
        $_SESSION['dashboard_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['dashboard_csrf'];
}

function dashboard_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . dashboard_escape(dashboard_csrf_token()) . '">';
}

function dashboard_time_ago(string $timestamp): string
{
    $seconds = max(0, time() - strtotime($timestamp));
    if ($seconds < 60) {
        return 'Just now';
    }
    if ($seconds < 3600) {
        return intdiv($seconds, 60) . 'm ago';
    }
    if ($seconds < 86400) {
        return intdiv($seconds, 3600) . 'h ago';
    }
    if ($seconds < 604800) {
        return intdiv($seconds, 86400) . 'd ago';
    }

    return date('M j', strtotime($timestamp));
}

function dashboard_format_time(string $time): string
{
    return $time === '' ? '' : date('g:i A', strtotime($time));
}

function dashboard_load_student(int $studentId): array
{
    $pdo = db();
    $statement = $pdo->prepare(
        'SELECT studentID, studentFirstName, studentLastName, blockID
         FROM tblstudent WHERE studentID = ?'
    );
    $statement->execute([$studentId]);
    $record = $statement->fetch();
    if (!$record) {
        throw new RuntimeException('The authenticated student was not found in tblstudent.');
    }

    $student = [
        'id' => (int) $record['studentID'],
        'name' => trim((string) $record['studentFirstName'] . ' ' . (string) $record['studentLastName']),
        'number' => (int) $record['studentID'],
        'blockID' => (int) $record['blockID'],
    ];

    $clearedStatement = $pdo->prepare('SELECT clearedAt FROM tblnotificationclear WHERE studentID = ?');
    $clearedStatement->execute([$studentId]);
    $clearedAt = (string) ($clearedStatement->fetchColumn() ?: '2000-01-01 00:00:00');

    $events = $pdo->prepare(
        "SELECT eventTitle, eventDate, eventStartTime, eventEndTime
         FROM tblevent
         WHERE eventStatus = 'Upcoming' AND eventDate >= CURDATE() AND createdAt > ?
         ORDER BY eventDate, eventStartTime LIMIT 5"
    );
    $events->execute([$clearedAt]);
    $notifications = [];
    foreach ($events->fetchAll() as $event) {
        $time = dashboard_format_time((string) $event['eventStartTime']);
        if (!empty($event['eventEndTime'])) {
            $time .= ' - ' . dashboard_format_time((string) $event['eventEndTime']);
        }
        $notifications[] = [
            'kind' => 'event',
            'title' => 'Upcoming event',
            'subtitle' => (string) $event['eventTitle'],
            'date' => date('D, M j', strtotime((string) $event['eventDate'])),
            'time' => $time,
        ];
    }

    $messages = $pdo->prepare(
        "SELECT m.messageContent, m.createdAt, s.studentFirstName
         FROM tblmessage m
         JOIN tblstudent s ON s.studentID = m.studentID
         WHERE m.blockID = ? AND m.studentID <> ?
           AND m.messageType IN ('message', 'note') AND m.createdAt > ?
         ORDER BY m.createdAt DESC LIMIT 5"
    );
    $messages->execute([$student['blockID'], $studentId, $clearedAt]);
    foreach ($messages->fetchAll() as $message) {
        $notifications[] = [
            'kind' => 'message',
            'title' => 'Message | ' . (string) $message['studentFirstName'],
            'subtitle' => mb_strimwidth((string) $message['messageContent'], 0, 90, '…'),
            'time' => dashboard_time_ago((string) $message['createdAt']),
        ];
    }

    $calendarEventStatement = $pdo->query(
        "SELECT eventTitle AS title, eventDate AS date
         FROM tblevent
         WHERE eventStatus = 'Upcoming' AND eventDate >= CURDATE()"
    );
    $calendarEvents = $calendarEventStatement->fetchAll();

    $assignmentStatement = $pdo->prepare(
        "SELECT a.assignmentID, a.assignmentTitle AS title, a.assignmentSubject AS subject,
                a.assignmentDeadline AS due, p.priority AS savedPriority
         FROM tblassignment a
         LEFT JOIN tblassignmentpriority p
           ON p.assignmentID = a.assignmentID AND p.studentID = ?
         WHERE a.blockID = ? AND a.assignmentDeadline >= NOW()
         ORDER BY a.assignmentDeadline, a.assignmentID"
    );
    $assignmentStatement->execute([$studentId, $student['blockID']]);
    $assignments = $assignmentStatement->fetchAll();
    foreach ($assignments as &$assignment) {
        $daysLeft = (strtotime((string) $assignment['due']) - time()) / 86400;
        $priority = (string) ($assignment['savedPriority'] ?: (
            $daysLeft <= 3 ? 'High' : ($daysLeft <= 7 ? 'Medium' : 'Low')
        ));
        $assignment['priority'] = $priority . ' priority';
        $assignment['assignmentID'] = (int) $assignment['assignmentID'];
    }
    unset($assignment);

    $taskStatement = $pdo->prepare(
        'SELECT taskID, taskTitle AS title, taskDate AS date,
                TIME_FORMAT(taskTime, "%H:%i") AS time, isDone AS done
         FROM tbltask WHERE studentID = ? ORDER BY isDone, taskDate, taskTime, taskID'
    );
    $taskStatement->execute([$studentId]);
    $tasks = $taskStatement->fetchAll();
    foreach ($tasks as &$task) {
        $task['taskID'] = (int) $task['taskID'];
        $task['done'] = (bool) $task['done'];
    }
    unset($task);

    $scheduleStatement = $pdo->prepare(
        "SELECT s.scheduleID, s.subject, s.professorName AS professor, s.meetingLink AS link,
                TIME_FORMAT(s.startTime, '%H:%i') AS start,
                TIME_FORMAT(s.endTime, '%H:%i') AS end,
                COALESCE(GROUP_CONCAT(d.dayOfWeek ORDER BY d.dayOfWeek), '') AS days
         FROM tblclassschedule s
         LEFT JOIN tblclassscheduleday d ON d.scheduleID = s.scheduleID
         WHERE s.studentID = ? GROUP BY s.scheduleID"
    );
    $scheduleStatement->execute([$studentId]);
    $schedules = $scheduleStatement->fetchAll();
    foreach ($schedules as &$schedule) {
        $schedule['scheduleID'] = (int) $schedule['scheduleID'];
        $schedule['days'] = $schedule['days'] === ''
            ? []
            : array_map('intval', explode(',', (string) $schedule['days']));
    }
    unset($schedule);

    return compact('student', 'notifications', 'calendarEvents', 'assignments', 'tasks', 'schedules');
}

function dashboard_expand_schedules(array $schedules, string $from, string $to): array
{
    $meetings = [];
    $date = new DateTimeImmutable($from);
    $lastDate = new DateTimeImmutable($to);

    for (; $date <= $lastDate; $date = $date->modify('+1 day')) {
        $weekday = (int) $date->format('w');
        foreach ($schedules as $schedule) {
            if (in_array($weekday, $schedule['days'], true)) {
                $meetings[] = [
                    'date' => $date->format('Y-m-d'),
                    'subject' => (string) $schedule['subject'],
                    'professor' => (string) $schedule['professor'],
                    'time' => (string) $schedule['start'],
                    'end' => (string) $schedule['end'],
                    'link' => (string) $schedule['link'],
                ];
            }
        }
    }

    usort($meetings, static function (array $left, array $right): int {
        return strcmp($left['date'] . $left['time'], $right['date'] . $right['time']);
    });

    return $meetings;
}

function dashboard_load_admin(): array
{
    $pdo = db();
    $counts = [];
    foreach ([
        'students' => 'tblstudent',
        'blocks' => 'tblblock',
        'assignments' => 'tblassignment',
        'events' => 'tblevent',
    ] as $key => $table) {
        $counts[$key] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    $announcements = $pdo->query(
        'SELECT announcementTitle AS title, announcementContent AS content, createdAt
         FROM tblannouncement ORDER BY createdAt DESC, announcementID DESC LIMIT 5'
    )->fetchAll();

    return compact('counts', 'announcements');
}

function dashboard_calendar(int $year, int $month): array
{
    $firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    $days = array_fill(0, (int) $firstDay->format('w'), null);
    for ($day = 1, $lastDay = (int) $firstDay->format('t'); $day <= $lastDay; $day++) {
        $days[] = $day;
    }

    return array_chunk(array_pad($days, 42, null), 7);
}
