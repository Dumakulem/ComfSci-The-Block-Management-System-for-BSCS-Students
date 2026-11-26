<?php

declare(strict_types=1);

require_once __DIR__ . '/dashboard_helpers.php';

$pageTitle = 'Administrator Dashboard';
$adminData = dashboard_load_admin();
$counts = $adminData['counts'];
$announcements = $adminData['announcements'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-sidebar="closed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= dashboard_escape($pageTitle) ?></title>
    <link rel="stylesheet" href="./assets/dashboard.css?v=9">
    <link rel="stylesheet" href="./assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<div class="app">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main">
        <header class="topbar">
            <div class="top-left">
                <span class="tab"><?= dashboard_icon('layout', 16) ?>Dashboard</span>
            </div>
            <div class="actions">
                <button class="icon-btn themeToggle" type="button" aria-label="Switch to dark mode" title="Switch theme"><?= dashboard_icon('moon', 18) ?></button>
            </div>
        </header>
        <main class="content admin-content">
            <section class="hero">
                <h1>Welcome, <?= dashboard_escape($userName) ?>!</h1>
                <h2>Administrator overview</h2>
                <p>Choose a section to manage its records.</p>
            </section>
            <div class="admin-dashboard-grid">
                <a class="admin-stat" href="./admin_students.php"><?= dashboard_icon('user', 22) ?><strong><?= $counts['students'] ?></strong><span>Active students</span></a>
                <a class="admin-stat" href="./admin_blocks.php"><?= dashboard_icon('layout', 22) ?><strong><?= $counts['blocks'] ?></strong><span>Active blocks</span></a>
                <a class="admin-stat" href="./admin_assignments.php"><?= dashboard_icon('file', 22) ?><strong><?= $counts['assignments'] ?></strong><span>Active assignments</span></a>
                <a class="admin-stat" href="./admin_events.php"><?= dashboard_icon('calendar', 22) ?><strong><?= $counts['events'] ?></strong><span>Active events</span></a>
                <section class="card admin-announcements">
                    <div class="card-head">
                        <h3><?= dashboard_icon('bell', 17) ?>Recent announcements</h3>
                        <a class="link-orange" href="./admin_announcements.php">Manage announcements</a>
                    </div>
                    <?php if (!$announcements): ?><p class="empty">No active announcements.</p><?php endif; ?>
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
<script src="./assets/dashboard.js?v=7"></script>
<?php require __DIR__ . '/../includes/ai_chat_widget.php'; ?>
</body>
</html>
