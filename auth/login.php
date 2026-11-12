<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

session_start();

if (!empty($_SESSION['authenticated'])) {
    header('Location: ../pages/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $pdo = db();
        $user = null;
        $userType = null;

        $studentStatement = $pdo->prepare(
            'SELECT studentID, studentFirstName, studentLastName, studentEmail, studentPassword
             FROM tblstudent WHERE studentEmail = ?'
        );
        $studentStatement->execute([$email]);
        $student = $studentStatement->fetch();

        if ($student && password_verify($password, $student['studentPassword'])) {
            $user = $student;
            $userType = 'student';
        } else {
            $adminStatement = $pdo->prepare(
                'SELECT adminID, adminFirstName, adminLastName, adminEmail, adminPassword
                 FROM tbladmin WHERE adminEmail = ?'
            );
            $adminStatement->execute([$email]);
            $admin = $adminStatement->fetch();

            if ($admin && password_verify($password, $admin['adminPassword'])) {
                $user = $admin;
                $userType = 'admin';
            }
        }

        if ($user !== null && $userType !== null) {
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['user_type'] = $userType;
            $_SESSION['user_id'] = $userType === 'student' ? $user['studentID'] : $user['adminID'];
            $_SESSION['user_email'] = $userType === 'student' ? $user['studentEmail'] : $user['adminEmail'];
            $_SESSION['user_name'] = trim(
                ($userType === 'student' ? $user['studentFirstName'] : $user['adminFirstName']) . ' ' .
                ($userType === 'student' ? $user['studentLastName'] : $user['adminLastName'])
            );

            header('Location: ../pages/index.php');
            exit;
        }

        $error = 'Invalid email or password.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gordon College Portal - Login</title>
  <link rel="stylesheet" href="../assets/auth/styles.css">
</head>
<body>
  <img class="page-bg" src="../assets/auth/background.svg" alt="">
  <main class="card">
    <div class="art" aria-hidden="true">
      <div class="art__fill masked"></div>
      <img class="art__vector art__vector--5 masked" src="../assets/auth/vector-5.svg" alt="">
      <img class="art__vector art__vector--7 masked" src="../assets/auth/vector-7.svg" alt="">
      <img class="art__illustration masked" src="../assets/auth/cloud-upload.png" alt="">
    </div>
    <form action="login.php" method="post" class="login">
      <header class="brand">
        <img class="brand__logo" src="../assets/auth/portal.png" alt="">
        <h1 class="brand__title">
          <span class="brand__college">Gordon College</span>
          <span class="brand__portal">Portal</span>
        </h1>
      </header>
      <div class="field">
        <input id="email" name="email" type="email" autocomplete="username" placeholder="Email@gordoncollege.edu.ph" required>
        <label for="email">Email</label>
      </div>
      <div class="field">
        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Your Password" required>
        <label for="password">Password</label>
      </div>
      <?php if ($error !== ''): ?>
        <p class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <button class="submit" type="submit">Login</button>
    </form>
  </main>
</body>
</html>
