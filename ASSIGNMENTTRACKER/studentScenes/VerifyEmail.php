<?php
session_start();
require_once __DIR__ . '/../database/student.php';
require_once __DIR__ . '/../database/admin.php';
require_once __DIR__ . '/../includes/mailer.php';

$student = getStudent($pdo, (int) $_SESSION['currentStudent']);

$name = $student['studentFirstName'];
$classCode = $student['classCode'];

$typedEmail = $_SESSION['typedEmail'] ?? '';

$emailVerified = ((int)$student['is_email_verified'] == 0) ? false : true;

if (isset($_POST['verifyEmail'])) {
  $_SESSION['typedEmail'] = trim($_POST['email']);

  $sent = verifyEmail($_SESSION['typedEmail']);

  header('Location: ' . $_SERVER['PHP_SELF'] . '#verify-modal');
  exit;
}

if (isset($_POST['confirmCode'])) {
  $verification = checkEmailCode($_POST['code'] ?? '');

  if ($verification['ok']) {
    $_SESSION['verified_email'] = $verification['email'];
    studentEmailVerify($pdo, $student['studentID'], $classCode);
    changeEmailVerify($pdo, $student['studentID'], $classCode, $_SESSION['typedEmail']);
                           // shows the success popup once
    header('Location: ' . $_SERVER['PHP_SELF'] . '#verified-modal');
  } else {
    $_SESSION['codeError'] = [                                // shows the wrong-code popup once
      'reason'  => $verification['reason'],
      'message' => $verification['message'],
      'attempt' => $verification['attempt'] ?? 0,
      'max'     => $verification['max'] ?? 0,
    ];
    header('Location: ' . $_SERVER['PHP_SELF'] . '#wrong-code-modal');
  }
  exit;
}

if (isset($_POST['resendCode'])) {
  verifyEmail($_SESSION['typedEmail'] ?? '');
  header('Location: ' . $_SERVER['PHP_SELF'] . '#verify-modal');
  exit;
}

// One-time messages left by the redirects above (shown by the popups at the bottom)
$codeError    = $_SESSION['codeError'] ?? null;
$justVerified = !empty($_SESSION['justVerified']);
unset($_SESSION['codeError'], $_SESSION['justVerified']);

// Seconds left before "Resend code" may be used (same 60 s rule as verifyEmail())
$resendWait = isset($_SESSION['email_code']['sent'])
  ? max(0, 60 - (time() - $_SESSION['email_code']['sent']))
  : 0;
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
                <input class="ev-input" type="email" id="email" name="email" value="<?= htmlspecialchars($_SESSION['verified_email'] ?? '') ?>" readonly>
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
                <input class="ev-input" type="email" id="email" name="email" placeholder="<?= $student['studentEmail'] ?>" autocomplete="email" required>
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


</div>

<?php if (!$emailVerified): ?>
<!-- ================= Email verification popup (opens when the address ends in #verify-modal) ================= -->
<div class="vm" id="verify-modal" role="dialog" aria-modal="true" aria-labelledby="verify-title">
  <a href="#!" class="vm-backdrop" aria-label="Close dialog"></a>

  <div class="vm-box">
    <div class="vm-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="4" width="20" height="16" rx="2"/>
        <polyline points="22 6 12 13 2 6"/>
      </svg>
    </div>

    <h2 class="vm-title" id="verify-title">Verify your email</h2>
    <!-- Replace example@gmail.com with the email the student typed -->
    <p class="vm-text">An email was sent to <strong><?= htmlspecialchars($typedEmail) ?></strong>. Enter the 6-digit code to confirm it.</p>

    <!-- Verification form (template). PHP receives $_POST['code'] -->
    <form method="POST" class="vm-form" autocomplete="off">

      <div class="vm-otp" role="group" aria-label="6-digit verification code">
        <input class="vm-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" aria-label="Digit 1" required>
        <input class="vm-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" aria-label="Digit 2" required>
        <input class="vm-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" aria-label="Digit 3" required>
        <input class="vm-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" aria-label="Digit 4" required>
        <input class="vm-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" aria-label="Digit 5" required>
        <input class="vm-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" aria-label="Digit 6" required>
      </div>
      <!-- The six boxes are combined into this one value by the script below -->
      <input type="hidden" name="code" id="vmCode">

      <!-- Resend button and countdown -->
      <div class="vm-meta">
        <button type="submit" form="resendForm" name="resendCode" id="vmResend" class="vm-resend" disabled>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="23 4 23 10 17 10"/>
            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
          </svg>
          Resend code
        </button>
        <span class="vm-timer" id="vmTimer" aria-live="polite"></span>
      </div>

      <div class="vm-actions">
        <a href="#!" class="vm-btn vm-btn-outline">Cancel</a>
        <button type="submit" name="confirmCode" class="vm-btn vm-btn-primary">Confirm</button>
      </div>
    </form>

    <!-- Empty form used only by the Resend button, so the code boxes never block it -->
    <form method="POST" id="resendForm"></form>
  </div>
</div>

<script>
 (function () {
  var digits = document.querySelectorAll('.vm-digit');
  var hidden = document.getElementById('vmCode');
  var form   = hidden.form;

  function combine() {
    hidden.value = Array.prototype.map.call(digits, function (d) { return d.value; }).join('');
  }

  digits.forEach(function (box, i) {
    box.addEventListener('input', function () {
      box.value = box.value.replace(/\D/g, '').slice(0, 1);
      if (box.value && i < digits.length - 1) digits[i + 1].focus();
      combine();
    });
    box.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && !box.value && i > 0) digits[i - 1].focus();
    });
    box.addEventListener('paste', function (e) {
      var text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
      if (!text) return;
      e.preventDefault();
      for (var k = 0; k < digits.length; k++) digits[k].value = text.charAt(k);
      digits[Math.min(text.length, digits.length) - 1].focus();
      combine();
    });
  });

  form.addEventListener('submit', combine);

  // Put the cursor in the first box whenever the code popup opens
  function focusFirst() { if (location.hash === '#verify-modal') digits[0].focus(); }
  window.addEventListener('hashchange', focusFirst);
  window.addEventListener('load', focusFirst);   // after a redirect the popup is only "open" once the page has loaded

  // Resend button: enabled when the 60-second cooldown (counted by PHP) is over
  var resend = document.getElementById('vmResend');
  var timer  = document.getElementById('vmTimer');
  var left   = <?= (int) $resendWait ?>;

  function showTimer() {
    if (left <= 0) { resend.disabled = false; timer.textContent = ''; return false; }
    timer.textContent = 'Resend available in ' + left + 's';
    return true;
  }

  if (showTimer()) {
    var t = setInterval(function () {
      left--;
      if (!showTimer()) clearInterval(t);
    }, 1000);
  }
})();
</script>

