<?php
session_start();

$error = '';
$bruteForceWarn = '';
$isLockedOut = false;
$showDeniedModal = false;

// Anti-brute force settings
$maxAttempts = 5;
$lockMinutes = 15;

if (isset($_POST['loginAdminUsername']) && isset($_POST['loginAdminPassword'])) {
    $name = $_POST['loginAdminUsername'];
    $pass = $_POST['loginAdminPassword'];

    require_once __DIR__ . '/../config/database.php';

    $db = db();
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    // Check existing failed attempts for this username and IP
    $attemptStmt = $db->prepare(
        'SELECT attemptID, attempts, lockedUntil
         FROM tblloginattempts
         WHERE email = ? AND ipAddress = ?'
    );
    $attemptStmt->execute([$name, $ip]);
    $attempt = $attemptStmt->fetch(PDO::FETCH_ASSOC);

    if ($attempt && (int) $attempt['lockedUntil'] > time()) {
        $minutesLeft = (int) ceil(((int) $attempt['lockedUntil'] - time()) / 60);
        $error = 'Maximum login attempts exceeded for security protection.';
        $bruteForceWarn = 'Too many failed attempts. Please try again in ' . $minutesLeft . ' minute(s).';
        $isLockedOut = true;
        $showDeniedModal = true;
    } else {
        // Reset expired lockdown
        if ($attempt && (int) $attempt['lockedUntil'] !== 0) {
            $resetStmt = $db->prepare(
                'UPDATE tblloginattempts SET attempts = 0, lockedUntil = 0 WHERE attemptID = ?'
            );
            $resetStmt->execute([$attempt['attemptID']]);
            $attempt['attempts'] = 0;
        }

        if (loginAdmin($db, $name, $pass)) {
            // Clear failed attempts on successful login
            $clearStmt = $db->prepare('DELETE FROM tblloginattempts WHERE email = ? AND ipAddress = ?');
            $clearStmt->execute([$name, $ip]);

            // Fetch the admin's row from tbladmin so dashboard.php recognizes the session
            $adminStmt = $db->prepare(
                'SELECT adminID, adminFirstName, adminLastName, adminEmail
                 FROM tbladmin
                 WHERE adminEmail = ? OR adminFirstName = ?
                 LIMIT 1'
            );
            $adminStmt->execute([$name, $name]);
            $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);

            session_regenerate_id(true);
            $_SESSION['name'] = $name;
            $_SESSION['authenticated'] = true;
            $_SESSION['user_type'] = 'admin';

            if ($adminRow) {
                $_SESSION['user_id'] = (int) $adminRow['adminID'];
                $_SESSION['user_email'] = (string) $adminRow['adminEmail'];
                $_SESSION['user_name'] = trim(
                    (string) $adminRow['adminFirstName'] . ' ' . (string) $adminRow['adminLastName']
                );
            }

            header('Location: ../pages/dashboard.php');
            exit;
        }

        // Record failed attempt
        if ($attempt) {
            $attempts = (int) $attempt['attempts'] + 1;
            $lockedUntil = $attempts >= $maxAttempts ? time() + ($lockMinutes * 60) : 0;

            $updateStmt = $db->prepare(
                'UPDATE tblloginattempts SET attempts = ?, lockedUntil = ? WHERE attemptID = ?'
            );
            $updateStmt->execute([$attempts, $lockedUntil, $attempt['attemptID']]);
        } else {
            $attempts = 1;
            $insertStmt = $db->prepare(
                'INSERT INTO tblloginattempts (email, ipAddress, attempts, lockedUntil) VALUES (?, ?, 1, 0)'
            );
            $insertStmt->execute([$name, $ip]);
        }

        $error = 'The username or password you entered is incorrect. Please try again.';

        if ($attempts >= $maxAttempts) {
            $bruteForceWarn = 'Account locked: Too many failed attempts. Try again in ' . $lockMinutes . ' minutes.';
            $isLockedOut = true;
        } else {
            $attemptsLeft = $maxAttempts - $attempts;
            $bruteForceWarn = 'Security Warning: ' . $attemptsLeft . ' of ' . $maxAttempts . ' login attempt(s) remaining before a ' . $lockMinutes . '-minute lockout.';
        }
        $showDeniedModal = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Log In</title>
    <link rel="stylesheet" href="../assets/auth/styles.css">
    <style>
        /* Page-specific tweaks: room for the extra links under the button
           and a card that scrolls instead of clipping on short screens. */
        html, body { height: auto; min-height: 100%; overflow-y: auto; }
        body { min-height: 100dvh; padding: 16px; }

        .card {
            position: relative;
            height: auto;
            min-height: 600px;
            padding: 48px 47px 40px;
            align-items: center;
        }
        .art { align-self: center; }

        .login { gap: 14px; margin-top: 0; }
        .field { margin-top: 16px; }
        .submit { margin-top: 30px; }

        .login-switch {
            margin: 0;
            font: 500 15px/1.3 var(--font-ui);
            color: #666;
            text-align: center;
        }
        .login-switch a {
            color: var(--accent);
            font-weight: 700;
            text-decoration: none;
        }
        .login-switch a:hover { text-decoration: underline; }

        .login-note {
            margin: 0;
            font: 500 13px/1.3 var(--font-ui);
            color: #777;
            text-align: center;
        }

        /* Access denied modal */
        .modal {
            width: min(400px, calc(100% - 32px));
            padding: 36px 32px 30px;
            border: 0;
            border-radius: 32px;
            background: var(--card-bg);
            color: #333;
            text-align: center;
            box-shadow: 0 10px 40px rgba(60, 25, 0, 0.45);
            animation: modalIn 0.2s ease-out;
        }
        .modal::backdrop {
            background: rgba(60, 25, 0, 0.55);
            -webkit-backdrop-filter: blur(3px);
                    backdrop-filter: blur(3px);
        }
        .modal__icon {
            display: grid;
            place-items: center;
            width: 72px;
            height: 72px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #fde8e8;
            color: #b00020;
        }
        .modal__icon svg { width: 36px; height: 36px; }
        .modal__title {
            margin: 0 0 8px;
            font: 700 28px/1.2 var(--font-display);
            color: #b00020;
        }
        .modal__text {
            margin: 0;
            font: 500 16px/1.45 var(--font-ui);
            color: #666;
        }
        .modal__warn {
            margin: 14px 0 0;
            padding: 10px 14px;
            border-radius: 14px;
            background: #fff3cd;
            border: 1px solid #ffe69c;
            color: #856404;
            font: 600 13.5px/1.4 var(--font-ui);
        }
        .modal__warn--locked {
            background: #fde8e8;
            border-color: #f5c2c7;
            color: #b00020;
        }
        .modal .submit {
            width: 100%;
            height: 56px;
            margin-top: 24px;
            padding: 10px 24px;
            font-size: 22px;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(8px) scale(0.97); }
            to   { opacity: 1; transform: none; }
        }
        @media (prefers-reduced-motion: reduce) { .modal { animation: none; } }

        @media (max-width: 960px) {
            .card { padding: 40px 24px; }
            .login { margin: 0; }
        }
        @media (max-width: 480px) {
            .card { padding: 32px 18px; border-radius: 36px; }
        }
    </style>
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
        <form method="POST" class="login">
            <header class="brand">
                <img class="brand__logo" src="../assets/auth/portal.png" alt="">
                <h1 class="brand__title">
                    <span class="brand__college">Gordon College</span>
                    <span class="brand__portal">Admin Portal</span>
                </h1>
            </header>

            <div class="field">
                <input id="username" name="loginAdminUsername" type="text" autocomplete="username" placeholder="Your Username" required autofocus>
                <label for="username">Admin Username</label>
            </div>
            <div class="field">
                <input id="password" name="loginAdminPassword" type="password" autocomplete="current-password" placeholder="Your Password" required>
                <label for="password">Password</label>
            </div>

            <button class="submit" type="submit">Log in</button>

            <p class="login-switch">Need an admin account? <a href="newadmincreateaccount.php">Create Account</a></p>
            <p class="login-note">Authorized administrators only.</p>
        </form>
    </main>

    <?php if ($showDeniedModal): ?>
    <dialog class="modal" id="deniedModal" aria-labelledby="deniedTitle" aria-describedby="deniedText">
        <div class="modal__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M4.9 4.9l14.2 14.2"/>
            </svg>
        </div>
        <h2 class="modal__title" id="deniedTitle"><?= $isLockedOut ? 'Account Locked' : 'User Denied Access' ?></h2>
        <p class="modal__text" id="deniedText"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($bruteForceWarn !== ''): ?>
            <p class="modal__warn <?= $isLockedOut ? 'modal__warn--locked' : '' ?>">
                <?= htmlspecialchars($bruteForceWarn, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>
        <form method="dialog">
            <button class="submit" type="submit" autofocus><?= $isLockedOut ? 'Close' : 'Try again' ?></button>
        </form>
    </dialog>
    <script>
        (function () {
            var modal = document.getElementById('deniedModal');
            if (!modal) return;

            if (typeof modal.showModal === 'function') { modal.showModal(); }
            else { modal.setAttribute('open', ''); }

            // Click outside the box closes it
            modal.addEventListener('click', function (e) {
                var r = modal.getBoundingClientRect();
                if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
                    modal.close();
                }
            });

            // Back to the username field when it closes (button, Esc or outside click)
            modal.addEventListener('close', function () {
                var u = document.getElementById('username');
                if (u) { u.focus(); u.select(); }
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>