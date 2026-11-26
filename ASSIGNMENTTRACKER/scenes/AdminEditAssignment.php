<?php
session_start();

// Load the functions first so $pdo, getAssignment(), getAsFile() and updateAssignment() exist
require_once __DIR__ . '/../database/admin.php';
require_once __DIR__ . '/../controllers/AdminCon.php';

$name      = $_SESSION['name'] ?? '';
$classCode = $_SESSION['code'] ?? null;
$asId      = (int) ($_SESSION['assignment'] ?? 0);

// Small helpers (skip if you already have them in AdminCon.php)
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('formatBytes')) {
    function formatBytes($b): string {
        $b = (int) $b;
        if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
        if ($b >= 1024)    return round($b / 1024) . ' KB';
        return $b . ' B';
    }
}

if (isset($_POST['cancel'])) {
    header("Location: AdminViewAssignment.php");
    exit;
}

if (!$classCode || !$asId) {
    header("Location: AdminViewAssignment.php");
    exit;
}

// The assignment the admin is currently on
$assignment = getAssignment($pdo, $asId, $classCode);
if (!$assignment) {
    header("Location: AdminViewAssignment.php");
    exit;
}

$error = null;

if (isset($_POST['editAssignment'])) {
    try {
        $title   = trim($_POST['title'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $des     = trim($_POST['description'] ?? '');
        $files   = $_FILES['files'] ?? null;                  // newly added files
        $remove  = $_POST['remove_files'] ?? [];              // sa_id of files to delete

        $dt          = mergeDateAndTime($_POST['date'], $_POST['time']);
        $forDatabase = $dt->format('Y-m-d H:i:s');            // "2026-11-20 23:59:00"

        updateAssignment($pdo, $asId, $classCode, $title, $subject, $des, $forDatabase, $files, (array) $remove);

        header("Location: AdminViewAssignment.php");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Values shown in the form: what was just submitted (if saving failed), otherwise what is in the database
$deadline  = new DateTime($assignment['as_deadline']);
$valTitle  = $_POST['title']       ?? $assignment['as_title'];
$valSubj   = $_POST['subject']     ?? $assignment['as_subject'];
$valDesc   = $_POST['description'] ?? $assignment['as_description'];
$valDate   = $_POST['date']        ?? $deadline->format('Y-m-d');
$valTime   = $_POST['time']        ?? $deadline->format('H:i');

// Files already saved for this assignment (always read from the database)
$existingFiles = getAsFile($pdo, $assignment['as_title'], $classCode);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Assignment | Admin</title>
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
      <p class="profile-name"><?= h($name) ?></p>
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
        <span class="user-avatar" aria-hidden="true"><?= h(strtoupper(substr($name, 0, 2))) ?></span>
        <span class="user-name"><?= h($name) ?></span>
      </div>
    </header>

    <div class="form-wrap">

      <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="AdminAssignments.php">Assignments</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Edit assignment</span>
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
            <h1>Edit assignment</h1>
            <p>Update the details below. Students will see the changes right away.</p>
          </div>
        </div>

        <?php if ($error): ?>
          <div class="form-error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <!-- enctype="multipart/form-data" is required for file uploads -->
        <form method="POST" autocomplete="off" enctype="multipart/form-data">

          <!-- Title -->
          <div class="field">
            <label for="title">Title <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="title" name="title" placeholder="e.g. Linked List Implementation" maxlength="100" value="<?= h($valTitle) ?>" required>
          </div>

          <!-- Subject -->
          <div class="field">
            <label for="subject">Subject <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="subject" name="subject" placeholder="e.g. Data Structures and Algorithms" maxlength="100" value="<?= h($valSubj) ?>" required>
          </div>

          <!-- Files -->
          <div class="field">
            <label for="files">Files <span class="optional">(optional)</span></label>

            <!-- Files already attached. Remove marks a file for deletion; it is only deleted when you press Save changes. -->
            <?php if (!empty($existingFiles)): ?>
            <div class="existing-files" id="existingFiles">
              <?php foreach ($existingFiles as $f): ?>
                <div class="existing-file">
                  <input type="hidden" name="remove_files[]" value="<?= (int) $f['sa_id'] ?>" disabled>
                  <svg class="existing-file-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                  </svg>
                  <span class="existing-file-name"><?= h($f['as_file_name']) ?></span>
                  <span class="existing-file-size"><?= h(formatBytes($f['as_file_size'])) ?></span>
                  <button type="button" class="file-remove" aria-label="Remove <?= h($f['as_file_name']) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <line x1="18" y1="6" x2="6" y2="18"/>
                      <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                    <span class="undo-text">Undo</span>
                  </button>
                </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- New files (choose a file, then another picker appears) -->
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

            <p class="hint">Remove a file with the X, or choose a new file to add one. Up to 10 files in total.</p>
          </div>

          <!-- Description -->
          <div class="field">
            <label for="description">Description <span class="req" aria-hidden="true">*</span></label>
            <textarea id="description" name="description" placeholder="Write one short sentence about this assignment." maxlength="300" required><?= h($valDesc) ?></textarea>
            <p class="hint">Keep it to one sentence. Longer text is shortened with an ellipsis on the assignment card.</p>
          </div>

          <!-- Deadline (date picker + time) -->
          <div class="row-2">
            <div class="field">
              <label for="deadline_date">Deadline <span class="req" aria-hidden="true">*</span></label>
              <input type="date" id="deadline_date" name="date" value="<?= h($valDate) ?>" required>
            </div>

            <div class="field">
              <label for="deadline_time">Time</label>
              <input type="time" id="deadline_time" name="time" value="<?= h($valTime) ?>">
            </div>
          </div>

          <div class="form-actions">
            <button name="cancel" form="cancelForm" type="submit" class="btn btn-secondary" formnovalidate>Cancel</button>
            <button type="submit" name="editAssignment" class="btn btn-primary">Save changes</button>
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
    var list     = document.getElementById('fileList');
    var existing = document.getElementById('existingFiles');   // null when there are no saved files

    // Copy of the empty row, used to create new rows
    var template = list.firstElementChild.cloneNode(true);
    template.querySelector('input').removeAttribute('id');

    function keptExisting() {
      return existing ? existing.querySelectorAll('.existing-file:not(.is-removed)').length : 0;
    }
    function filledNew() {
      return list.querySelectorAll('.file-slot.is-filled').length;
    }

    // Keep exactly one empty picker at the end while there is still room under the limit
    function ensureEmptySlot() {
      var last        = list.lastElementChild;
      var lastIsEmpty = last && !last.classList.contains('is-filled');
      var hasRoom     = keptExisting() + filledNew() < MAX_FILES;

      if (hasRoom && !lastIsEmpty) list.appendChild(template.cloneNode(true));
      if (!hasRoom && lastIsEmpty) last.remove();
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

    // Remove button on a new file picker
    list.addEventListener('click', function (e) {
      var btn = e.target.closest('.file-remove');
      if (!btn) return;
      btn.closest('.file-slot').remove();
      ensureEmptySlot();
    });

    // Remove / Undo on a file that is already saved
    if (existing) {
      existing.addEventListener('click', function (e) {
        var btn = e.target.closest('.file-remove');
        if (!btn) return;

        var row      = btn.closest('.existing-file');
        var hidden   = row.querySelector('input[name="remove_files[]"]');
        var removing = !row.classList.contains('is-removed');

        // Undo is blocked when bringing the file back would go over the limit
        if (!removing && keptExisting() + filledNew() >= MAX_FILES) {
          alert('You can only have up to ' + MAX_FILES + ' files. Remove another file first.');
          return;
        }

        row.classList.toggle('is-removed', removing);
        hidden.disabled = !removing;     // only enabled inputs are submitted
        btn.setAttribute('aria-label', (removing ? 'Keep ' : 'Remove ') + row.querySelector('.existing-file-name').textContent);
        ensureEmptySlot();
      });
    }

    // If the saved files already fill the limit, no empty picker is needed
    ensureEmptySlot();
  })();
</script>

</body>
</html>