<?php

require_once __DIR__ . '/../auth/require_auth.php';

$userName = $_SESSION['user_name'] ?? $_SESSION['user_email'];
$userEmail = $_SESSION['user_email'];
$userType = $_SESSION['user_type'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Block Forum</title>
    <link rel="stylesheet" href="../pages/blockForum/forumStyle.css">
</head>
<body>
<div class="mainContainer">

<!------ Sidebar ------->
<aside class="sidebar">
    <div class="userAvatar">
        <img class="avatarImg" src="./assets/profileImage.png" alt="Profile picture">
        <p class="userName"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="studentNo"><?= htmlspecialchars($userType, ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <nav class="navButtons">
        <a class="navItem" href="./index.php">
            <span class="icon"><img src="./assets/dashboardIcon.png" alt=""></span>
            <span class="label">Dashboard</span>
        </a>
        <a class="navItem" href="#profile">
            <span class="icon"><img src="./assets/profileIcon.png" alt=""></span>
            <span class="label">Profile</span>
        </a>
        <a class="navItem" href="#attendance">
            <span class="icon"><img src="./assets/attendanceIcon.png" alt=""></span>
            <span class="label">Attendance</span>   
        </a>
<!-- Block Forum Here! -->
        <a class="navItem active" href="./blockForum.php">
            <span class="icon"><img src="./assets/blockForumIcon.png" alt=""></span>
            <span class="label">Block Forum</span>   
        </a>
<!-- Block Forum <a> End -->
        <a class="navItem" href="#gamifiedQuiz">
            <span class="icon"><img src="./assets/gamifiedIcon.png" alt=""></span>
            <span class="label">Gamified Quiz</span>
        </a>
        <a class="navItem" href="#assignments">
            <span class="icon"><img src="./assets/assignmentIcon.png" alt=""></span>
            <span class="label">Assignments</span>
        </a>
        <a class="navItem" href="#announcement">
            <span class="icon"><img src="./assets/announcementIcon.png" alt=""></span>
            <span class="label">Announcement</span>
        </a>
        <a class="navItem" href="#academicCalendar">
            <span class="icon"><img src="./assets/academicCalendarIcon.png" alt=""></span>
            <span class="label">Academic Calendar</span>
        </a>
        <a class="navItem" href="#moduleSources">
            <span class="icon"><img src="./assets/moduleIcon.png" alt=""></span>
            <span class="label">Module Sources</span>
        </a>
        <a class="navItem logout" href="../auth/logout.php">
            <span class="icon"><img src="./assets/logoutIcon.png" alt=""></span>
            <span class="label">Logout</span>
        </a>
    </nav>
</aside>

<!------ Main content ------->
<div class="content">
<header class="topBar">
<h1>Block Forum</h1>

<label class="searchBox">
    <span class="icon"><img src="./assets/searchIcon.png" alt=""></span>
    <input type="search" placeholder="Search...">
</label>

<button class="iconBtn" aria-label="Settings">
    <span class="icon"><img src="./assets/settingsIcon.png" alt=""></span>
</button>

<button class="iconBtn" aria-label="Notifications">
    <span class="icon"><img src="./assets/notificationsIcon.png" alt=""></span>
    <span class="dot"></span>
</button>
</header>

<main class="pageBody">
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
    </div>
</div>
</main>
</div>
</div>
</body>
</html>