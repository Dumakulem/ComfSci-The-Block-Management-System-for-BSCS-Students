<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($_SESSION['authenticated']) && !empty($_SESSION['user_type'])) {
    header('Location: ../pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        require_once __DIR__ . '/../config/database.php';

        try {
            $pdo = db();
            $user = null;
            $userType = '';

            $studentStatement = $pdo->prepare(
                'SELECT studentID, studentFirstName, studentLastName, studentEmail, studentPassword
                 FROM tblstudent
                 WHERE studentEmail = ? AND archivedAt IS NULL'
            );
            $studentStatement->execute([$email]);
            $student = $studentStatement->fetch();

            if ($student && password_verify($password, (string) $student['studentPassword'])) {
                $user = $student;
                $userType = 'student';
            }

            if ($user === null) {
                $adminStatement = $pdo->prepare(
                    'SELECT adminID, adminFirstName, adminLastName, adminEmail, adminPassword
                     FROM tbladmin
                     WHERE adminEmail = ?'
                );
                $adminStatement->execute([$email]);
                $admin = $adminStatement->fetch();

                if ($admin && password_verify($password, (string) $admin['adminPassword'])) {
                    $user = $admin;
                    $userType = 'admin';
                }
            }

            if ($user !== null) {
                session_regenerate_id(true);
                $_SESSION['authenticated'] = true;
                $_SESSION['user_type'] = $userType;

                if ($userType === 'student') {
                    $_SESSION['user_id'] = (int) $user['studentID'];
                    $_SESSION['user_email'] = (string) $user['studentEmail'];
                    $_SESSION['user_name'] = trim(
                        (string) $user['studentFirstName'] . ' ' . (string) $user['studentLastName']
                    );
                } else {
                    $_SESSION['user_id'] = (int) $user['adminID'];
                    $_SESSION['user_email'] = (string) $user['adminEmail'];
                    $_SESSION['user_name'] = trim(
                        (string) $user['adminFirstName'] . ' ' . (string) $user['adminLastName']
                    );
                }

                header('Location: ../pages/dashboard.php');
                exit;
            }

            $error = 'Invalid email or password.';
        } catch (PDOException $exception) {
            $error = 'Unable to connect to the database.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                <p class="login-error" style='color: red; font-weight: bold; font-size: 20px; margin: 0'><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <button class="submit" type="submit">Login</button>
        </form>
    </main>
</body>
</html>
