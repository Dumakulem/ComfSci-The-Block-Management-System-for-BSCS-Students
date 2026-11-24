<?php
session_start();
require_once __DIR__ . '/../database/admin.php';
require_once __DIR__ . '/../database/student.php';
require_once __DIR__ . '/../controllers/AdminCon.php';

$name       = $_SESSION['name'];
$classCode  = $_SESSION['code'];

$adminID = (int)getAdmin($pdo, $name)['adminID'];

$student = getStudent($pdo, getLeaderID($pdo, (int)$_SESSION['gID'])['studentID']);

$group = getYourGroup($pdo, (int)$_SESSION['gID']);

$members = getAllMembers($pdo, (int)$_SESSION['gID']);

$assignment = getStudentAssignment($pdo, (int)$_SESSION['currentGroupAsID'], $classCode, $student['studentID']);

$myFiles = getStudentFiles($pdo, (int) $student['studentID'], $assignment['as_title'], $classCode);

(int) $score = null;
$comment = null;
(int) $scoreOver = 100;

$hasFeedback = false;  

$feedbackID = null;// <-- added

if(isAdminHasFeedback($pdo, $assignment['sa_id']) == true){
  $hasFeedback = true;                                  // <-- added
  $f = getFeedback($pdo, $assignment['sa_id']);
  $feedbackID = (int)$f['feedback_id'];
  $score = $f['score'];
  $comment = $f['feedback'];
  $scoreOver = $f['score_over'];
}

if(isset($_POST['backToGroupAssignment'])){

header('Location: AdminViewGroupAssignment.php');
exit;
}

if(isset($_POST['downloadFile'])){
downloadStudentFile($pdo, (int) $_POST['downloadFile'], $classCode);

header('Location: ' . $_SERVER['PHP_SELF']);   // prevents re-upload on refresh
exit;
}

