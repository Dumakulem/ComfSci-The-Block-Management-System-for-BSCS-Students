<?php

require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/../pages/renderers/dashboard_helpers.php';
require_once __DIR__ . '/blockForum/queryMessage.php';
require_once __DIR__ . '/blockForum/message.php';
require_once __DIR__ . '/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forumMessage'])) {
    require_once __DIR__ . '/../pages/blockForum/userQuery.php';
}

$userName = $_SESSION['user_name'] ?? $_SESSION['user_email'];
$userEmail = $_SESSION['user_email'];
$userType = $_SESSION['user_type'];

$pageTitle = 'Block Forum';
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
    <link rel="stylesheet" href="./assets/theme.css?v=1">
    <link rel="stylesheet" href="./assets/dashboard.css?v=9">
    <link rel="stylesheet" href="./assets/sidebar.css?v=1">
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
  padding: .5rem;
  gap: .5rem;
  border-radius: 16px;
}


/* Pager. Used for creation and submission of Tickets */


/*Do not Delete ".parentPagerFrame". Pager will break, under construction */
.parentPagerFrame{
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    width: 100%;
    height: 100%;
}

.pagerFrame {
  /* Layout */
  display: flex;
  flex-direction: row;
  width: 100%;
  height: 30%;
  align-items: center;
  background: black;
  border-radius: 16px;
  padding: .5rem;
  cursor: pointer;
}


/*Subject Frame. */
/*
.parentTicketFrame{
    width: 100%;
    height: 100%;
    padding: 0.2px;
}
*/
#ticketSubjectDisplay {
    width: 100%;
    height: 50%;
}

#ticketSubject {
    background-color: #FF8800;
    padding: 1rem 1rem 1rem 1rem; 
    border-radius: 18px;
    border: 0.5rem solid black;
    font-family: 'Courier New', Courier, monospace;
    font-style: monospace;
    font-weight: bold;
    color: #000;
    font-size: 1rem;
    text-align: left; 
    padding-left: 1rem; 
    width: 100%;
    height: 100%;
}

/* Message Frame 

*/

.ticketMessageDisplay {
    width: 100%;
    height: 100%;
}

#MessageDisplay {
    display: inherit;
    align-items: inherit;
    background-color: #b1ffa2;
    padding: 1rem 1rem 1rem 1rem; 
    border-radius: 18px;
    border: 0.5rem solid black;
    font-family: 'Courier New', Courier, monospace;
    font-style: monospace;
    font-weight: bold;
    color: #000;
    font-size: 1rem;
    text-align: left; 
    padding-left: 1rem; 
    width: 100%;
    height: 100%;
}

/*Button for Ticket Types*/

.pagerButtons {
  display: flex;
  flex-direction: column;
  width: 5%;
  height: 100%;
  gap: .5rem;
  align-items: center;
  align-self: center;
}

#buttonEmergency {
   display: flex;
   align-self: center;
   width: 100%;
   height: 100%;
   background: limegreen;
   border-radius: 16px;
   align-items: center;
   cursor: pointer;
    font-family: 'Courier New', Courier, monospace;
    font-style: monospace;
    font-weight: bold;
    color: #000;
    font-size: 1rem;
}

#buttonIllness {
   display: flex;
   align-self: center;
   width: 100%;
   height: 100%;
   background: limegreen;
   border-radius: 16px; 
   align-items: center;
   cursor: pointer;
    font-family: 'Courier New', Courier, monospace;
    font-style: monospace;
    font-weight: bold;
    color: #000;
    font-size: 1rem;
}

#buttonComplaint {
   display: flex;
   align-self: center;
   width: 100%;
   height: 100%;
   background: limegreen;
   border-radius: 16px; 
   align-items: center;
   cursor: pointer;
    font-family: 'Courier New', Courier, monospace;
    font-style: monospace;
    font-weight: bold;
    color: #000;
    font-size: 1rem;
}

/* Pager Ends Here */


#messageFrame {
    display: inherit;
    flex-direction: inherit;
    width: 100%;
    height: 70%;
    padding: 10px;
    gap: 1rem;
    border-radius: 16px;
    align-items: flex-start;
    background-color: black;
}

