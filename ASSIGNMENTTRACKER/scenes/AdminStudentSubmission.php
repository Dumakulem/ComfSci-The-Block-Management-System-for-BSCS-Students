<?php
session_start();
require_once __DIR__ . '/../database/admin.php';
require_once __DIR__ . '/../database/student.php';
require_once __DIR__ . '/../controllers/AdminCon.php';

$name       = $_SESSION['name'];
$classCode  = $_SESSION['code'];

$student = getStudent($pdo, (int)$_SESSION['s']);

$assignment = getStudentAssignment($pdo, (int)$_SESSION['assignment'], $classCode, $student['studentID']);

$myFiles = getStudentFiles($pdo, (int) $student['studentID'], $assignment['as_title'], $classCode);

if(isset($_POST['backToAssignment'])){

header('Location: AdminViewAssignment.php');
exit;
}

if(isset($_POST['downloadFile'])){
downloadStudentFile($pdo, (int) $_POST['downloadFile'], $classCode);

header('Location: ' . $_SERVER['PHP_SELF']);   // prevents re-upload on refresh
exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>View Submission | Admin</title>
  <link rel="stylesheet" href="../css/submission.css">
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
      <!-- Admin name -->
      <p class="profile-name"><?= $name ?></p>
      <p class="profile-role">Administrator</p>
    </div>

    <div class="nav-panel">
      <nav class="nav" aria-label="Main">
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
        <!-- Initials and admin name -->
        <span class="user-avatar" aria-hidden="true">U</span>
        <span class="user-name"><?= $name ?></span>
      </div>
    </header>

    <!-- Banner: student name, class code, and the Back button -->
    <section class="banner">
      <div>
        <!-- Student name -->
        <h1><?= getStudentName($pdo, $classCode, $student['studentID']) ?></h1>
        <!-- Class code -->
        <p>Class Code: <?= $classCode ?></p>
      </div>

      <form method="POST" class="back-form">
        <button type="submit" name="backToAssignment" class="btn-back">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="19" y1="12" x2="5" y2="12"/>
            <polyline points="12 19 5 12 12 5"/>
          </svg>
          Back
        </button>
      </form>

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

    <div class="view-grid">

      <!-- ##########################################################
           LEFT: SUBMISSION (title, subject, status, dates, files)
           ########################################################## -->
      <article class="box">

        <header class="detail-head">
          <span class="detail-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="8" y="2" width="8" height="4" rx="1"/>
              <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
              <path d="M9 12h6"/>
              <path d="M9 16h4"/>
            </svg>
          </span>

          <div class="detail-titles">
            <!-- Assignment title -->
            <h2><?= $assignment['as_title'] ?></h2>
            <!-- Subject -->
            <p class="detail-subject"><strong>Subject:</strong> <?= $assignment['as_subject'] ?></p>
          </div>
        </header>

        <!-- Status, date submitted and due date -->
        <dl class="facts">
          <div class="fact">
            <dt>Status</dt>
            <dd>
             

            <?php if($assignment['as_status'] == "Completed"): ?>
              <span class="status status-ontime">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Completed On Time
              </span>
            <?php endif; ?>

            <?php if($assignment['as_status'] == "Late"): ?>
            <span class="status status-late">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Late Submission
                </span>
            <?php endif; ?>
            </dd>
          </div>

          <div class="fact">
            <dt>Submitted</dt>
            <!-- Date the student passed it -->
            <dd><?= (new DateTime($assignment['edited_at']))->format('M j, Y, g:i A') ?></dd>
          </div>

          <div class="fact">
            <dt>Due</dt>
            <!-- Deadline -->
            <dd class="due-value"><?= (new DateTime($assignment['as_deadline']))->format('M j, Y, g:i A') ?></dd>
          </div>
        </dl>

        <div class="detail-body">
          <h3 class="section-title">Submitted files</h3>

          <ul class="files">

            <!-- ======================================================
                 FILES FOREACH START  (one row per submitted file)
                 Put your foreach line right here. Replace the sample text:
                     PDF                  ->  file type
                     Linked List.pdf      ->  file name
                     PDF · 1.2 MB         ->  file type and size
                     FILE_ID_HERE         ->  the file's ID (for the download button)
                 Close it after the </li> below.
                 The second row is only a sample. Delete it when your foreach works.
                 ====================================================== -->
            <?php foreach($myFiles as $file): ?>
            <li class="file">
              <span class="file-type" aria-hidden="true"><?= strtoupper(pathinfo($file['file_name'], PATHINFO_EXTENSION)); ?></span>
              <span class="file-info">
                <span class="file-name"><?= $file['file_name'] ?></span>
                <span class="file-meta"><?= strtoupper(pathinfo($file['file_name'], PATHINFO_EXTENSION)); ?> &middot; <?= formatFileSize($file['file_size']) ?></span>
              </span>
              <span class="file-actions">
                <a href="#" class="icon-btn" aria-label="View Linked List.pdf" title="View">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                </a>
                <form method="POST" class="download-form">
                  <button type="submit" name="downloadFile" value="<?= $file['id'] ?>" class="icon-btn" aria-label="Download Linked List.pdf" title="Download">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                      <polyline points="7 10 12 15 17 10"/>
                      <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                  </button>
                </form>
              </span>
            </li>
            <?php endforeach; ?>
            <!-- FILES FOREACH END -->

          </ul>

          <!-- If the student submitted no files, remove the list above and show this instead:
          <p class="empty-text">No files were submitted.</p>
          -->
        </div>
      </article>


      <!-- ##########################################################
           RIGHT: FEEDBACK (score + comment sent to the student)
           ########################################################## -->
      <aside class="box" aria-label="Feedback">

        <header class="feedback-head">
          <h2>Feedback</h2>
          <!-- Student name -->
          <p>The score and comment are sent to <strong><?= getStudentName($pdo, $classCode, $student['studentID']) ?></strong>.</p>
        </header>

        <form method="POST" class="feedback-form" autocomplete="off">

          <!-- Score -->
          <div class="field">
            <label for="score">Score <span class="req" aria-hidden="true">*</span></label>
            <div class="score-input">
              <!-- Change max here and the "/ 100" label to the highest possible score -->
              <input type="number" id="score" name="score" min="0" max="100" step="any" placeholder="e.g. 90" required>
              <span class="score-max" aria-hidden="true">/ 100</span>
            </div>
          </div>

          <!-- Comment -->
          <div class="field">
            <label for="comment">Comment <span class="optional">(optional)</span></label>
            <textarea id="comment" name="comment" placeholder="Write a comment for the student." maxlength="500"></textarea>
            <p class="hint">Up to 500 characters.</p>
          </div>

          <button type="submit" name="sendFeedback" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="22" y1="2" x2="11" y2="13"/>
              <polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
            Send feedback
          </button>

        </form>
      </aside>

    </div>
  </main>

</div>
</body>
</html>