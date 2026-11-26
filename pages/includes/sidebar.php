<?php

declare(strict_types=1);

(static function (): void {
$navScriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$gamifiedPathPosition = strpos($navScriptPath, '/gamified/');
$pagesBaseUrl = $gamifiedPathPosition === false
    ? rtrim(dirname($navScriptPath), '/')
    : substr($navScriptPath, 0, $gamifiedPathPosition);
$pagesBaseUrl = $pagesBaseUrl === '' ? '' : $pagesBaseUrl;
$authBaseUrl = rtrim(dirname($pagesBaseUrl), '/');
$userType = strtolower((string) ($_SESSION['user_type'] ?? ''));

if (!in_array($userType, ['student', 'admin'], true)) {
    throw new RuntimeException('The authenticated user role is not supported by the navigation.');
}

$isAdmin = $userType === 'admin';
$userName = trim((string) ($_SESSION['user_name'] ?? ''));
$userName = $userName !== '' ? $userName : ($isAdmin ? 'Administrator' : 'Student');
$scriptName = basename($navScriptPath);
$isGamifiedStudentPage = !$isAdmin && strpos($navScriptPath, '/gamified/Student/') !== false;
$isGamifiedAdminPage = $isAdmin && strpos($navScriptPath, '/gamified/Admin/') !== false;
$escapeNavigationValue = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};
$navigationIcon = static function (string $name) use ($pagesBaseUrl, $escapeNavigationValue): string {
    return '<img class="comfsci-nav-icon" src="' . $escapeNavigationValue($pagesBaseUrl . '/assets/rai-icons/' . $name . '.png')
        . '" alt="" aria-hidden="true" width="18" height="18">';
};

if ($isAdmin) {
    $navigationItems = [
        ['Dashboard', '/dashboard.php', 'dashboard', $scriptName === 'dashboard.php'],
        ['Students', '/admin_students.php', 'user', $scriptName === 'admin_students.php'],
        ['Blocks', '/admin_blocks.php', 'layout', $scriptName === 'admin_blocks.php'],
        ['Assignments', '/admin_assignments.php', 'file', $scriptName === 'admin_assignments.php'],
        ['Events', '/admin_events.php', 'calendar', $scriptName === 'admin_events.php'],
        ['Announcements', '/admin_announcements.php', 'bell', $scriptName === 'admin_announcements.php'],
        ['Gamified Quiz', '/gamified/Admin/quiz1.php?tab=create', 'quiz', $isGamifiedAdminPage],
        ['Module Sources', '/module_sources.php', 'download', $scriptName === 'module_sources.php'],
        ['Event operations', '/admin_event_operations.php?tab=approvals', 'calendar', $scriptName === 'admin_event_operations.php'],
        ['Resources', '/resources.php', 'download', $scriptName === 'resources.php'],
        ['Block Forum', '/forum.php', 'bell', $scriptName === 'forum.php'],
    ];
} else {
    $navigationItems = [
        ['Dashboard', '/dashboard.php', 'dashboard', $scriptName === 'dashboard.php'],
        ['Profile', '/student_profile.php', 'user', $scriptName === 'student_profile.php'],
        ['Classes', '/student_class.php', 'layout', $scriptName === 'student_class.php'],
        ['Attendance', '/dashboard.php#attendance', 'attendance', $scriptName === 'dashboard.php' && isset($_GET['section']) && $_GET['section'] === 'attendance'],
        ['Gamified Quiz', '/gamified/Student/student_dashboard.php', 'quiz', $isGamifiedStudentPage],
        ['Assignments', '/assignments.php', 'file', $scriptName === 'assignments.php'],
        ['Campus Events', '/events.php', 'calendar', in_array($scriptName, ['events.php', 'event_attendance.php'], true)],
        ['Group Activities', '/group_activities.php', 'layout', $scriptName === 'group_activities.php'],
        ['Block Forum', '/forum.php', 'bell', $scriptName === 'forum.php'],
        ['Resources', '/resources.php', 'download', $scriptName === 'resources.php'],
        ['Grade Breakdown', '/grade_calculator.php', 'checklist', $scriptName === 'grade_calculator.php'],
        ['Announcements', '/dashboard.php#notifications', 'bell', false],
        ['Academic Calendar', '/dashboard.php#academicCalendar', 'calendar', false],
        ['Module Sources', '/module_sources.php', 'download', $scriptName === 'module_sources.php'],
    ];
}
?>
<aside class="comfsci-sidebar" id="dashboardSidebar" aria-label="<?= $isAdmin ? 'Administrator' : 'Student' ?> navigation">
    <div class="comfsci-sidebar-identity">
        <div class="comfsci-avatar">
            <img src="<?= $escapeNavigationValue($pagesBaseUrl . '/assets/profileIcon.png') ?>" alt="">
        </div>
        <h2 class="comfsci-user-name"><?= $escapeNavigationValue($userName) ?></h2>
        <p class="comfsci-user-role"><?= $isAdmin ? 'Administrator' : 'Student Number: ' . (int) ($_SESSION['user_id'] ?? 0) ?></p>
    </div>
    <nav class="comfsci-nav" aria-label="Main navigation">
        <ul>
            <?php foreach ($navigationItems as [$label, $href, $icon, $isActive]): ?>
                <li>
                    <a href="<?= $escapeNavigationValue($pagesBaseUrl . $href) ?>"
                       title="<?= $escapeNavigationValue($label) ?>"
                       <?= $isActive ? 'class="active" aria-current="page"' : '' ?>>
                        <?= $navigationIcon($icon) ?><span class="comfsci-nav-label"><?= $escapeNavigationValue($label) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <a class="comfsci-logout" href="<?= $escapeNavigationValue($authBaseUrl . '/auth/logout.php') ?>" title="Logout">
            <?= $navigationIcon('logout') ?><span class="comfsci-nav-label">Logout</span>
        </a>
    </nav>
</aside>
<a class="comfsci-sidebar-backdrop" href="#" data-sidebar-close aria-label="Close navigation"></a>
<button class="comfsci-sidebar-trigger" type="button" aria-controls="dashboardSidebar" aria-expanded="false" aria-label="Open navigation">
    <?= $navigationIcon('menu') ?>
</button>
<script src="<?= $escapeNavigationValue($pagesBaseUrl . '/assets/sidebar.js?v=1') ?>" defer></script>
<?php
})();
?>
