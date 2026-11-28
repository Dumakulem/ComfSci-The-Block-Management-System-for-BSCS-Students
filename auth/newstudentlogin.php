<?php
session_start();

if(isset($_POST['loginStudentId']) && isset($_POST['loginStudentPassword'])){
    require_once __DIR__ . '/../ASSIGNMENTTRACKER/database/student.php';

if(loginStudent($pdo, $_POST['loginStudentId'], $_POST['loginStudentPassword'])){
    session_regenerate_id(true);
    $_SESSION['currentStudent'] = (int) $_POST['loginStudentId'];
    header('Location: StudentAssignments.php');
    exit;
}

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Log In</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../ASSIGNMENTTRACKER/css/newmain.css">
</head>
<body>
  <main class="card">

    <!-- Art panel -->
    <aside class="art" aria-hidden="true">
      <div class="art__figure">
        <svg viewBox="0 0 240 260" fill="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="capFill" x1="30" y1="60" x2="210" y2="170" gradientUnits="userSpaceOnUse">
              <stop offset="0" stop-color="#ffffff"/>
              <stop offset="1" stop-color="#ffe2c2"/>
            </linearGradient>
            <linearGradient id="capBase" x1="70" y1="130" x2="170" y2="210" gradientUnits="userSpaceOnUse">
              <stop offset="0" stop-color="#fff4e6"/>
              <stop offset="1" stop-color="#ffd3a1"/>
            </linearGradient>
          </defs>
          <circle cx="120" cy="130" r="112" fill="#fff" fill-opacity=".12"/>
          <circle cx="120" cy="130" r="86" fill="#fff" fill-opacity=".12"/>
          <!-- cap base -->
          <path d="M68 128v44c0 14 24 26 52 26s52-12 52-26v-44l-52 24-52-24z" fill="url(#capBase)"/>
          <!-- cap top -->
          <path d="M120 54L22 100l98 46 98-46-98-46z" fill="url(#capFill)"/>
          <path d="M120 70l-66 30 66 30 66-30-66-30z" fill="#fff" fill-opacity=".55"/>
          <!-- tassel -->
          <path d="M204 108v50" stroke="#f79205" stroke-width="6" stroke-linecap="round"/>
          <circle cx="204" cy="164" r="9" fill="#f79205"/>
          <circle cx="205" cy="48" r="7" fill="#fff" fill-opacity=".7"/>
          <circle cx="30" cy="196" r="5" fill="#fff" fill-opacity=".6"/>
          <circle cx="214" cy="212" r="4" fill="#fff" fill-opacity=".5"/>
        </svg>
      </div>
      <div class="art__copy">
        <h2>Welcome back, student</h2>
        <p>Sign in to see your assignments in one place.</p>
      </div>
    </aside>

    <!-- Form column -->
    <section class="panel">
      <div class="panel__inner">

        <header class="brand">
          <div class="brand__mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
              <path d="M6 12v5c3 3 9 3 12 0v-5"/>
            </svg>
          </div>
          <p class="brand__title">
            <span class="brand__college">Gordon College</span>
            <span class="brand__portal">Student Portal</span>
          </p>
        </header>

        <h1 class="heading">Student log in</h1>
        <p class="subtitle">Enter your student credentials to continue.</p>

        <form method="POST">
          <div class="field">
            <label for="student_id">Student ID</label>
            <input type="text" id="student_id" name="loginStudentId" placeholder="Enter your student ID" autocomplete="username" required autofocus>
          </div>

          <div class="field field--password">
            <label for="password">Password</label>
            <input type="password" id="password" name="loginStudentPassword" placeholder="Enter your password" autocomplete="current-password" required>
            <button type="button" class="field__toggle" aria-label="Show password" aria-pressed="false" data-toggle-for="password">
              <svg class="icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg class="icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17.9 17.9A10.6 10.6 0 0 1 12 19c-6.4 0-10-7-10-7a18.5 18.5 0 0 1 5.1-5.9M9.9 5.2A9.9 9.9 0 0 1 12 5c6.4 0 10 7 10 7a18.6 18.6 0 0 1-2.2 3.2M1 1l22 22"/>
                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
              </svg>
            </button>
          </div>

          <button type="submit" class="btn btn-primary">Log in</button>
        </form>

        <p class="footer">Don&rsquo;t have an account? Please contact your administrator.</p>

      </div>
    </section>
  </main>

  <script>
    // Show / hide password (front-end only)
    document.querySelectorAll('[data-toggle-for]').forEach(function (btn) {
      var input = document.getElementById(btn.getAttribute('data-toggle-for'));
      btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      });
    });
  </script>
</body>
</html>