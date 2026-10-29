<?php
session_start();
$name = $_SESSION['name'];
$classCode = $_SESSION['code'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>View Assignment | Admin</title>
  <link rel="stylesheet" href="../css/viewas.css">
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
      <p class="profile-name"><?= htmlspecialchars($name) ?></p>
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
        <!-- Initials: replace "AN" with the first letters of the admin name -->
        <span class="user-avatar" aria-hidden="true">AN</span>
        <span class="user-name"><?= htmlspecialchars($name) ?></span>
      </div>
    </header>

    <!-- Banner: subject name, class code, and the Back button -->
    <section class="banner">
      <div>
        <!-- Subject name: replace with the assignment's subject -->
        <h1>Data Structures and Algorithms</h1>
        <p>Class Code: <?= htmlspecialchars($classCode) ?></p>
      </div>

      <a href="AdminAssignments.php" class="btn-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="19" y1="12" x2="5" y2="12"/>
          <polyline points="12 19 5 12 12 5"/>
        </svg>
        Back
      </a>

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
           LEFT: ASSIGNMENT DETAILS
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
            <h2>Linked List Implementation</h2>
            <div class="meta-row">
              <span><strong>Posted:</strong> Oct 15, 2026, 11:26 AM</span>
              <span><strong>Due:</strong> <span class="due-value">Oct 28, 2026, 11:59 PM</span></span>
            </div>
          </div>

          <!-- Edit and Delete -->
          <div class="detail-actions">
            <form method="POST" class="inline-form">
              <button type="submit" name="editAssignment" class="btn btn-outline">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M12 20h9"/>
                  <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                </svg>
                Edit
              </button>
            </form>

            <!-- This only opens the confirmation box below (CSS only) -->
            <a href="#delete-modal" class="btn btn-danger-outline">
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
        </header>

        <div class="detail-body">

          <section class="section">
            <h3 class="section-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="16" x2="12" y2="12"/>
                <line x1="12" y1="8" x2="12.01" y2="8"/>
              </svg>
              Instructions
            </h3>
            <p class="description">Implement a singly linked list with insert, delete, and search operations, then test it with at least ten sample values. Kindly upload your answer in PDF format.</p>
          </section>

          <section class="section">
            <h3 class="section-title accent">Attachments</h3>

            <ul class="files">

              <!-- ======================================================
                   ATTACHMENTS FOREACH START  (one row per file)
                   Put your foreach line right here, for example:
                       foreach ($attachments as $file):
                   Replace the sample text:  file name, file type (PDF), size
                   Close it after the </li> below with:  endforeach;
                   ====================================================== -->
              <li class="file">
                <span class="file-type" aria-hidden="true">PDF</span>
                <span class="file-info">
                  <span class="file-name">Linked List Guide.pdf</span>
                  <span class="file-meta">PDF &middot; 1.2 MB</span>
                </span>
                <span class="file-actions">
                  <a href="#" class="icon-btn" aria-label="View Linked List Guide.pdf" title="View">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </svg>
                  </a>
                  <a href="#" class="icon-btn" aria-label="Download Linked List Guide.pdf" title="Download" download>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                      <polyline points="7 10 12 15 17 10"/>
                      <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                  </a>
                </span>
              </li>
              <!-- ATTACHMENTS FOREACH END  (put endforeach; here)
                   The two rows below are extra samples. Delete them when your foreach works. -->
              <li class="file">
                <span class="file-type" aria-hidden="true">ZIP</span>
                <span class="file-info">
                  <span class="file-name">Starter Code.zip</span>
                  <span class="file-meta">ZIP &middot; 340 KB</span>
                </span>
                <span class="file-actions">
                  <a href="#" class="icon-btn" aria-label="View Starter Code.zip" title="View">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </svg>
                  </a>
                  <a href="#" class="icon-btn" aria-label="Download Starter Code.zip" title="Download" download>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                      <polyline points="7 10 12 15 17 10"/>
                      <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                  </a>
                </span>
              </li>
              <li class="file">
                <span class="file-type" aria-hidden="true">DOCX</span>
                <span class="file-info">
                  <span class="file-name">Grading Rubric.docx</span>
                  <span class="file-meta">DOCX &middot; 86 KB</span>
                </span>
                <span class="file-actions">
                  <a href="#" class="icon-btn" aria-label="View Grading Rubric.docx" title="View">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </svg>
                  </a>
                  <a href="#" class="icon-btn" aria-label="Download Grading Rubric.docx" title="Download" download>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                      <polyline points="7 10 12 15 17 10"/>
                      <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                  </a>
                </span>
              </li>

            </ul>
          </section>

        </div>
      </article>


      <!-- ##########################################################
           RIGHT: STUDENT SUBMISSIONS (scrollable list)
           ########################################################## -->
      <aside class="box" aria-label="Student submissions">

        <div class="sub-head">
          <div class="sub-title-row">
            <h2>Submissions</h2>
            <!-- submitted / total students -->
            <span class="count-pill">14 / 35</span>
          </div>

          <!-- Progress bar: set the widths in % (on time / total and late / total) -->
          <div class="bar" role="img" aria-label="10 on time, 4 late, 21 pending">
            <span class="bar-ontime" style="width: 28.6%"></span>
            <span class="bar-late" style="width: 11.4%"></span>
          </div>

          <ul class="legend">
            <li><span class="dot dot-ontime"></span>10 On time</li>
            <li><span class="dot dot-late"></span>4 Late</li>
            <li><span class="dot dot-pending"></span>21 Pending</li>
          </ul>
        </div>

        <!-- tabindex lets keyboard users scroll this list -->
        <div class="students-scroll" tabindex="0" role="region" aria-label="Students who submitted">
          <ul class="students">

            <!-- ======================================================
                 STUDENTS FOREACH START  (one row per student)
                 Put your foreach line right here, for example:
                     foreach ($submissions as $student):
                 Replace the sample text:
                     initials, student name, submitted date/time
                 Status badge: if the submission was on time, use the
                 "status-ontime" version below; if it was late, use the
                 "status-late" version (see the second sample row).
                 Close it after the </li> below with:  endforeach;
                 ====================================================== -->
            <li class="student">
              <span class="student-avatar" aria-hidden="true">JD</span>
              <span class="student-info">
                <span class="student-name">Juan Dela Cruz</span>
                <span class="student-time">Submitted Oct 27, 2026, 9:41 PM</span>
              </span>
              <span class="status status-ontime">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Completed On Time
              </span>
            </li>
            <!-- STUDENTS FOREACH END  (put endforeach; here)
                 Everything below is sample data. Delete it when your foreach works. -->
            <li class="student">
              <span class="student-avatar" aria-hidden="true">MS</span>
              <span class="student-info">
                <span class="student-name">Maria Clara Santos</span>
                <span class="student-time">Submitted Oct 27, 2026, 8:15 PM</span>
              </span>
              <span class="status status-ontime">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Completed On Time
              </span>
            </li>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">JR</span>
              <span class="student-info">
                <span class="student-name">Jose Miguel Reyes</span>
                <span class="student-time">Submitted Oct 29, 2026, 12:20 AM</span>
              </span>
              <span class="status status-late">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Late Submission
              </span>
            </li>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">AV</span>
              <span class="student-info">
                <span class="student-name">Ana Beatriz Villanueva</span>
                <span class="student-time">Submitted Oct 26, 2026, 4:05 PM</span>
              </span>
              <span class="status status-ontime">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Completed On Time
              </span>
            </li>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">CM</span>
              <span class="student-info">
                <span class="student-name">Carlo Mendoza</span>
                <span class="student-time">Submitted Oct 28, 2026, 11:42 PM</span>
              </span>
              <span class="status status-ontime">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Completed On Time
              </span>
            </li>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">LF</span>
              <span class="student-info">
                <span class="student-name">Liza Fernandez</span>
                <span class="student-time">Submitted Oct 29, 2026, 8:10 AM</span>
              </span>
              <span class="status status-late">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Late Submission
              </span>
            </li>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">PG</span>
              <span class="student-info">
                <span class="student-name">Paolo Gonzales</span>
                <span class="student-time">Submitted Oct 27, 2026, 1:30 PM</span>
              </span>
              <span class="status status-ontime">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Completed On Time
              </span>
            </li>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">BR</span>
              <span class="student-info">
                <span class="student-name">Bianca Ramos</span>
                <span class="student-time">Submitted Oct 28, 2026, 10:05 PM</span>
              </span>
              <span class="status status-ontime">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Completed On Time
              </span>
            </li>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">RN</span>
              <span class="student-info">
                <span class="student-name">Rafael Navarro</span>
                <span class="student-time">Submitted Oct 28, 2026, 11:58 PM</span>
              </span>
              <span class="status status-ontime">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Completed On Time
              </span>
            </li>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">KA</span>
              <span class="student-info">
                <span class="student-name">Kristine Aquino</span>
                <span class="student-time">Submitted Oct 29, 2026, 2:45 PM</span>
              </span>
              <span class="status status-late">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Late Submission
              </span>
            </li>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">MT</span>
              <span class="student-info">
                <span class="student-name">Miguel Torres</span>
                <span class="student-time">Submitted Oct 25, 2026, 6:22 PM</span>
              </span>
              <span class="status status-ontime">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Completed On Time
              </span>
            </li>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">AB</span>
              <span class="student-info">
                <span class="student-name">Angelica Bautista</span>
                <span class="student-time">Submitted Oct 27, 2026, 7:50 PM</span>
              </span>
              <span class="status status-ontime">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Completed On Time
              </span>
            </li>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">DC</span>
              <span class="student-info">
                <span class="student-name">Daniel Castillo</span>
                <span class="student-time">Submitted Oct 28, 2026, 9:12 PM</span>
              </span>
              <span class="status status-ontime">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Completed On Time
              </span>
            </li>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">SM</span>
              <span class="student-info">
                <span class="student-name">Sofia Mercado</span>
                <span class="student-time">Submitted Oct 30, 2026, 9:00 AM</span>
              </span>
              <span class="status status-late">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Late Submission
              </span>
            </li>

          </ul>
        </div>
      </aside>

    </div>
  </main>

</div>


<!-- ================= Delete confirmation (opens with the Delete button, CSS only) ================= -->
<div class="modal" id="delete-modal" role="dialog" aria-modal="true" aria-labelledby="delete-title">
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

    <h2 id="delete-title">Delete this assignment?</h2>
    <p>&ldquo;Linked List Implementation&rdquo; and all student submissions will be permanently removed. This can&rsquo;t be undone.</p>

    <form method="POST" class="modal-actions">
      <a href="#!" class="btn btn-outline">Cancel</a>
      <button type="submit" name="deleteAssignment" class="btn btn-danger">Delete</button>
    </form>
  </div>
</div>

</body>
</html>