<?php
session_start();
$name = $_SESSION['name'];
$classCode = $_SESSION['code'];

if(isset($_POST['cancel'])){
    header("Location: AdminGroupAssignments.php");
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
createGroupAssignment($pdo, $classCode, $name, $title, $subject, getAllStudents($pdo, $classCode), $files, $des, $forDatabase);

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
        <span class="user-name"><?= $name ?></span>
      </div>
    </header>

    <div class="form-wrap">

      <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="AdminGroupAssignments.php">Group Assignments</a>
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
            <h1>Create new group assignment</h1>
            <p>Fill in the details below to post an GROUP assignment.</p>
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

          <!-- Files (choose a file, then another picker appears) -->
<div class="field">
  <label for="files">Files <span class="optional">(optional)</span></label>

  <div class="file-list" id="fileList">
    <div class="file-slot">
      <input type="file" id="files" name="files[]">
      <button type="button" class="file-remove" aria-label="Remove this file">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18"/>
          <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
  </div>

  <p class="hint">Choose a file and another picker appears so you can add more. Up to 10 files.</p>
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

<script>
  (function () {
    var MAX_FILES = 10;
    var list = document.getElementById('fileList');

    // Copy of the empty row, used to create new rows
    var template = list.firstElementChild.cloneNode(true);
    template.querySelector('input').removeAttribute('id');

    // Always keep one empty picker at the end (until the limit is reached)
    function ensureEmptySlot() {
      var last = list.lastElementChild;
      var lastIsFilled = !last || last.classList.contains('is-filled');
      if (lastIsFilled && list.children.length < MAX_FILES) {
        list.appendChild(template.cloneNode(true));
      }
    }

    // A file was chosen (or cleared) in one of the pickers
    list.addEventListener('change', function (e) {
      if (!e.target.matches('input[type="file"]')) return;
      var slot = e.target.closest('.file-slot');

      if (e.target.files.length > 0) {
        slot.classList.add('is-filled');
      } else {
        slot.classList.remove('is-filled');
        // Chrome clears a picker if its dialog is cancelled, so drop that row
        if (slot !== list.lastElementChild) slot.remove();
      }
      ensureEmptySlot();
    });

    // Remove button
    list.addEventListener('click', function (e) {
      var btn = e.target.closest('.file-remove');
      if (!btn) return;
      btn.closest('.file-slot').remove();
      ensureEmptySlot();
    });
  })();
</script>

</body>
</html>