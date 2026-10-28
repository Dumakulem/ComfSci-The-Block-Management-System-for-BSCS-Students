<?php
session_start();
$name = $_SESSION['name'];
$classCode = $_SESSION['code'];

require_once __DIR__ . '/../database/admin.php';

if(isset($_POST['toGroupAssignments'])){
    header('Location: AdminGroupAssignments.php');
    exit;
}

if(isset($_POST['createAssignment'])){
    header('Location: AdminCreateAssignment.php');
    exit;
}
//next = find a way to have two session, so when entered a class, you have the admin name
//and the class code, after this, do the assignments first..
//also, when enter button is clicked, get its value and make sure it passes the class code

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Assignments | Admin</title>
  <link rel="stylesheet" href="../css/assignments.css">
</head>
<body>
<div class="shell">

  <!-- ================= Sidebar ================= -->
  <aside class="sidebar">
    <div class="profile">
      <div class="profile-avatar" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
      </div>
      <!-- Admin name: replace "Admin Name" with  echo $adminName  -->
      <p class="profile-name"><?= $name ?></p>
      <p class="profile-role">Administrator</p>
    </div>

    <div class="nav-panel">
      <nav class="nav" aria-label="Main">
        <a class="nav-link" href="AdminClassCodes.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/>
          </svg>
          <span>Class Codes</span>
        </a>
        <a class="nav-link is-active" href="AdminAssignments.php" aria-current="page">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="8" y="2" width="8" height="4" rx="1"/>
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
            <path d="M9 12h6"/>
            <path d="M9 16h4"/>
          </svg>
          <span>Assignments</span>
        </a>
      </nav>

      <a class="nav-link logout" href="AdminLoginScene.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        <span>Logout</span>
      </a>
    </div>
  </aside>

  <!-- ================= Main ================= -->
  <main class="main">

    <!-- The two radio buttons below power the "Assignments" / "Group Assignments"
         buttons with CSS only. Keep them as the FIRST items inside <main>. -->
    <input type="radio" name="tab" id="tab-assignments" class="tab-radio" checked>
    <input type="radio" name="tab" id="tab-group" class="tab-radio">

    <header class="topbar">
      <div class="page-label">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="8" y="2" width="8" height="4" rx="1"/>
          <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
          <path d="M9 12h6"/>
          <path d="M9 16h4"/>
        </svg>
        <span>Class Code: <?= $classCode; ?></span>
      </div>

      <div class="user-chip">
        <!-- Initials and name: replace with  echo $adminName  (initials can stay "AN" for now) -->
        <span class="user-avatar" aria-hidden="true">AN</span>
        <span class="user-name"><?= $name ?></span>
      </div>
    </header>

    <!-- Banner with the two buttons -->
    <section class="banner">
      <div class="banner-titles">
        <div class="title title-assignments">
          <h1>Assignments</h1>
          <p>List of posted assignments and their deadlines</p>
        </div>
      </div>

      <div class="tabs">
        <button class="tab-btn" for="tab-assignments">Assignments</button>
        <form method="POST">
            <button name="toGroupAssignments" type="submit" class="tab-btn" for="tab-group">Group Assignments</button>
        </form>
      </div>

      <svg class="banner-art" viewBox="0 0 220 160" fill="none" aria-hidden="true">
        <rect x="50" y="14" width="120" height="136" rx="14" stroke="#fff" stroke-opacity=".3" stroke-width="2"/>
        <rect x="82" y="6" width="56" height="20" rx="8" fill="#ff6a00"/>
        <circle cx="78" cy="58" r="8" stroke="#ff6a00" stroke-width="2.5"/>
        <polyline points="74 58 77 61 82 55" stroke="#ff6a00" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        <line x1="98" y1="58" x2="148" y2="58" stroke="#fff" stroke-opacity=".4" stroke-width="3" stroke-linecap="round"/>
        <circle cx="78" cy="88" r="8" stroke="#ff6a00" stroke-width="2.5"/>
        <polyline points="74 88 77 91 82 85" stroke="#ff6a00" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        <line x1="98" y1="88" x2="138" y2="88" stroke="#fff" stroke-opacity=".4" stroke-width="3" stroke-linecap="round"/>
        <circle cx="78" cy="118" r="8" stroke="#fff" stroke-opacity=".4" stroke-width="2.5"/>
        <line x1="98" y1="118" x2="144" y2="118" stroke="#fff" stroke-opacity=".4" stroke-width="3" stroke-linecap="round"/>
      </svg>
    </section>


    <!-- ##########################################################
         PANEL 1: ASSIGNMENTS  (shown when "Assignments" is selected)
         ########################################################## -->
    <section class="grid panel panel-assignments" aria-label="Assignments">

      <!-- ==========================================================
           OUTER FOREACH START  (one card per class)
           Put your foreach line right here, for example:
               foreach ($assignments as $class):
           Everything from here down to "OUTER FOREACH END" repeats once per class.

           Replace the sample text inside the card like this:
               10003 - CS201A    ->  echo $class['code']
               BSCS 2B           ->  echo $class['block']
               Assignments (2)   ->  echo count($class['tasks'])
               Enter button href ->  echo $class['url']
           ========================================================== -->
      <article class="card">
        <header class="card-head">
          <h2 class="card-title">Data Structures and Algorithms</h2>
          <span class="block-pill">CS201A</span>
        </header>
        <div class="card-body">

          <!-- ----------------------------------------------------
               INNER FOREACH START  (one row per assignment)
               Put your foreach line here, for example:
                   foreach ($class['tasks'] as $task):
               Replace the sample text like this:
                   title        ->  echo $task['title']
                   description  ->  echo $task['description']   (one sentence; long text becomes "...")
                   posted date  ->  echo $task['posted']
                   deadline     ->  echo $task['deadline']
                   href         ->  echo $task['url']
               ---------------------------------------------------- -->
          <a class="task" href="#">
            <span class="task-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="9" y1="13" x2="15" y2="13"/>
                <line x1="9" y1="17" x2="13" y2="17"/>
              </svg>
            </span>
            <span class="task-text">
              <span class="task-title-row">
                <span class="task-title">Linked List Implementation</span>
              </span>
              <span class="task-desc" title="Implement a singly linked list with insert, delete, and search operations, then test it with at least ten sample values.">Implement a singly linked list with insert, delete, and search operations, then test it with at least ten sample values.</span>
              <span class="task-meta">
                <span>Posted: Oct 15, 2026</span>
                <span class="due">Due: Oct 28, 2026, 11:59 PM</span>
              </span>
            </span>
            <svg class="task-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </a>
          <!-- INNER FOREACH END  (put endforeach; here)
               Tip: the row below is only a second sample so you can see the divider line.
               Delete it once your foreach is in place. -->
          <!-- <a class="task" href="#">
            <span class="task-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="9" y1="13" x2="15" y2="13"/>
                <line x1="9" y1="17" x2="13" y2="17"/>
              </svg>
            </span>
            <span class="task-text">
              <span class="task-title-row">
                <span class="task-title">Stack and Queue Reviewer</span>
              </span>
              <span class="task-desc" title="Complete the reviewer before the quiz next week.">Complete the reviewer before the quiz next week.</span>
              <span class="task-meta">
                <span>Posted: Oct 17, 2026</span>
                <span class="due">Due: Oct 30, 2026, 8:00 AM</span>
              </span>
            </span>
            <svg class="task-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </a> -->

        </div>

        <footer class="card-foot">
          <span class="count">Assignments (2)</span>
          <a href="#" class="btn-enter" aria-label="Enter 10003 - CS201A">Enter</a>
        </footer>
      </article>
      <!-- OUTER FOREACH END  (put endforeach; here) -->


      <!-- ==========================================================
           EMPTY CARD EXAMPLE (sample only)
           This is how a class with NO assignments looks.
           In your loop, show this block INSTEAD of the task rows when the
           class has no tasks, for example:
               if (empty($class['tasks'])):   ...this block...   else:   ...task foreach...   endif;
           Then delete this static card.
           ========================================================== -->
      <article class="card">
        <header class="card-head">
          <h2 class="card-title">Arts Appreciation</h2>
          <span class="block-pill">CS301A</span>
        </header>

        <div class="card-body">
          <p class="empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="8 12 11 15 16 9"/>
            </svg>
            No pending tasks!
          </p>
        </div>

        <footer class="card-foot">
          <span class="count">Assignments (0)</span>
          <a href="#" class="btn-enter" aria-label="Enter 10005 - CS301A">Enter</a>
        </footer>
      </article>


      <!-- "+" card: keep it OUTSIDE the foreach so it always stays after the last card -->
      <form method="POST" class="add-form">
  <button type="submit" name="createAssignment" value="assignment" class="card card-add" aria-label="Add assignment">
    <span class="plus" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="5" x2="12" y2="19"/>
        <line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
    </span>
    <span>Add assignment</span>
  </button>
</form>

    </section>

  </main>

</div>
</body>
</html>