<?php endif; ?>

<?php if ($codeError):
  $titles = [
    'wrong'   => 'Incorrect Code',
    'expired' => 'Code Expired',
    'locked'  => 'Too Many Attempts',
    'none'    => 'No Code Found',
  ];
  $errTitle    = $titles[$codeError['reason']] ?? 'Verification Failed';
  $showCounter = in_array($codeError['reason'], ['wrong', 'locked'], true) && $codeError['max'] > 0;
?>
<!-- ================= Wrong code popup (opens when the address ends in #wrong-code-modal) ================= -->
<div class="vm" id="wrong-code-modal" role="alertdialog" aria-modal="true" aria-labelledby="wrong-title" aria-describedby="wrong-text">
  <a href="#verify-modal" class="vm-backdrop" aria-label="Back to code entry"></a>

  <div class="vm-box">
    <div class="vm-icon vm-icon--error" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <line x1="15" y1="9" x2="9" y2="15"/>
        <line x1="9" y1="9" x2="15" y2="15"/>
      </svg>
    </div>

    <h2 class="vm-title" id="wrong-title"><?= htmlspecialchars($errTitle) ?></h2>
    <p class="vm-text" id="wrong-text"><?= htmlspecialchars($codeError['message']) ?></p>

    <?php if ($showCounter): ?>
    <!-- Attempt counter: one dot per allowed attempt, used ones are filled -->
    <div class="vm-attempts" role="status">
      <span class="vm-attempts-label">Attempt <strong><?= (int) $codeError['attempt'] ?></strong> of <strong><?= (int) $codeError['max'] ?></strong></span>
      <span class="vm-dots" aria-hidden="true">
        <?php for ($i = 1; $i <= $codeError['max']; $i++): ?><i<?= $i <= $codeError['attempt'] ? ' class="is-used"' : '' ?>></i><?php endfor; ?>
      </span>
    </div>
    <?php endif; ?>

    <div class="vm-actions">
      <a href="#verify-modal" class="vm-btn vm-btn-primary">Confirm</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($justVerified): ?>
<!-- ================= Success popup (opens when the address ends in #verified-modal) ================= -->
<div class="vm" id="verified-modal" role="dialog" aria-modal="true" aria-labelledby="verified-title">
  <a href="#!" class="vm-backdrop" aria-label="Close dialog"></a>

  <div class="vm-box">
    <div class="vm-icon vm-icon--success" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
        <polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
    </div>

    <h2 class="vm-title" id="verified-title">Email Verified Successfully</h2>
    <p class="vm-text"><strong><?= htmlspecialchars($_SESSION['verified_email'] ?? '') ?></strong> is now verified. You will receive updates on upcoming and overdue assignments.</p>

    <div class="vm-actions">
      <a href="#!" class="vm-btn vm-btn-primary">Confirm</a>
    </div>
  </div>
</div>
<?php endif; ?>


</body>
</html>