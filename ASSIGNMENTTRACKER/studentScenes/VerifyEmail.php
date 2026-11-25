<?php
session_start();
require_once __DIR__ . '/../database/student.php';
require_once __DIR__ . '/../database/admin.php';
require_once __DIR__ . '/../includes/mailer.php';

$student = getStudent($pdo, (int) $_SESSION['currentStudent']);

$name = $student['studentFirstName'];
$classCode = $student['classCode'];

if(isset($_POST['verifyEmail'])){
    $ok = sendMail('baltazarjaydenross@gmail.com',
            'Test from my PHP app',
            '<h2>It works!</h2><p>This email was sent through Brevo.</p>');
            
 header('Location: ' . $_SERVER['PHP_SELF'] . '#!');   // '#!' replaces any leftover #delete-feedback-modal
 exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Email Verification | Student</title>
  <link rel="stylesheet" href="../css/assignments.css">
  <link rel="stylesheet" href="../css/emailverification.css">
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
      
      <p class="profile-name"><?= $name ?></p>
      <p class="profile-role">Student</p>
    </div>

    <div class="nav-panel">
      <nav class="nav" aria-label="Main">
        <a class="nav-link" href="StudentAssignments.php" aria-current="page">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="8" y="2" width="8" height="4" rx="1"/>
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
            <path d="M9 12h6"/>
            <path d="M9 16h4"/>
          </svg>
          <span>Assignments</span>
        </a>

       <a class="nav-link is-active" href="#.php" aria-current="page">
           <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
               <rect x="2" y="4" width="20" height="16" rx="2"/>
               <polyline points="22 6 12 13 2 6"/>
            </svg>
         <span>Email Service</span>
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
        <span>Class Code: <?= $classCode; ?></span>
      </div>

      <div class="user-chip">
        <!-- Initials and name: replace with  echo $adminName  (initials can stay "AN" for now) -->
        <span class="user-avatar" aria-hidden="true">AN</span>
        <span class="user-name"><?= $name ?></span>
      </div>
    </header>

    <!-- Banner -->
    <section class="banner">
      <div class="banner-titles">
        <div class="title">
          <h1>Email Verification</h1>
          <p>Confirm your email to receive assignment reminders</p>
        </div>
      </div>

      <svg class="banner-art" viewBox="0 0 220 160" fill="none" aria-hidden="true">
        <rect x="30" y="30" width="160" height="100" rx="14" stroke="#fff" stroke-opacity=".3" stroke-width="2"/>
        <polyline points="34 38 110 94 186 38" stroke="#fff" stroke-opacity=".4" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="168" cy="118" r="24" fill="#ff6a00"/>
        <polyline points="157 118 165 126 180 110" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </section>


    <!-- ##########################################################
         TEMPLATE SWITCH (no backend yet)
         Change to true to preview the "verified" state.
         Later, replace this line with the real value from your DB.
         ########################################################## -->
    <?php $emailVerified = false; ?>

    <section class="ev-layout" aria-label="Email verification">

      <!-- ==================== Verify card ==================== -->
      <article class="card">
        <header class="card-head">
          <h2 class="card-title">Email Service</h2>
          <?php if ($emailVerified): ?>
            <span class="ev-status ev-status--verified">Verified</span>
          <?php else: ?>
            <span class="ev-status ev-status--pending">Not Verified</span>
          <?php endif; ?>
        </header>

        <div class="card-body ev-body">

          <?php if ($emailVerified): ?>
            <!-- ---------- STATE: VERIFIED ---------- -->
            <div class="ev-icon ev-icon--verified" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
              </svg>
            </div>

            <h3 class="ev-heading">Your Email Is Verified</h3>
            <p class="ev-sub">You will now receive updates on upcoming and overdue assignments.</p>

            <form class="ev-form" method="POST">
              <label class="ev-label" for="email">Email address</label>
              <div class="ev-input-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <rect x="2" y="4" width="20" height="16" rx="2"/>
                  <polyline points="22 6 12 13 2 6"/>
                </svg>
                <!-- Replace the value with the student's saved email -->
                <input class="ev-input" type="email" id="email" name="email" value="student@example.com" readonly>
              </div>

              <button type="button" class="ev-btn ev-btn--done" disabled>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
                Email Verified
              </button>
            </form>

          <?php else: ?>
            <!-- ---------- STATE: NOT VERIFIED ---------- -->
            <div class="ev-icon ev-icon--pending" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <polyline points="22 6 12 13 2 6"/>
              </svg>
            </div>

            <h3 class="ev-heading">Verify Your Email</h3>
            <p class="ev-sub">Get updated on upcoming and overdue assignments.</p>

            <form class="ev-form" method="POST">
              <label class="ev-label" for="email">Email address</label>
              <div class="ev-input-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <rect x="2" y="4" width="20" height="16" rx="2"/>
                  <polyline points="22 6 12 13 2 6"/>
                </svg>
                <input class="ev-input" type="email" id="email" name="email" placeholder="you@example.com" autocomplete="email" required>
              </div>

              <button type="submit" name="verifyEmail" class="ev-btn">Verify Email</button>
            </form>
          <?php endif; ?>

        </div>

        <footer class="card-foot">
          <?php if ($emailVerified): ?>
            <span class="ev-note">Need to use a different address? Contact your professor.</span>
          <?php else: ?>
            <span class="ev-note">We will send a confirmation link to this address.</span>
          <?php endif; ?>
        </footer>
      </article>

      <!-- ==================== Benefits card ==================== -->
      <article class="card">
        <header class="card-head">
          <h2 class="card-title">What You'll Receive</h2>
        </header>

        <div class="card-body">
          <div class="ev-benefit">
            <span class="ev-benefit-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
              </svg>
            </span>
            <div>
              <p class="ev-benefit-title">Upcoming deadline reminders</p>
              <p class="ev-benefit-desc">A heads-up before an assignment is due.</p>
            </div>
          </div>

          <div class="ev-benefit">
            <span class="ev-benefit-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
            </span>
            <div>
              <p class="ev-benefit-title">Overdue assignment alerts</p>
              <p class="ev-benefit-desc">Know right away when something has slipped past its deadline.</p>
            </div>
          </div>

          <div class="ev-benefit">
            <span class="ev-benefit-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="9" y1="13" x2="15" y2="13"/>
                <line x1="9" y1="17" x2="13" y2="17"/>
              </svg>
            </span>
            <div>
              <p class="ev-benefit-title">New assignment notices</p>
              <p class="ev-benefit-desc">Be notified when your professor posts something new.</p>
            </div>
          </div>
        </div>
      </article>

    </section>

  </main>

</div>
</body>
</html>