#greetingFrame {
    width: 100%;
    height: 10%;
    border-top-left-radius: 14px;
    border-top-right-radius: 14px;
    background: var(--bg, linear-gradient(174deg, #F76E10 -7.13%, #B24B28 44.24%, #682C0C 97.4%));
}

.pager {
  /* Layout */
  display: flex;
  align-self: center;
  width: 100%;
  height: 30%;
  align-items: center;
  background: #FF8800;
  border-top-left-radius: 15px; 
  border-top-right-radius: 15px; 
  border-bottom: .5rem solid #000 ;
}
    /* padding: 1rem 1rem 1rem 1rem; */

.pagerDisplay {
  /* Layout */
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 70%;
  padding: .5rem;
  align-items: center;
  align-self: center;
  background: #b1ffa2;
  border-bottom-left-radius: 15px;
  border-bottom-right-radius: 15px;
}

#NoteDisplay {
   /* Layout */
  font-family: 'Courier New', Courier, monospace;
  font-style: monospace;
  font-weight: bold;
  color: #000;
  font-size: 1.5rem;
  text-align: left; 
  padding-left: 1rem; 
}

#MessageDisplay {
   /* Layout */
  font-family: 'Courier New', Courier, monospace;
  font-style: monospace;
  font-weight: bold;
  color: #000;
  font-size: 1.5rem;
  text-align: left;
}
/*
.pagerButtons {
 
  display: flex;
  flex-direction: column;
  width: 6%;
  height: 100%;
  margin: .5rem;
  align-items: center;
  align-self: center;
}

.pagerButtonSend {
   display: flex;
   align-self: center;
   width: 100%;
   height: 60%;
   padding: .25rem;
   margin: .25rem;
   background: red;

   border-radius: 16px;
}

.pagerButtonDummy {
   display: flex;
   align-self: center;
   width: 100%;
   height: 60%;
   padding: .25rem;
   margin: .25rem;
   background: red;

   border-radius: 16px; 
}
*/


.messageStack {
  /* Layout */
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 80%;
  overflow-y: auto;
  align-items: flex-start;
  background: #000;
  gap: 1rem;
}

.messageContent {
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 100%;
  min-height: 50%;
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

#messageText, #inputField{
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

#messageField {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    background: var(--bg, linear-gradient(174deg, #F76E10 -7.13%, #B24B28 44.24%, #682C0C 97.4%));
    border-radius: 18px;
    width: 100%;
    height: 20%;
    padding: .5rem .5rem .5rem .5rem;
}
    

#inputForm {
    display: inherit;
    flex-direction: row;
    align-items: inherit;
    width: 95%;
    height: 100%;
}

#inputField {
    display: inherit;
    flex-direction: inherit;
    align-items: inherit;
    background-color: #b1ffa2;
    border-radius: 18px;
    border: 0.6rem solid black;
    width: 100%;
    height: 100%;
    padding: 0rem 1rem 0rem 1rem;
}

#InputButtons {

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
<body class="comfsci-shell">
<div class="app">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main">
        <header class="topbar">
            <div class="top-left">
                <span class="tab"><?= dashboard_icon('layout', 16) ?>Dashboard</span>
            </div>
            <div class="actions">
                <button class="icon-btn themeToggle" type="button" aria-label="Switch to dark mode" title="Switch theme">
                    <?= dashboard_icon('moon', 18) ?>
                </button>
               
            </div>
        </header>
        <main>
        <!------ Main content ------->
    <div class="contentFrame">
    <!-- Pager -->
    <div class="pagerFrame"> 
        <!-- Contex -->

       <div class="parentPagerFrame">
        
            <div id="ticketSubjectDisplay"> 
                <input id="ticketSubject" placeholder="Enter Ticket Subject Here."></input>
            </div>
            <div class="ticketMessageDisplay">
                <input id="MessageDisplay" placeholder="Enter Ticket Message Here."></input>
            </div>

        </div>
        
        <div class="pagerButtons">

            <div id="buttonEmergency"><button id="buttonEmergency">btn</button></div>

     

            <div id="buttonIllness"><button id="buttonIllness">btn</button></div>

    

            <div id="buttonComplaint"><button id="buttonComplaint">btn</button></div>

        </div>

    </div>

    <!-- Message Stack -->
<!-- Parent Frame --> <div id="messageFrame">

<!-- Greeting Card --> <div id="greetingFrame">

    <div>

    </div>

    </div>
    <div class="messageStack">
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
                <p id="messageText"><?= print_r($_SESSION); echo " Student Block: " . getCurrentStudentBlock() . " isClassRep: "; var_dump(checkIfClassRep())?></p>
            </div>
        </div>
        <?php 
        for ( $i = 0; $i <= 100; $i++) {
        }
        ?>
    <!-- End of messageStack --></div> 

    <div id="messageField">
       <form action="" method="post" id="inputForm"> 
        <input type="text" name="forumMessage" id="inputField" placeholder="Enter a message">
        <button type="submit">Send</button>
       </form>
        
       
    </div>

    <!-- End of messageField --></div>
    </div>
    </div>
    </div>
        </main>
        
    </div>
    <script src="./assets/dashboard.js?v=6"></script>
<?php require __DIR__ . '/../includes/ai_chat_widget.php'; ?>
</body>
</html>