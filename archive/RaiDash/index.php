<?php
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php';
['student' => $student, 'notifications' => $notifications, 'assignments' => $assignments, 'tasks' => $tasks, 'schedules' => $schedules] = load_dashboard();

// Sidebar menu (links live in includes/nav.php); the link that matches this page is highlighted.
$page = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$nav  = array_map(fn($n) => [$n[0], $n[1], $n[2], basename($n[2]) === $page], require __DIR__ . '/includes/nav.php');

$ym = is_string($_GET['m'] ?? null) ? $_GET['m'] : date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) { $ym = date('Y-m'); }
[$year, $month] = array_map('intval', explode('-', $ym));
$calendar   = build_calendar($year, $month);
$monthLabel = date('F Y', strtotime("$ym-01"));
$prevMonth  = date('Y-m', strtotime("$ym-01 -1 month"));
$nextMonth  = date('Y-m', strtotime("$ym-01 +1 month"));
$today      = date('Y-m-d');
$tasksLeft  = count(array_filter($tasks, fn($t) => !$t['done']));

// Weekly schedules -> dated classes. $meetings = the month shown on the calendar, $upcoming = next 14 days.
$meetings  = expand_schedules($schedules, "$ym-01", date('Y-m-t', strtotime("$ym-01")));
$meetDays  = array_values(array_unique(array_column($meetings, 'date')));
$upcoming  = expand_schedules($schedules, $today, date('Y-m-d', strtotime('+13 days')));

$del   = isset($_GET['del']) ? (int)$_GET['del'] : -1;   // schedule waiting for delete confirmation
$edit  = in_array($_GET['edit'] ?? '', ['tasks', 'addtask', 'assignments', 'schedules', 'addschedule'], true) ? $_GET['edit'] : '';
$pick  = isset($_GET['pick']) ? (int)$_GET['pick'] : -1;
$cpYear = preg_match('/^\d{4}$/', (string)($_GET['cp'] ?? '')) ? max(1970, min(2100, (int)$_GET['cp'])) : 0;
$picker = !$edit && $cpYear > 0;               // month / year picker pop-up open?
$panel = ($_GET['panel'] ?? '') === 'notif';   // notification dropdown open?
$day   = is_string($_GET['day'] ?? null) ? $_GET['day'] : '';
$dt    = DateTime::createFromFormat('!Y-m-d', $day);
if (!$dt || $dt->format('Y-m-d') !== $day) { $day = ''; }
// Pop the online-class notification once per day when a class is today.
if (!$edit && !$picker && $day === '' && expand_schedules($schedules, $today, $today) && ($_SESSION['meet_popup'] ?? '') !== $today) { $day = $today; $_SESSION['meet_popup'] = $today; }
$dayMeetings = $day ? expand_schedules($schedules, $day, $day) : [];
$dayTasks    = $day ? array_values(array_filter($tasks, fn($t) => $t['date'] === $day)) : [];
$dayAssign   = $day ? array_values(array_filter($assignments, fn($a) => $a['due'] && substr($a['due'], 0, 10) === $day)) : [];
$zoom  = $picker && ($_GET['z'] ?? '') === '1';   // month picked -> show that month's days inside the picker

// Everything that happens on a date (classes, to-dos, assignment deadlines) -> tooltip + dot on the calendar.
$byDay = [];
foreach ($meetings as $m)    { $byDay[$m['date']][] = $m['subject']; }
foreach ($tasks as $t)       { if ($t['date'] !== '') { $byDay[$t['date']][] = $t['title']; } }
foreach ($assignments as $a) { if ($a['due']) { $byDay[substr($a['due'], 0, 10)][] = $a['title']; } }
$theme = ($_COOKIE['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
$sb    = 'closed';   // sidebar starts as the icon rail; hovering it opens (and pushes the page)
$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>" data-sidebar="<?= $sb ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard | <?= e($student['name']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="app">
  <?php include __DIR__ . '/views/sidebar.php'; ?>
  <div class="main">
    <?php include __DIR__ . '/views/topbar.php'; ?>
    <main class="content">
      <?php include __DIR__ . '/views/welcomesign.php'; ?>
      <div class="grid">
        <div class="cell a-notif"><?php include __DIR__ . '/views/notifications.php'; ?></div>
        <div class="cell a-tasks"><?php include __DIR__ . '/views/tasks.php'; ?></div>
        <div class="cell a-cal"><?php include __DIR__ . '/views/calendar.php'; ?></div>
        <div class="cell a-assign"><?php include __DIR__ . '/views/assignments.php'; ?></div>
        <div class="cell a-meet"><?php include __DIR__ . '/views/meeting.php'; ?></div>
      </div>
    </main>
  </div>
</div>
<?php include __DIR__ . '/views/modal.php'; ?>
<?php if ($flash): ?><div class="toast" role="status"><?= e($flash) ?></div><?php endif; ?>
<script>
// Sidebar: slim icon rail by default. Hover (or keyboard focus) opens it and pushes the page; leaving closes it.
(function () {
  var root = document.documentElement, phone = window.matchMedia('(max-width:900px)');
  var sb = document.getElementById('sidebar'), timer;
  function sync() {
    var btn = document.querySelector('.sb-toggle');
    if (btn) btn.setAttribute('aria-expanded', String(phone.matches ? root.classList.contains('sb-mobile-open') : root.dataset.sidebar === 'open'));
  }
  function set(v) { if (phone.matches) return; clearTimeout(timer); root.dataset.sidebar = v; sync(); }
  sb.addEventListener('mouseenter', function () { set('open'); });
  sb.addEventListener('mouseleave', function () { timer = setTimeout(function () { set('closed'); }, 150); });
  sb.addEventListener('focusin',    function () { set('open'); });
  sb.addEventListener('focusout',   function (e) { if (!sb.contains(e.relatedTarget)) set('closed'); });

  // Narrow screens (<=900px) have no rail: the menu button slides the sidebar over the page.
  document.addEventListener('submit', function (e) {
    if (!e.target.matches('.sb-form')) return;
    e.preventDefault();
    if (phone.matches) { root.classList.toggle('sb-mobile-open'); sync(); }
  });
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-sb-close]')) { e.preventDefault(); root.classList.remove('sb-mobile-open'); sync(); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && root.classList.contains('sb-mobile-open')) { root.classList.remove('sb-mobile-open'); sync(); }
  });
  phone.addEventListener('change', function () { root.classList.remove('sb-mobile-open'); sync(); });
})();
</script>
</body>
</html>
