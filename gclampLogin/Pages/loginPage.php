<!-- <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style/log.css">
    <title>Document</title>
</head>
<body>
    it can be changed since we are using figma -->
    <!-- <div class="main-container">
        <div class="right-section">
            <h1>Login</h1>
            <form actions="loginPage.php"       method="post">
                <div class="email-form">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Email@gordoncollege.edu.ph" required>
                </div>
                <div class="pass-form">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Your Password" required>
                    
                    <button class="submit-button" type="submit" name="login">Login</button>
                </div>
                
                
            </form>
        </div>
    </div>
    
</body>
</html> -->

<?php
// Require the files means to connect the files to this page so that it can use the functions and variables defined in those files.

require_once "../SQL/actions.php"; // Sql actions ito.
require_once "../Route/loginChecker.php"; // kung ito ba ay admin or student and dito rin yung condition para makapag login at mapunta sa dashboard page.
session_start();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gordon College Portal – Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@700&family=Roboto:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style/styles.css">
</head>
<body>
  <img class="page-bg" src="../assets/background.svg" alt="">

  <main class="card">
    <!-- Left: illustration panel -->
    <div class="art" aria-hidden="true">
      <div class="art__fill masked"></div>
      <img class="art__vector art__vector--5 masked" src="../assets/vector-5.svg" alt="">
      <img class="art__vector art__vector--7 masked" src="../assets/vector-7.svg" alt="">
      <img class="art__illustration masked" src="../assets/cloud-upload.png" alt="">
    </div>

    <!-- Right: login form -->
    <form action="loginPage.php" method="post" class="login" id="login-form" novalidate>
      <header class="brand">
        <img class="brand__logo" src="../assets/portal.png" alt="">
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
      <?php if (!empty($error)): ?>
        <p style="color: red;
        font-weight: bold; text-align: center; margin: 0;
        positon: absolute;"><?= htmlspecialchars($error); ?></p>
        <?php endif; ?>
      <button class="submit" type="submit" name="login">Login</button>
      
    </form>
  </main>
</body>
</html>









