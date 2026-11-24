<?php

declare(strict_types=1);

require_once __DIR__ . '/dashboard_helpers.php';

$pageTitle = 'Administrator Dashboard';
$adminData = dashboard_load_admin();
$counts = $adminData['counts'];
$announcements = $adminData['announcements'];
$flashMessage = (string) ($_SESSION['dashboard_flash'] ?? '');
unset($_SESSION['dashboard_flash']);
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
    <aside class="sidebar" id="dashboardSidebar" aria-label="Administrator navigation">
        <div class="avatar"><img src="./assets/profileIcon.png" alt="" class="avatarImg"></div>
        <h2 class="student-name"><?= dashboard_escape($userName) ?></h2>
        <p class="student-no">Administrator</p>
        <nav class="nav-panel" aria-label="Main navigation">
            <ul>
                <li><a href="#dashboard" class="active" aria-current="page" title="Dashboard"><?= dashboard_icon('dashboard') ?><span class="nav-label">Dashboard</span></a></li>
                <li><a href="#users" title="Users"><?= dashboard_icon('user') ?><span class="nav-label">Users</span></a></li>
                <li><a href="#blocks" title="Blocks"><?= dashboard_icon('layout') ?><span class="nav-label">Blocks</span></a></li>
                <li><a href="#assignments" title="Assignments"><?= dashboard_icon('file') ?><span class="nav-label">Assignments</span></a></li>
                <li><a href="#events" title="Events"><?= dashboard_icon('calendar') ?><span class="nav-label">Events</span></a></li>
                <li><a href="#announcements" title="Announcements"><?= dashboard_icon('bell') ?><span class="nav-label">Announcements</span></a></li>
            </ul>
            <a href="../auth/logout.php" class="logout" title="Logout"><?= dashboard_icon('logout') ?><span class="nav-label">Logout</span></a>
        </nav>
    </aside>
    <a class="sidebarBackdrop" href="#" data-sidebar-close aria-label="Close navigation"></a>

    <div class="main">
        <header class="topbar">
            <div class="top-left">
                <button class="icon-btn sidebarToggle" type="button" aria-controls="dashboardSidebar" aria-expanded="false" aria-label="Open navigation"><?= dashboard_icon('menu', 18) ?></button>
                <span class="tab"><?= dashboard_icon('layout', 16) ?>Dashboard</span>
            </div>
            <div class="actions">
                <button class="icon-btn themeToggle" type="button" aria-label="Switch to dark mode" title="Switch theme"><?= dashboard_icon('moon', 18) ?></button>
            </div>
        </header>
        <main class="content" id="dashboard">
            <section class="hero">
                <h1>Welcome, <?= dashboard_escape($userName) ?>!</h1>
                <h2>Administrator overview</h2>
                <p>Here’s a quick look at your students, assignments, events, and announcements.</p>
            </section>
            <?php if ($flashMessage !== ''): ?><p class="toast" role="status"><?= dashboard_escape($flashMessage) ?></p><?php endif; ?>
            <div class="admin-dashboard-grid">
                <section class="admin-stat" id="users">
                    <?= dashboard_icon('user', 22) ?><strong><?= $counts['students'] ?></strong><span>Students</span>
                </section>
                <section class="admin-stat" id="blocks">
                    <?= dashboard_icon('layout', 22) ?><strong><?= $counts['blocks'] ?></strong><span>Blocks</span>
                </section>
                <section class="admin-stat" id="assignments">
                    <?= dashboard_icon('file', 22) ?><strong><?= $counts['assignments'] ?></strong><span>Assignments</span>
                </section>
                <section class="admin-stat" id="events">
                    <?= dashboard_icon('calendar', 22) ?><strong><?= $counts['events'] ?></strong><span>Events</span>
                </section>
                <section class="card admin-announcements" id="announcements">
                    <div class="card-head">
                        <h3><?= dashboard_icon('bell', 17) ?>Recent announcements</h3>
                        <span class="link-muted"><?= count($announcements) ?> total shown</span>
                    </div>
                    <?php if (!$announcements): ?><p class="empty">No announcements have been posted yet.</p><?php endif; ?>
                    <div class="scroll">
                        <?php foreach ($announcements as $announcement): ?>
                            <article class="note">
                                <h4><?= dashboard_escape($announcement['title']) ?></h4>
                                <p class="muted"><?= dashboard_escape(mb_strimwidth((string) $announcement['content'], 0, 180, '…')) ?></p>
                                <p class="meta"><?= dashboard_escape(date('D, M j · g:i A', strtotime((string) $announcement['createdAt']))) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>
<script src="./assets/dashboard.js?v=6"></script>
</body>
</html>