if(isset($_POST['sendFeedback'])){
  $c = $_POST['comment'];
  $s = (int)$_POST['score'];
  $ms = (int)$_POST['maxScore'];

  createFeedback($pdo, $assignment['sa_id'], $adminID, $c, $s, $ms);

  header('Location: ' . $_SERVER['PHP_SELF'] . '#!');   // '#!' replaces any leftover #delete-feedback-modal
  exit;
}
if(isset($_POST['deleteFeedback'])){
  deleteFeedback($pdo, $feedbackID);

  header('Location: ' . $_SERVER['PHP_SELF'] . '#!');   // this is where the stale hash used to survive
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
        <h1><?= $group['group_name'] ?></h1>
        <!-- Class code -->
        <p>Class Code: <?= $classCode ?></p>
      </div>

      <form method="POST" class="back-form">
        <button type="submit" name="backToGroupAssignment" class="btn-back">
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

            </article>

      <!-- ##########################################################
           GROUP MEMBERS (below the submission details)
           ########################################################## -->
      <section class="box group-card members-card" aria-label="Group members">
        <!-- The card is a <details> element, so the arrow works with no code.
             It starts open. Remove the word "open" to start it minimized. -->
        <details open>
          <summary class="group-head">
            <span class="group-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
            </span>

            <span class="group-text">
              <span class="group-name-row">
                <!-- Group name and number of members -->
                <span class="group-name"><?= $group['group_name'] ?></span>
              </span>
              <span class="group-assignment">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                  <polyline points="14 2 14 8 20 8"/>
                </svg>
                <!-- Assignment title -->
                <span><?= $assignment['as_title'] ?></span>
              </span>
              <span class="group-leader">
                <svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                <!-- Leader name -->
                <span>Leader: <strong><?= getStudentName($pdo, $classCode, $student['studentID']) ?></strong></span>
              </span>
            </span>

            <!-- The arrow -->
            <span class="group-toggle" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </span>
          </summary>

          <div class="group-body">
            <h3 class="members-title">Members</h3>

            <ul class="members">
              <li class="member is-leader">
                <span class="member-avatar" aria-hidden="true">U</span>
                <span class="member-name"><?= getStudentName($pdo, $classCode, $student['studentID']) ?></span>
                <span class="tag">Leader</span>
              </li>
              <?php foreach($members as $member): ?>
                <?php if($member['is_leader'] == 0): ?>
              <li class="member">
                <span class="member-avatar" aria-hidden="true">U</span>
                <span class="member-name"><?= getStudentName($pdo, $classCode, $member['studentID']) ?></span>
              </li>
              <?php endif; ?>
              <?php endforeach; ?>
              <!-- MEMBERS FOREACH END -->
            </ul>
          </div>
        </details>
      </section>

      <!-- RIGHT: FEEDBACK ... your existing <aside class="box" aria-label="Feedback"> follows here -->


      <!-- ##########################################################
           RIGHT: FEEDBACK (score + comment sent to the student)
           ########################################################## -->
      <aside class="box" aria-label="Feedback">

        <header class="feedback-head">
          <h2>Feedback</h2>
          <!-- Student name -->
          <p>The score and comment are sent to <strong><?= $group['group_name'] ?></strong>.</p>
                  <?php if($hasFeedback): ?>
        <div class="feedback-bar">
          <span class="feedback-bar-text">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
            Feedback sent
          </span>

          <!-- This only opens the confirmation box below (CSS only) -->
          <a href="#delete-feedback-modal" class="btn btn-danger-outline">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polyline points="3 6 5 6 21 6"/>
              <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
              <path d="M10 11v6"/>
              <path d="M14 11v6"/>
              <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
            </svg>
            Delete
          </a>
        </div>
        <?php endif; ?>
        </header>

        <form method="POST" class="feedback-form" autocomplete="off">

          <!-- Score -->
          <div class="field">
            <label for="score">Score <span class="req" aria-hidden="true">*</span></label>
            <div class="score-input">
              <!-- Change max here and the "/ 100" label to the highest possible score -->
              <input type="number" value="<?= $score ?>" id="score" name="score" min="0" max="100" step="any" placeholder="e.g. 90" required>
              <span class="score-max">/ <input type="number" id="maxScore" name="maxScore" value="<?= $scoreOver ?>" min="1" step="any" aria-label="Highest possible score" required></span>

            </div>
          </div>

          <!-- Comment -->
          <div class="field">
            <label for="comment">Comment <span class="optional">(optional)</span></label>
            <textarea id="comment" name="comment" placeholder="Write a comment for the group." maxlength="500"><?= $comment ?></textarea>
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

</div>

<?php if($hasFeedback): ?>
<!-- ================= Delete feedback confirmation (opens with the Delete button, CSS only) ================= -->
<div class="modal" id="delete-feedback-modal" role="dialog" aria-modal="true" aria-labelledby="delete-feedback-title">
  <a href="#!" class="modal-backdrop" aria-label="Close dialog"></a>

  <div class="modal-box">
    <div class="modal-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="3 6 5 6 21 6"/>
        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
        <path d="M10 11v6"/>
        <path d="M14 11v6"/>
        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
      </svg>
    </div>

    <h2 id="delete-feedback-title">Delete this feedback?</h2>
    <p>The score and comment for this submission will be permanently removed. This can&rsquo;t be undone.</p>

    <!-- deleteFeedback form (template). PHP receives $_POST['deleteFeedback'] -->
    <form method="POST" class="modal-actions">
      <a href="#!" class="btn btn-outline">Cancel</a>
      <button type="submit" name="deleteFeedback" class="btn btn-danger">Delete</button>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
  (function () {
    var score    = document.getElementById('score');
    var maxInput = document.getElementById('maxScore');

    // Keep the score box's limit equal to the number the admin typed after the "/"
    function sync() {
      var m = parseFloat(maxInput.value);
      if (m > 0) { score.max = m; } else { score.removeAttribute('max'); }
    }

    maxInput.addEventListener('input', sync);
    sync();
  })();
</script>

</body>
</html>