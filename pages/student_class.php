<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/renderers/dashboard_helpers.php';

if (strtolower((string) $_SESSION['user_type']) !== 'student') {
    http_response_code(403);
    exit('This page is only available to students.');
}

$userId = (int) $_SESSION['user_id'];
$profile = dashboard_load_student_profile($userId);
$userName = trim(implode(' ', array_filter([
    (string) $profile['studentFirstName'],
    (string) ($profile['studentMiddleName'] ?? ''),
    (string) $profile['studentLastName'],
    (string) ($profile['studentExtensionName'] ?? ''),
], static function (string $part): bool {
    return trim($part) !== '';
})));
$formatValue = static function ($value): string {
    return $value === null || trim((string) $value) === ''
        ? 'Not provided'
        : (string) $value;
};
$pageTitle = 'Student Profile';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-sidebar="closed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= dashboard_escape($pageTitle) ?></title>
    <link rel="stylesheet" href="./assets/dashboard.css?v=10">
    <link rel="stylesheet" href="./assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<div class="app">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main">
        <header class="topbar">
            <div class="top-left">
                <span class="tab"><?= dashboard_icon('user', 16) ?>Profile</span>
            </div>
            <div class="actions">
                <button class="icon-btn themeToggle" type="button" aria-label="Switch to dark mode" title="Switch theme"><?= dashboard_icon('moon', 18) ?></button>
            </div>
        </header>
        <main class="content profile-content">
            <section class="hero profile-banner">
                <h1>Profile / Enlistment</h1>
                <p>View your personal and academic information</p>
            </section>

            <section class="card profile-overview-card" aria-label="Student personal information">
                <div class="profile-overview-details">
                    <div class="profile-info-row"><strong>Student Number</strong><span><?= dashboard_escape((string) $profile['studentID']) ?></span></div>
                    <div class="profile-info-row"><strong>Full name</strong><span><?= dashboard_escape($userName) ?></span></div>
                    <div class="profile-info-row"><strong>Block</strong><span><?= dashboard_escape($formatValue($profile['classBlock'])) ?></span></div>
                    <div class="profile-info-row"><strong>Email Address</strong><span><?= dashboard_escape($formatValue($profile['studentEmail'])) ?></span></div>
                </div>
                <div class="profile-photo-wrap">
                    <img src="./assets/profileIcon.png" alt="Profile avatar">
                </div>
            </section>

            <div class="profile-secondary-grid">
                <section class="card profile-info-card">
                    <h2><?= dashboard_icon('layout', 19) ?>Academic information</h2>
                    <dl class="profile-details">
                        <div><dt>Course / Program</dt><dd><?= dashboard_escape($formatValue($profile['courseName'])) ?></dd></div>
                        <div><dt>Curriculum</dt><dd><?= dashboard_escape($formatValue($profile['curriculumName'])) ?></dd></div>
                        <div><dt>Faculty adviser</dt><dd><?= dashboard_escape($formatValue($profile['facultyName'])) ?></dd></div>
                        <div class="profile-detail-wide"><dt>Faculty email</dt><dd><?= dashboard_escape($formatValue($profile['facultyEmail'])) ?></dd></div>
                    </dl>
                </section>

                <section class="card profile-info-card">
                    <h2><?= dashboard_icon('user', 19) ?>Class responsibilities</h2>
                    <div class="profile-role-list">
                        <div class="profile-role<?= (int) $profile['isClassRep'] === 1 ? ' is-active' : '' ?>">
                            <span>Class representative</span>
                            <strong><?= (int) $profile['isClassRep'] === 1 ? 'Yes' : 'No' ?></strong>
                        </div>
                        <div class="profile-role<?= (int) $profile['isGroupLeader'] === 1 ? ' is-active' : '' ?>">
                            <span>Group leader</span>
                            <strong><?= (int) $profile['isGroupLeader'] === 1 ? 'Yes' : 'No' ?></strong>
                        </div>
                    </div>
                </section>
            </div>
            <p class="profile-record-note">This information is displayed from your student and academic records. Contact an administrator if anything needs correction.</p>
        </main>
    </div>
</div>
<script src="./assets/dashboard.js?v=7"></script>
<?php require __DIR__ . '/includes/ai_chat_widget.php'; ?>
</body>
</html>
