<?php
session_start();
require_once __DIR__ . '/../database/student.php';
require_once __DIR__ . '/../database/admin.php';
require_once __DIR__ . '/../controllers/AdminCon.php';

$student = $_SESSION['student'];
$classcode = $_SESSION['asClassCode'];
(int) $currentStudentID = $_SESSION['currentStudent'];

$assigment = getStudentAssignment($pdo, (int) $_SESSION['assignment'], $classcode, (int)$currentStudentID);

// Turns $_FILES['files'] (array-of-arrays-by-field) into a clean list of files
function normalizeFiles(?array $f): array {
    $out = [];
    if (!$f || !isset($f['name']) || !is_array($f['name'])) return $out;

    foreach ($f['name'] as $i => $name) {
        if ($f['error'][$i] !== UPLOAD_ERR_OK) continue;   // skips empty pickers and failed uploads
        $out[] = [
            'name'     => basename($name),
            'tmp_name' => $f['tmp_name'][$i],
            'size'     => $f['size'][$i],
            'type'     => $f['type'][$i],
        ];
    }
    return $out;
}

if (isset($_POST['addFile'])) {
    $newFiles = array_slice(normalizeFiles($_FILES['files'] ?? null), 0, 10);

    if (!empty($newFiles)) {
        addStudentFiles($pdo, (int) $currentStudentID, $assigment['as_title'], $classcode, $newFiles, (int)$assigment['as_id']);
    }

    header('Location: ' . $_SERVER['PHP_SELF']);   // prevents re-upload on refresh
    exit;
}

$myFiles = getStudentFiles($pdo, (int) $currentStudentID, $assigment['as_title'], $classcode);

$hasFiles = false;

if(isset($_POST['backToAssignments'])){
    unset($_SESSION['student']);
    unset($_SESSION['asClassCode']);
    unset($_SESSION['assignment']);

    header('Location: StudentAssignments.php');
    exit;
}

