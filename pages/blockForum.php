<?php

require_once __DIR__ . '/../auth/require_auth.php';
require_once '../pages/renderers/dashboard_helpers.php';

$userName = $_SESSION['user_name'] ?? $_SESSION['user_email'];
$userEmail = $_SESSION['user_email'];
$userType = $_SESSION['user_type'];

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
<style>
/* Copied from the template Dashboard, modified for Block Forum Page */

/* Everything is sized in rem, and the root font size follows the
viewport. Browser zoom changes the viewport size, so the whole
layout scales with it and never overflows or needs scrolling.  Reference design: 1440 x 1024 -> 16px. */
/* ---------- Content area ---------- */
.contentFrame {
  /* Layout */
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 100%;
  align-items: flex-start;
  background: #BC9A89;
  padding: 1rem;
  gap: 1rem;
  border-radius: 16px;
}

.pagerFrame {
  /* Layout */
  display: flex;
  flex-direction: row;
  width: 100%;
  height: 30%;
  align-items: flex-start;
  background: #000;
  border-radius: 16px;
}


.messageFrame {
  /* Layout */
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 70%;
  overflow-y: auto;
  align-items: flex-start;
  background: #000;
  padding: 10px;
  gap: 1rem;
  border-radius: 16px;
}

.messageContent {
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 100%;
  min-height: 33%;
  align-items: flex-start;
  max-height: 40%;
  background: var(--bg, linear-gradient(174deg, #F76E10 -7.13%, #B24B28 44.24%, #682C0C 97.4%));
  padding: 10px;
  gap: 10px;

  border-radius: 18px;
}

.messengerFrame {
  display: flex;
  flex-direction: row;
  width: 100%;
  height: 25%;
  align-items: flex-start;

  border-radius: 50px;

  background-color: #000;
}

.messengerMessage {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 100%;
  height: 100%;
  /* text-wrap-mode: ; */
  border-radius: 18px;
  border: 10px solid #000;
  background-color: #b1ffa2;
  padding: 0px 10px 0 10px;
}

#messageText {
  font-family: 'Courier New', Courier, monospace;
  font-style: monospace;
  font-weight: bold;
  color: #000;
  font-size: 1.5rem;
  text-align: left;
}

.studentFrame, .dateFrame, .timeFrame {
  width: 33%;
  height: 100%;
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  align-items: center;
}

.studentName, .dateSent, .timeSent {
  font-size: 1.2rem;
  padding-left: 1rem;
  font-family: Arial, Helvetica, sans-serif;
  font-style: Arial;
  font-weight: 900;
  color: white;
}
#studentFrame {
  border-top-left-radius: 50px;
  border-bottom-left-radius: 50px;
}
.identifierFrame {
  display: flex;
  width: 30%;
  height: inherit;
  background-color: #FF8800;
  justify-content: center;
  align-items: center;
  text-align: center;
  font-weight: 900;
  font-family: Arial, Helvetica, sans-serif;
  font-style: Arial;
  font-size: 1.2rem;
  color: #000;
}
/* 
Individual Styling, if Needed
.studentFrame {
  
}
.dateFrame {

}
.timeFrame {

} */
</style>
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
                <li><a href="../pages/blockForum.php" title="Forum"><?= dashboard_icon('forum') ?><span class="nav-label">Block Forum</span></a></li>
            </ul>
            <a href="../auth/logout.php" class="logout" title="Logout"><?= dashboard_icon('logout') ?><span class="nav-label">Logout</span></a>
        </nav>
    </aside>
    <a class="sidebarBackdrop" href="#" data-sidebar-close aria-label="Close navigation"></a>

    <div class="main">
        <header class="topbar">
            <div class="top-left">
                <button class="icon-btn sidebarToggle" type="button" aria-controls="dashboardSidebar" aria-expanded="false" aria-label="Open navigation"><?= dashboard_icon('menu', 18) ?></button>
                <span class="tab"><?= dashboard_icon('layout', 16) ?>Block Forum</span>
            </div>
            <div class="actions">
                <button class="icon-btn themeToggle" type="button" aria-label="Switch to dark mode" title="Switch theme"><?= dashboard_icon('moon', 18) ?></button>
            </div>
        </header>
