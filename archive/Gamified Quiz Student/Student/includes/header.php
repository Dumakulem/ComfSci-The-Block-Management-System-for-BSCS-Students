<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/icons.php';

$page_title = $PAGE_TITLE ?? 'Gamified Quiz';
$flash_msg = flash();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= h($page_title) ?></title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <div class="app">

        <aside class="rail">

            <!-- Student Profile -->
            <div class="profile">
                <svg viewBox="0 0 64 64">
                    <rect x="8" y="4" width="48" height="56" rx="24" fill="#fff"/>
                    <circle cx="32" cy="26" r="9" fill="#1c1c1c"/>
                    <circle cx="32" cy="26" r="4" fill="#fff"/>
                    <path d="M20 50c3-8 21-8 24 0z" fill="#1c1c1c"/>
                </svg>

                <b>
                    <?= h(($current_student['studFirstName'] ?? '') . ' ' . ($current_student['studLastName'] ?? '')) ?>
                </b>

                <small>STUDENT</small>
            </div>

            <!-- Sidebar Navigation -->
            <nav class="nav">

                <a href="student_dashboard.php?tab=dashboard"
                   class="<?= ($current_page == 'student_dashboard.php' && isset($_GET['tab']) && $_GET['tab'] == 'dashboard') ? 'on' : '' ?>">
                    <?= ic('grid') ?> Dashboard
                </a>

                <a href="profile.php"
                   class="<?= $current_page == 'profile.php' ? 'on' : '' ?>">
                    <?= ic('user') ?> Profile
                </a>

                <a href="block_forum.php"
                   class="<?= $current_page == 'block_forum.php' ? 'on' : '' ?>">
                    <?= ic('cup') ?> Block Forum
                </a>

                <a href="attendance.php"
                   class="<?= $current_page == 'attendance.php' ? 'on' : '' ?>">
                    <?= ic('chk') ?> Attendance
                </a>

                <a href="student_dashboard.php"
                   class="<?= ($current_page == 'student_dashboard.php' && (!isset($_GET['tab']) || $_GET['tab'] != 'dashboard')) || $current_page == 'take_quiz.php' ? 'on' : '' ?>">
                    <?= ic('bars') ?> Gamified Quiz
                </a>

                <a href="assignments.php"
                   class="<?= $current_page == 'assignments.php' ? 'on' : '' ?>">
                    <?= ic('doc') ?> Assignments
                </a>

                <a href="announcement.php"
                   class="<?= $current_page == 'announcement.php' ? 'on' : '' ?>">
                    <?= ic('bell') ?> Announcement
                </a>

                <a href="academic_calendar.php"
                   class="<?= $current_page == 'academic_calendar.php' ? 'on' : '' ?>">
                    <?= ic('cal') ?> Academic Calendar
                </a>

                <a href="module_sources.php"
                   class="<?= $current_page == 'module_sources.php' ? 'on' : '' ?>">
                    <?= ic('cloud') ?> Module Sources
                </a>

                <a href="logout.php" class="out">
                    <?= ic('out') ?> Logout
                </a>

            </nav>
        </aside>

        <main>

            <!-- Top Header -->
            <div class="top">
                <div class="crumb">
                    <?= ic('bars') ?>
                    <span><?= h($page_title) ?></span>
                </div>

                <div>
                    <button class="ib" title="Notifications">
                        <?= ic('bell') ?>
                    </button>

                    <button class="ib" title="Settings">
                        <?= ic('gear') ?>
                    </button>
                </div>
            </div>

            <!-- Flash Message -->
            <?php if ($flash_msg): ?>
                <div style="
                    background: #e6f4ea;
                    color: #137333;
                    padding: 12px 20px;
                    border-radius: 12px;
                    margin-bottom: 14px;
                    border: 1px solid #ceead6;
                    font-weight: 600;
                ">
                    <?= h($flash_msg) ?>
                </div>
            <?php endif; ?>