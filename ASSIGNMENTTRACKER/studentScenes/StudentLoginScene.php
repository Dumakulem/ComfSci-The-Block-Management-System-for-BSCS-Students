<?php
session_start();

if(isset($_POST['loginStudentId']) && isset($_POST['loginStudentPassword'])){
    require_once __DIR__ . '/../database/student.php';

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
  <link rel="stylesheet" href="../css/main.css">
</head>
<body>
  <main class="card">
    <div class="mark" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
        <path d="M6 12v5c3 3 9 3 12 0v-5"/>
      </svg>
    </div>

    <h1>Student log in</h1>
    <p class="subtitle">Enter your student credentials to continue.</p>

    <form method="POST">
      <div class="field">
        <label for="student_id">Student ID</label>
        <input type="text" id="student_id" name="loginStudentId" placeholder="Enter your student ID" autocomplete="username" required>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="loginStudentPassword" placeholder="Enter your password" autocomplete="current-password" required>
      </div>

      <button type="submit" class="btn btn-primary">Log in</button>
    </form>

    <p class="footer">Don&rsquo;t have an account? Please contact your administrator.</p>
  </main>
</body>
</html>