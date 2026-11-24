<?php

declare(strict_types=1);

require_once __DIR__ . '/dashboard_helpers.php';

$pageTitle = 'Student Dashboard';
$dashboardData = dashboard_load_student((int) $userId);
extract($dashboardData, EXTR_OVERWRITE);

$today = date('Y-m-d');
$yearMonth = (string) ($_GET['m'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $yearMonth)) {
    $yearMonth = date('Y-m');
}
[$calendarYear, $calendarMonth] = array_map('intval', explode('-', $yearMonth));
$monthLabel = date('F Y', strtotime($yearMonth . '-01'));
$previousMonth = date('Y-m', strtotime($yearMonth . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($yearMonth . '-01 +1 month'));
$calendar = dashboard_calendar($calendarYear, $calendarMonth);
$monthMeetings = dashboard_expand_schedules(
    $schedules,
    $yearMonth . '-01',
    date('Y-m-t', strtotime($yearMonth . '-01'))
);
$upcoming = dashboard_expand_schedules($schedules, $today, date('Y-m-d', strtotime('+13 days')));
$soonNotifications = array_values(array_filter($upcoming, static function (array $meeting) use ($today): bool {
    return $meeting['date'] <= date('Y-m-d', strtotime('+1 day'));
}));
$tasksLeft = count(array_filter($tasks, static function (array $task): bool {
    return !$task['done'];
}));
$calendarItems = [];
foreach ($monthMeetings as $meeting) {
    $calendarItems[$meeting['date']][] = $meeting['subject'];
}
foreach ($tasks as $task) {
    if ($task['date'] !== '') {
        $calendarItems[$task['date']][] = $task['title'];
    }
}
foreach ($assignments as $assignment) {
    if (!empty($assignment['due'])) {
        $calendarItems[substr((string) $assignment['due'], 0, 10)][] = $assignment['title'];
    }
}
foreach ($calendarEvents as $event) {
    $calendarItems[$event['date']][] = $event['title'];
}
$editPriorities = ($_GET['edit'] ?? '') === 'assignments';
$flashMessage = (string) ($_SESSION['dashboard_flash'] ?? '');
unset($_SESSION['dashboard_flash']);
$notificationCount = count($notifications) + count($soonNotifications);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-sidebar="closed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= dashboard_escape($pageTitle) ?></title>
    <link rel="stylesheet" href="./assets/dashboard.css?v=6">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="dashboardSidebar" aria-label="Student navigation">
        <div class="avatar" id="profile">
            <img src="./assets/profileIcon.png" alt="" class="avatarImg">
        </div>
        <h2 class="student-name"><?= dashboard_escape($student['name']) ?></h2>
        <p class="student-no">Student Number: <?= dashboard_escape((string) $student['number']) ?></p>
        <nav class="nav-panel" aria-label="Main navigation">
            <ul>
                <li><a href="#dashboard" class="active" aria-current="page" title="Dashboard"><?= dashboard_icon('dashboard') ?><span class="nav-label">Dashboard</span></a></li>
                <li><a href="#profile" title="Profile"><?= dashboard_icon('user') ?><span class="nav-label">Profile</span></a></li>
                <li><a href="#attendance" title="Attendance"><?= dashboard_icon('attendance') ?><span class="nav-label">Attendance</span></a></li>
                <li><a href="#gamifiedQuiz" title="Gamified Quiz"><?= dashboard_icon('quiz') ?><span class="nav-label">Gamified Quiz</span></a></li>
                <li><a href="#assignments" title="Assignments"><?= dashboard_icon('file') ?><span class="nav-label">Assignments</span></a></li>
                <li><a href="#notifications" title="Announcements"><?= dashboard_icon('bell') ?><span class="nav-label">Announcements</span></a></li>
                <li><a href="#academicCalendar" title="Academic Calendar"><?= dashboard_icon('calendar') ?><span class="nav-label">Academic Calendar</span></a></li>
                <li><a href="#moduleSources" title="Module Sources"><?= dashboard_icon('download') ?><span class="nav-label">Module Sources</span></a></li>
            </ul>
            <a href="../auth/logout.php" class="logout" title="Logout"><?= dashboard_icon('logout') ?><span class="nav-label">Logout</span></a>
        </nav>
    </aside>
    <a class="sidebarBackdrop" href="#" data-sidebar-close aria-label="Close navigation"></a>

    <div class="main">
        <header class="topbar">
            <div class="top-left">
                <button class="icon-btn sidebarToggle" type="button" aria-controls="dashboardSidebar" aria-expanded="false" aria-label="Open navigation">
                    <?= dashboard_icon('menu', 18) ?>
                </button>
                <span class="tab"><?= dashboard_icon('layout', 16) ?>Dashboard</span>
            </div>
            <div class="actions">
                <button class="icon-btn themeToggle" type="button" aria-label="Switch to dark mode" title="Switch theme">
                    <?= dashboard_icon('moon', 18) ?>
                </button>
                <div class="bell-wrap">
                    <button class="icon-btn notificationToggle" type="button" aria-label="Notifications<?= $notificationCount ? ' (' . $notificationCount . ')' : '' ?>" aria-expanded="false">
                        <?= dashboard_icon('bell', 18) ?>
                        <?php if ($notificationCount): ?><span class="badge"><?= $notificationCount > 9 ? '9+' : $notificationCount ?></span><?php endif; ?>
                    </button>
                    <section class="notif-pop" hidden aria-label="Notifications">
                        <div class="card-head">
                            <h3><?= dashboard_icon('bell', 16) ?>Notifications</h3>
                            <form method="post" action="./dashboard_action.php">
                                <?= dashboard_csrf_field() ?>
                                <input type="hidden" name="m" value="<?= dashboard_escape($yearMonth) ?>">
                                <input type="hidden" name="action" value="clear_notifications">
                                <button class="linkbtn link-orange"<?= $notifications ? '' : ' disabled' ?>>Clear</button>
                            </form>
                        </div>
                        <?php if (!$notifications && !$soonNotifications): ?><p class="empty">You're all caught up.</p><?php endif; ?>
                        <?php foreach ($soonNotifications as $meeting): ?>
                            <article class="note">
                                <h4><span class="dot"></span>Online class <?= $meeting['date'] === $today ? 'today' : 'tomorrow' ?></h4>
                                <p class="muted"><?= dashboard_escape($meeting['subject']) ?> · Prof. <?= dashboard_escape($meeting['professor']) ?></p>
                                <p class="meta"><span><?= dashboard_icon('calendar', 13) ?><?= dashboard_escape(date('D, M j', strtotime($meeting['date']))) ?></span><span><?= dashboard_icon('clock', 13) ?><?= dashboard_escape(dashboard_format_time($meeting['time'])) ?></span></p>
                            </article>
                        <?php endforeach; ?>
                        <?php foreach ($notifications as $notification): ?>
                            <article class="note">
                                <h4><?= dashboard_escape($notification['title']) ?></h4>
                                <p class="muted"><?= dashboard_escape($notification['subtitle']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </section>
                </div>
            </div>
        </header>

        <main class="content" id="dashboard">
            <section class="hero">
                <h1>Hi, <?= dashboard_escape($student['name']) ?>! 👋</h1>
                <h2>Check out your Assignment!</h2>
                <p>You have <?= count($assignments) ?> <?= count($assignments) === 1 ? 'assignment' : 'assignments' ?>, <?= $tasksLeft ?> <?= $tasksLeft === 1 ? 'task' : 'tasks' ?> left today and <?= count($upcoming) ?> upcoming <?= count($upcoming) === 1 ? 'class' : 'online classes' ?>.</p>
            </section>
            <?php if ($flashMessage !== ''): ?><p class="toast" role="status"><?= dashboard_escape($flashMessage) ?></p><?php endif; ?>

            <div class="grid">
                <section class="cell a-notif" id="notifications">
                    <div class="card card-fill">
                        <div class="card-head">
                            <h3><?= dashboard_icon('bell', 16) ?>Notifications</h3>
                            <form method="post" action="./dashboard_action.php">
                                <?= dashboard_csrf_field() ?>
                                <input type="hidden" name="m" value="<?= dashboard_escape($yearMonth) ?>">
                                <input type="hidden" name="action" value="clear_notifications">
                                <button class="linkbtn link-orange"<?= $notifications ? '' : ' disabled' ?>>Clear</button>
                            </form>
                        </div>
                        <div class="scroll" tabindex="0" aria-label="Notifications">
                            <?php if (!$notifications): ?><p class="empty">You're all caught up.</p><?php endif; ?>
                            <?php foreach ($notifications as $notification): ?>
                                <article class="note">
                                    <h4><?php if ($notification['kind'] === 'event'): ?><span class="dot"></span><?php endif; ?><?= dashboard_escape($notification['title']) ?></h4>
                                    <p class="muted"><?= dashboard_escape($notification['subtitle']) ?></p>
                                    <?php if (!empty($notification['date'])): ?>
                                        <p class="meta"><span><?= dashboard_icon('calendar', 13) ?><?= dashboard_escape($notification['date']) ?></span><span><?= dashboard_icon('clock', 13) ?><?= dashboard_escape($notification['time']) ?></span></p>
                                    <?php else: ?>
                                        <p class="meta"><?= dashboard_escape($notification['time']) ?></p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="cell a-tasks" id="todo">
                    <div class="card card-fill">
                        <div class="card-head">
                            <h3><?= dashboard_icon('checklist', 18) ?>To-do list</h3>
                            <button class="linkbtn link-orange addTaskToggle" type="button" aria-expanded="false"><?= dashboard_icon('plus', 13) ?>Add</button>
                        </div>
                        <form class="add-task-form" method="post" action="./dashboard_action.php" hidden>
                            <?= dashboard_csrf_field() ?>
                            <input type="hidden" name="m" value="<?= dashboard_escape($yearMonth) ?>">
                            <input type="hidden" name="action" value="add_task">
                            <label>Task<input name="title" maxlength="60" required placeholder="Add a to-do"></label>
                            <div class="task-form-row">
                                <input type="date" name="date" required aria-label="Deadline date">
                                <input type="time" name="time" required aria-label="Deadline time">
                                <button class="btn btn-solid" type="submit">Add</button>
                            </div>
                        </form>
                        <?php if (!$tasks): ?><p class="empty">Nothing to do yet. Add your first to-do.</p><?php endif; ?>
                        <ul class="tasks" aria-label="To-do items">
                            <?php foreach ($tasks as $task): ?>
                                <?php
                                $dueAt = strtotime((string) $task['date'] . ' ' . (string) $task['time']);
                                $isLate = !$task['done'] && $dueAt < time();
                                $dueLabel = date('D, M j', strtotime((string) $task['date'])) . ' · ' . dashboard_format_time((string) $task['time']);
                                ?>
                                <li>
                                    <form method="post" action="./dashboard_action.php">
                                        <?= dashboard_csrf_field() ?>
                                        <input type="hidden" name="m" value="<?= dashboard_escape($yearMonth) ?>">
                                        <input type="hidden" name="action" value="toggle_task">
                                        <input type="hidden" name="task_id" value="<?= (int) $task['taskID'] ?>">
                                        <button class="task<?= $task['done'] ? ' done' : '' ?>" type="submit" aria-pressed="<?= $task['done'] ? 'true' : 'false' ?>">
                                            <span class="task-text">
                                                <strong><?= dashboard_escape($task['title']) ?></strong>
                                                <small<?= $isLate ? ' class="late"' : '' ?>><?= dashboard_icon('calendar', 12) ?><?= $isLate ? 'Overdue · ' : 'Due ' ?><?= dashboard_escape($dueLabel) ?></small>
                                            </span>
                                            <span class="box"><?= dashboard_icon('check', 14) ?></span>
                                        </button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <button class="add-btn addTaskToggle" type="button"><?= dashboard_icon('plus', 15) ?>Add to-do</button>
                    </div>
                </section>

                <section class="cell a-cal" id="academicCalendar">
                    <div class="card cal-card">
                        <div class="card-head">
                            <h3 class="cal-title"><?= dashboard_icon('calendar', 20) ?><span><?= dashboard_escape($monthLabel) ?></span></h3>
                            <div class="cal-nav">
                                <a href="?m=<?= dashboard_escape($previousMonth) ?>" aria-label="Previous month"><?= dashboard_icon('prev', 16) ?></a>
                                <a href="?m=<?= dashboard_escape($nextMonth) ?>" aria-label="Next month"><?= dashboard_icon('next', 16) ?></a>
                            </div>
                        </div>
                        <div class="cal-grid">
                            <?php foreach (['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $weekdayIndex => $weekday): ?>
                                <div class="cw<?= $weekdayIndex === 0 || $weekdayIndex === 6 ? ' wk' : '' ?>"><?= $weekday ?></div>
                            <?php endforeach; ?>
                            <?php foreach ($calendar as $week): ?>
                                <?php foreach ($week as $weekdayIndex => $day): ?>
                                    <?php
                                    $isoDate = $day ? sprintf('%s-%02d', $yearMonth, $day) : '';
                                    $dayClasses = [];
                                    if ($isoDate === $today) {
                                        $dayClasses[] = 'today';
                                    }
                                    if (isset($calendarItems[$isoDate])) {
                                        $dayClasses[] = 'has-event';
                                    }
                                    ?>
                                    <div class="cd<?= $weekdayIndex === 0 || $weekdayIndex === 6 ? ' sun' : '' ?>">
                                        <?php if ($day): ?>
                                            <a href="?m=<?= dashboard_escape($yearMonth) ?>&day=<?= dashboard_escape($isoDate) ?>" class="<?= implode(' ', $dayClasses) ?>"<?= isset($calendarItems[$isoDate]) ? ' title="' . dashboard_escape(implode(', ', $calendarItems[$isoDate])) . '"' : '' ?>><?= $day ?></a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                        <p class="legend"><span class="ring"></span>Has a class or event. Select any date to see details.</p>
                        <?php if (isset($_GET['day']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['day'])): ?>
                            <?php $selectedDate = (string) $_GET['day']; ?>
                            <div class="selected-day"><strong><?= dashboard_escape(date('D, M j', strtotime($selectedDate))) ?></strong>
                                <?php foreach ($calendarItems[$selectedDate] ?? [] as $item): ?><span><?= dashboard_escape($item) ?></span><?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="cell a-assign" id="assignments">
                    <div class="card card-fill">
                        <div class="card-head">
                            <h3><?= dashboard_icon('file', 16) ?>Assignments</h3>
                            <a href="?m=<?= dashboard_escape($yearMonth) ?>&edit=assignments" class="link-orange"><?= dashboard_icon('edit', 13) ?>Edit</a>
                        </div>
                        <div class="scroll assign-list" tabindex="0" aria-label="Assignments">
                            <?php if (!$assignments): ?><p class="empty">No assignments yet.</p><?php endif; ?>
                            <?php foreach ($assignments as $assignment): ?>
                                <article class="assignment">
                                    <div class="chips">
                                        <span class="chip">Deadline</span>
                                        <span class="chip chip-grey"><?= dashboard_escape(date('M j, g:i A', strtotime((string) $assignment['due']))) ?></span>
                                        <span class="chip<?= $assignment['priority'] === 'High priority' ? ' chip-hot' : ($assignment['priority'] === 'Medium priority' ? ' chip-med' : '') ?>"><?= dashboard_escape($assignment['priority']) ?></span>
                                    </div>
                                    <h4><?= dashboard_escape($assignment['title']) ?></h4>
                                    <?php if ($editPriorities): ?>
                                        <form method="post" action="./dashboard_action.php" class="priority-form">
                                            <?= dashboard_csrf_field() ?>
                                            <input type="hidden" name="m" value="<?= dashboard_escape($yearMonth) ?>">
                                            <input type="hidden" name="action" value="update_priority">
                                            <input type="hidden" name="assignment_id" value="<?= (int) $assignment['assignmentID'] ?>">
                                            <label>Priority
                                                <select name="priority">
                                                    <?php foreach (['High', 'Medium', 'Low'] as $priority): ?>
                                                        <option value="<?= $priority ?>"<?= $assignment['priority'] === $priority . ' priority' ? ' selected' : '' ?>><?= $priority ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <button class="btn btn-solid" type="submit">Save</button>
                                        </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="cell a-meet" id="attendance">
                    <div class="card card-fill meeting">
                        <div class="card-head">
                            <h3><?= dashboard_icon('video', 16) ?>Online meetings</h3>
                        </div>
                        <?php if (!$upcoming): ?>
                            <p class="empty">No online classes in the next two weeks.</p>
                        <?php else: ?>
                            <div class="scroll meet-list" tabindex="0" aria-label="Upcoming online classes">
                                <?php foreach (array_slice($upcoming, 0, 5) as $meeting): ?>
                                    <article class="meet">
                                        <?php
                                        $chip = $meeting['date'] === $today ? 'Today' : (
                                            $meeting['date'] === date('Y-m-d', strtotime('+1 day')) ? 'Tomorrow' : ''
                                        );
                                        $hasMeetingLink = preg_match('#^https?://#i', $meeting['link']) === 1;
                                        ?>
                                        <div class="meet-info">
                                            <h4>You have Online Class!<?php if ($chip): ?> <span class="chip chip-hot"><?= $chip ?></span><?php endif; ?></h4>
                                            <p class="meta"><span><?= dashboard_icon('calendar', 13) ?><?= dashboard_escape(date('D, M j', strtotime($meeting['date']))) ?></span><span><?= dashboard_icon('clock', 13) ?><?= dashboard_escape(dashboard_format_time($meeting['time']) . ' - ' . dashboard_format_time($meeting['end'])) ?></span></p>
                                            <p class="meet-sub"><?= dashboard_escape($meeting['subject']) ?></p>
                                            <p class="muted">Prof. <?= dashboard_escape($meeting['professor']) ?></p>
                                        </div>
                                        <?php if ($hasMeetingLink): ?>
                                            <a class="join" href="<?= dashboard_escape($meeting['link']) ?>" target="_blank" rel="noopener noreferrer"><?= dashboard_icon('video', 14) ?>Join</a>
                                        <?php else: ?>
                                            <span class="join join-off">No link</span>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <script src="./assets/dashboard.js?v=6"></script>
</body>
</html>
