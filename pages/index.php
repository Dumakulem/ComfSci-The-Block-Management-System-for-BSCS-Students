<?php 
//put ur php code here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ComfySci</title>
    <style>
        /* Everything is sized in rem, and the root font size follows the
           viewport. Browser zoom changes the viewport size, so the whole
           layout scales with it and never overflows or needs scrolling.
           Reference design: 1440 x 1024 -> 16px. */
        html{
            font-size: clamp(5px, min(calc(100vw / 90), calc(100vh / 64)), 24px);
        }

        :root{
            --accent: #ff6a00;
            --accent-glow: rgba(255, 106, 0, 0.35);
            --sidebar-w: 15.6rem;
            --header-h: 4.4rem;
        }

        *{ box-sizing: border-box; }

        html, body{
            margin: 0;
            height: 100%;
            overflow: hidden;
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
            background: linear-gradient(180deg, #e8681a 0%, #7a3511 100%);
        }
        body{ font-size: 1rem; }

        /* ---------- Icons ---------- */
        .icon{
            display: inline-flex;
            flex: 0 0 auto;
            width: 1.2rem;
            height: 1.2rem;
        }
        .icon img{
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: filter 0.25s ease;
        }

        /* ---------- Layout ---------- */
        .mainContainer{
            display: flex;
            height: 100%;
            width: 100%;
        }

        /* ---------- Sidebar ---------- */
        .sidebar{
            width: var(--sidebar-w);
            flex: 0 0 var(--sidebar-w);
            height: 100%;
            background: #000;
            color: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .userAvatar{
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 2.2rem 0.75rem 1.4rem;
        }
        .userAvatar .avatarImg{
            height: 7.5rem;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            display: block;
        }
        .userAvatar .userName{
            margin: 0.4rem 0 0;
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.1;
        }
        .userAvatar .studentNo{
            margin: 0.15rem 0 0;
            font-size: 0.78rem;
        }

        .navButtons{
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: #fff;
            color: #000;
            margin: 0 0.6rem 0.6rem;
            padding: 0.6rem;
            border-radius: 1rem;
        }

        .navItem{
            display: flex;
            align-items: center;
            gap: 0.9rem;
            padding: 0.75rem 0.9rem;
            border-radius: 0.9rem;
            color: #000;
            font-size: 0.95rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.25s ease, color 0.25s ease, box-shadow 0.25s ease;
        }
        .navItem .label{ margin: 0; }

        .navItem:hover,
        .navItem.active{
            background-color: var(--accent);
            color: #fff;
            box-shadow: 0 0.5rem 1.2rem var(--accent-glow);
        }
        .navItem:hover .icon img,
        .navItem.active .icon img{
            filter: brightness(0) invert(1);
        }

        .navItem.logout{ color: var(--accent); }
        .navItem.logout:hover{ color: #fff; }

        /* ---------- Content area ---------- */
        .content{
            flex: 1;
            min-width: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .topBar{
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 0.9rem;
            height: var(--header-h);
            padding: 0 1.2rem;
            background: #fff;
            border-bottom: 2px solid var(--accent);
        }
        .topBar h1{
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .searchBox{
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            height: 2.8rem;
            padding: 0 0.9rem;
            border: 1.5px solid var(--accent);
            border-radius: 0.75rem;
            color: #444;
        }
        .searchBox input{
            flex: 1;
            min-width: 0;
            border: none;
            outline: none;
            background: transparent;
            font: inherit;
            font-size: 0.95rem;
        }

        .iconBtn{
            position: relative;
            flex: 0 0 auto;
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            color: #333;
            border: 1.5px solid var(--accent);
            border-radius: 0.75rem;
            cursor: pointer;
            transition: background-color 0.25s ease, color 0.25s ease;
        }
        .iconBtn:hover{
            background: var(--accent);
            color: #fff;
        }
        .iconBtn:hover .icon img{ filter: brightness(0) invert(1); }
        .iconBtn .dot{
            position: absolute;
            top: 0.5rem;
            right: 0.6rem;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background: var(--accent);
        }

        .pageBody{
            flex: 1;
            min-height: 0;
            overflow: hidden;
            padding: 1.2rem;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="mainContainer">

        <!------ Sidebar ------->
        <aside class="sidebar">
            <div class="userAvatar">
                <img class="avatarImg" src="./assets/profileImage.png" alt="Profile picture">
                <p class="userName">Pogi</p>
                <p class="studentNo">Student Number: 676767</p>
            </div>

            <nav class="navButtons">
                <a class="navItem active" href="#dashboard">
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
                <a class="navItem logout" href="#logout">
                    <span class="icon"><img src="./assets/logoutIcon.png" alt=""></span>
                    <span class="label">Logout</span>
                </a>
            </nav>
        </aside>

        <!------ Main content ------->
        <div class="content">
            <header class="topBar">
                <h1>Dashboard</h1>

                <label class="searchBox">
                    <span class="icon"><img src="./assets/searchIcon.png" alt=""></span>
                    <input type="search" placeholder="Search...">
                </label>

                <button class="iconBtn" aria-label="Settings">
                    <span class="icon"><img src="./assets/settingsIcon.png" alt=""></span>
                </button>

                <button class="iconBtn" aria-label="Notifications">
                    <span class="icon"><img src="./assets/notificationIcon.png" alt=""></span>
                    <span class="dot"></span>
                </button>
            </header>

            <main class="pageBody">
                <p>Main Content Goes Here</p>
            </main>
        </div>

    </div>
</body>
</html>