if(isset($_POST['downloadFile'])){
downloadFile($pdo, (int) $_POST['downloadFile'], $classcode);

header('Location: ' . $_SERVER['PHP_SELF']);   // prevents re-upload on refresh
exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>View Assignment | Student</title>
  <link rel="stylesheet" href="../css/studentas.css">
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
      <!-- Student name -->
      <p class="profile-name"><?= $student['studentFirstName']; ?></p>
      <p class="profile-role">Student</p>
    </div>

    <div class="nav-panel">
      <nav class="nav" aria-label="Main">
        <a class="nav-link" href="StudentDashboard.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/>
          </svg>
          <span>Dashboard</span>
        </a>
        <a class="nav-link is-active" href="StudentAssignments.php" aria-current="page">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="8" y="2" width="8" height="4" rx="1"/>
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
            <path d="M9 12h6"/>
            <path d="M9 16h4"/>
          </svg>
          <span>Assignments</span>
        </a>
      </nav>

      <a class="nav-link logout" href="StudentLoginScene.php">
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
        <!-- Initials and student name -->
        <span class="user-avatar" aria-hidden="true">SN</span>
        <span class="user-name"><?= $student['studentFirstName']; ?></span>
      </div>
    </header>

    <!-- Banner: subject name, class code, and the Back button -->
    <section class="banner">
      <div>
        <!-- Subject name -->
        <h1><?= $assigment['as_subject']; ?></h1>
        <!-- Class code -->
        <p>Class Code: <?= $classcode; ?></p>
      </div>

      <form method="POST" class="back-form">
        <button type="submit" name="backToAssignments" class="btn-back">
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
           LEFT: YOUR WORK (status + files + Add a file)
           ########################################################## -->
      <section class="box" aria-label="Your work">

<header class="work-head">
  <h2>Your Work</h2>

  <?php if ($assigment['as_status'] == "Overdue"): ?>
    <span class="status status-overdue">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Overdue
    </span>
  <?php elseif ($assigment['as_status'] == "Late"): ?>
    <span class="status status-overdue">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Late
    </span>
  <?php elseif ($assigment['as_status'] == "Completed"): ?>
    <span class="status status-completed">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
      Completed
    </span>
  <?php else: ?>
    <span class="status status-pending">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Pending
    </span>
  <?php endif; ?>
</header>

        <form method="POST" enctype="multipart/form-data" class="upload-form" autocomplete="off">

  <div class="work-body">
    <section class="section">
      <h3 class="section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
        </svg>
        Files Uploaded
      </h3>

      <?php if (empty($myFiles)): ?>
        <p class="empty-text">No files attached yet.</p>
      <?php else: ?>
        <ul class="files">
          <?php foreach ($myFiles as $mf): ?>
            <li class="file">
              <span class="file-type" aria-hidden="true"><?= strtoupper(pathinfo($mf['file_name'], PATHINFO_EXTENSION)); ?></span>
              <span class="file-info">
                <span class="file-name"><?= htmlspecialchars($mf['file_name']); ?></span>
                <span class="file-meta"><?= strtoupper(pathinfo($mf['file_name'], PATHINFO_EXTENSION)); ?> &middot; <?= formatFileSize($mf['file_size']); ?></span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="section">
      <h3 class="section-title accent">Add files</h3>

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

      <p class="hint">Choose a file and another picker appears. Up to 10 files.</p>
    </section>
  </div>

  <footer class="work-foot">
    <button type="submit" name="addFile" class="btn-add">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="12" y1="5" x2="12" y2="19"/>
        <line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Add files
    </button>
  </footer>

</form>
      </section>


      <!-- ##########################################################
           RIGHT: ASSIGNMENT DETAILS
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
            <h2><?= $assigment['as_title']; ?></h2>
            <div class="meta-row">
              <!-- Posted and due dates -->
              <span><strong>Posted:</strong> <?=(new DateTime($assigment['created_at']))->format('M j, Y, g:i A') ?></span>
              <span><strong>Due:</strong> <span class="due-value"><?=(new DateTime($assigment['as_deadline']))->format('M j, Y, g:i A') ?></span></span>
            </div>
          </div>
        </header>

        <div class="detail-body">

          <section class="section">
            <h3 class="section-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="17" y1="10" x2="3" y2="10"/>
                <line x1="21" y1="6" x2="3" y2="6"/>
                <line x1="21" y1="14" x2="3" y2="14"/>
                <line x1="17" y1="18" x2="3" y2="18"/>
              </svg>
              Description
            </h3>
            <!-- The description can be any length. It grows the box, and the line breaks you type are kept.
                 Keep the text right after the opening tag and right before the closing tag. -->
            <p class="description"><?= $assigment['as_description']; ?>
            </p>
          </section>

          <section class="section">
            <h3 class="section-title accent">Attachments</h3>

            <?php $files = getAsFile($pdo, $assigment['as_title'], $classcode); ?>
            <?php $allHasFiles = false; ?>

            <?php foreach($files as $file): ?>
              <?php if($file['as_file_name'] != null):?>
                <?php $allHasFiles = true; ?>
              <ul class="files">
              <!-- One row per attachment from the teacher. Repeat this whole item for each file. -->
              <li class="file">
                <span class="file-type" aria-hidden="true"><?= strtoupper(pathinfo($file['as_file_name'], PATHINFO_EXTENSION)); ?></span>
                <span class="file-info">
                  <span class="file-name"><?= $file['as_file_name']; ?></span>
                  <span class="file-meta"><?= strtoupper(pathinfo($file['as_file_name'], PATHINFO_EXTENSION)); ?> &middot; <?= formatFileSize($file['as_file_size']) ?></span>
                </span>
                <span class="file-actions">
                  <a href="#" class="icon-btn" aria-label="View Review Questions.pdf" title="View">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </svg>
                  </a>
         <form method="POST" class="download-form">
             <button type="submit" name="downloadFile" value="<?= $file['sa_id'] ?>" class="icon-btn" aria-label="Download Review Questions.pdf" title="Download">
             <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
             <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
             <polyline points="7 10 12 15 17 10"/>
             <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
        </button>
       </form>
                </span>
              </li>
            </ul>

            <?php endif; ?>
            <?php endforeach; ?>
            <!-- If there are no attachments, remove the list above and show this instead:
            <p class="empty-text">No attachments.</p>
            -->
            <?php if($allHasFiles === false): ?>

              <p class="empty-text">No attachments.</p>

            <?php endif; ?>
          </section>

        </div>
      </article>

    </div>
  </main>

</div>
</body>

<script>
  (function () {
    var MAX_FILES = 10;
    var list = document.getElementById('fileList');

    var template = list.firstElementChild.cloneNode(true);
    template.querySelector('input').removeAttribute('id');

    function ensureEmptySlot() {
      var last = list.lastElementChild;
      var lastIsFilled = !last || last.classList.contains('is-filled');
      if (lastIsFilled && list.children.length < MAX_FILES) {
        list.appendChild(template.cloneNode(true));
      }
    }

    list.addEventListener('change', function (e) {
      if (!e.target.matches('input[type="file"]')) return;
      var slot = e.target.closest('.file-slot');

      if (e.target.files.length > 0) {
        slot.classList.add('is-filled');
      } else {
        slot.classList.remove('is-filled');
        if (slot !== list.lastElementChild) slot.remove();
      }
      ensureEmptySlot();
    });

    list.addEventListener('click', function (e) {
      var btn = e.target.closest('.file-remove');
      if (!btn) return;
      btn.closest('.file-slot').remove();
      ensureEmptySlot();
    });
  })();
</script>
</html>