<main class="content" id="dashboard">
<!------ Main content ------->
    <div class="contentFrame">
    <!-- Pager -->
    <div class="pagerFrame">
        <!-- Contex -->
        <h1>Sample Content</h1>
    </div>

    <!-- Message Stack -->
    <div class="messageFrame">
        <!--
        The div below is to be copied by the Backend
        and filled with queried content. 
        -->
        <div class="messageContent"> 
            <!-- Where the users information is displayed -->
            <div class="messengerFrame">

                <div class="studentFrame">
                <div id="studentFrame" class="identifierFrame">
                    <p class="identifierTextStyle">Student:</p>
                </div>
                <div class="studentName">
                    <p class="messengerInfoText">Test Student</p>
                </div>
                </div>

                <div class="dateFrame">
                <div class="identifierFrame">
                    <p class="identifierTextStyle">Date:</p>
                </div>
                <div class="dateSent">
                    <p class="messengerInfoText">00/00/0000</p>
                </div>
                </div>

                <div class="timeFrame">
                <div class="identifierFrame">
                    <p class="identifierTextStyle">Time:</p>
                </div>
                <div class="timeSent">
                    <p class="messengerInfoText">00:00am</p>
                </div>
                </div>

            </div>

            <!-- Where the users message is displayed -->
            <div class="messengerMessage">
                <p id="messageText">Hard Coded Message Test</p>
            </div>
        </div>
        <div class="messageContent"> 
            <!-- Where the users information is displayed -->
            <div class="messengerFrame">

                <div class="studentFrame">
                <div id="studentFrame" class="identifierFrame">
                    <p class="identifierTextStyle">Student:</p>
                </div>
                <div class="studentName">
                    <p class="messengerInfoText">Test Student</p>
                </div>
                </div>

                <div class="dateFrame">
                <div class="identifierFrame">
                    <p class="identifierTextStyle">Date:</p>
                </div>
                <div class="dateSent">
                    <p class="messengerInfoText">00/00/0000</p>
                </div>
                </div>

                <div class="timeFrame">
                <div class="identifierFrame">
                    <p class="identifierTextStyle">Time:</p>
                </div>
                <div class="timeSent">
                    <p class="messengerInfoText">00:00am</p>
                </div>
                </div>

            </div>

            <!-- Where the users message is displayed -->
            <div class="messengerMessage">
                <p id="messageText">Hard Coded Message Test</p>
            </div>
        </div>
        <div class="messageContent"> 
            <!-- Where the users information is displayed -->
            <div class="messengerFrame">

                <div class="studentFrame">
                <div id="studentFrame" class="identifierFrame">
                    <p class="identifierTextStyle">Student:</p>
                </div>
                <div class="studentName">
                    <p class="messengerInfoText">Test Student</p>
                </div>
                </div>

                <div class="dateFrame">
                <div class="identifierFrame">
                    <p class="identifierTextStyle">Date:</p>
                </div>
                <div class="dateSent">
                    <p class="messengerInfoText">00/00/0000</p>
                </div>
                </div>

                <div class="timeFrame">
                <div class="identifierFrame">
                    <p class="identifierTextStyle">Time:</p>
                </div>
                <div class="timeSent">
                    <p class="messengerInfoText">00:00am</p>
                </div>
                </div>

            </div>

            <!-- Where the users message is displayed -->
            <div class="messengerMessage">
                <p id="messageText">Hard Coded Message Test</p>
            </div>
        </div>
        <div class="messageContent"> 
            <!-- Where the users information is displayed -->
            <div class="messengerFrame">

                <div class="studentFrame">
                <div id="studentFrame" class="identifierFrame">
                    <p class="identifierTextStyle">Student:</p>
                </div>
                <div class="studentName">
                    <p class="messengerInfoText">Test Student</p>
                </div>
                </div>

                <div class="dateFrame">
                <div class="identifierFrame">
                    <p class="identifierTextStyle">Date:</p>
                </div>
                <div class="dateSent">
                    <p class="messengerInfoText">00/00/0000</p>
                </div>
                </div>

                <div class="timeFrame">
                <div class="identifierFrame">
                    <p class="identifierTextStyle">Time:</p>
                </div>
                <div class="timeSent">
                    <p class="messengerInfoText">00:00am</p>
                </div>
                </div>

            </div>

            <!-- Where the users message is displayed -->
            <div class="messengerMessage">
                <p id="messageText">Hard Coded Message Test</p>
            </div>
        </div>
    </div>
    </div>
    </div>
    </div>
<script src="./assets/dashboard.js?v=6"></script>
</main>
</div>
</div>
</body>
</html>