<?php
session_start();
$name = $_SESSION['name'];
$classCode = $_SESSION['code'];

if(isset($_POST['cancel'])){
    header("Location: AdminAssignments.php");
    exit;
}

if(isset($_POST['createAssignment'])){
require_once __DIR__ . '/../controllers/AdminCon.php';

$title = $_POST['title'];
$subject = $_POST['subject'];
$files = $_FILES['files'] ?? null;
$des = $_POST['description'];
$dt = mergeDateAndTime($_POST['date'], $_POST['time']);
$forDatabase = $dt->format('Y-m-d H:i:s');   // "2026-11-20 23:59:00"

require_once __DIR__ . '/../database/admin.php';
createAssignment($pdo, $classCode, $name, $title, $subject, $files, $des, $forDatabase);

//$forDatabase = $dt->format('Y-m-d H:i:s');   // "2026-11-20 23:59:00"
//$forDisplay  = $dt->format('M j, Y, g:i A'); // "Nov 20, 2026, 11:59 PM"

    header("Location: AdminAssignments.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Assignment | Admin</title>
  <link rel="stylesheet" href="../css/createassignments.css">
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
      <p class="profile-name">Admin Name</p>
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

    <header class="topbar">
      <div class="page-label">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="8" y="2" width="8" height="4" rx="1"/>
          <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
          <path d="M9 12h6"/>
          <path d="M9 16h4"/>
        </svg>
        <span>Assignments</span>
      </div>

      <div class="user-chip">
        <!-- Initials and name: replace with  echo $adminName  -->
        <span class="user-avatar" aria-hidden="true">AN</span>
        <span class="user-name">Admin Name</span>
      </div>
    </header>

    <div class="form-wrap">

      <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="AdminAssignments.php">Assignments</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">New assignment</span>
      </nav>

      <section class="form-card">

        <div class="form-head">
          <span class="form-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="12" y1="18" x2="12" y2="12"/>
              <line x1="9" y1="15" x2="15" y2="15"/>
            </svg>
          </span>
          <div>
            <h1>Create new assignment</h1>
            <p>Fill in the details below to post an assignment.</p>
          </div>
        </div>

        <!-- enctype="multipart/form-data" is required for file uploads -->
        <form method="POST" autocomplete="off" enctype="multipart/form-data">

          <!-- Title -->
          <div class="field">
            <label for="title">Title <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="title" name="title" placeholder="e.g. Linked List Implementation" maxlength="100" required>
          </div>

          <!-- Subject -->
          <!-- Subject -->
          <div class="field">
               <label for="subject">Subject <span class="req" aria-hidden="true">*</span></label>
               <input type="text" id="subject" name="subject" placeholder="e.g. Data Structures and Algorithms" maxlength="100" required>
          </div>

          <!-- File (multiple files allowed, drag and drop works) -->
          <div class="field">
            <label for="files">File <span class="optional">(optional)</span></label>
            <input type="file" id="files" name="files">
            <p class="hint">Click to browse or drop files here. You can add more than one file.</p>
          </div>

          <!-- Description -->
          <div class="field">
            <label for="description">Description <span class="req" aria-hidden="true">*</span></label>
            <textarea id="description" name="description" placeholder="Write one short sentence about this assignment." maxlength="300" required></textarea>
            <p class="hint">Keep it to one sentence. Longer text is shortened with an ellipsis on the assignment card.</p>
          </div>

          <!-- Deadline (date picker + time) -->
          <div class="row-2">
            <div class="field">
              <label for="deadline_date">Deadline <span class="req" aria-hidden="true">*</span></label>
              <input type="date" id="deadline_date" name="date" required>
            </div>

            <div class="field">
              <label for="deadline_time">Time</label>
              <input type="time" id="deadline_time" name="time" value="23:59">
            </div>
          </div>

          <div class="form-actions">
            <button name="cancel" form="cancelForm" type="submit" class="btn btn-secondary">Cancel</button>
            <button type="submit" name="createAssignment" class="btn btn-primary">Create Assignment</button>
          </div>

        </form>

        <form method="POST" id="cancelForm"></form>
      </section>

    </div>
  </main>

</div>
</body>
</html>