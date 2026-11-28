<?php

if (isset($_POST['adminUsername']) && isset($_POST['adminPassword']) && isset($_POST['adminConfirmPassword'])){

$name = $_POST['adminUsername'];
$pass = $_POST['adminPassword'];
$conPass = $_POST['adminConfirmPassword'];

$lastName = $_POST['adminLastName'];
$middleName = $_POST['adminMiddleName'];
$extName = $_POST['adminExtensionName'];
$email = $_POST['adminEmail'];

// require_once __DIR__ . '../config/admin.php';
require_once __DIR__ . '/../config/database.php';

createAdminForOtherDB(db(), $lastName, $name, $middleName, $extName, $email, $pass);

header('Location: newadminlogin.php');
exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account</title>
    <link rel="stylesheet" href="../assets/auth/styles.css">
    <style>
        /* Page-specific tweaks: the reference card is sized for 2 fields,
           this form has 7, so the card grows and fields sit in 2-column rows. */
        html, body { height: auto; min-height: 100%; overflow-y: auto; }
        body { min-height: 100dvh; padding: 16px; }

        .card {
            position: relative;
            width: 1140px;
            height: auto;
            min-height: 600px;
            padding: 44px 47px 40px;
            align-items: center;
        }
        .art { align-self: center; }

        .login {
            width: 480px;
            max-width: 100%;
            gap: 0;
            margin-top: 0;
        }
        .brand { width: 100%; margin-bottom: 10px; }

        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 14px;
            width: 100%;
        }
        .field {
            width: 100%;
            height: 71px;            /* 11px label overhang + 60px box */
            margin-top: 0;
            margin-bottom: 14px;
        }
        .field-row .field { min-width: 0; }
        .field input {
            top: 11px;
            height: 60px;
            padding: 0 14px;
            font-size: 18px;
            border-radius: 18px;
        }
        .field label { left: 18px; font-size: 16px; }
        .field label .optional { font-size: 14px; font-weight: 500; }

        .submit {
            width: 100%;
            height: 62px;
            margin-top: 12px;
            padding: 12px 36px;
            font-size: 26px;
        }

        .login-switch {
            margin: 18px 0 0;
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

        /* Narrow screens: hide art, single column card */
        @media (max-width: 1199px) {
            .art { display: none; }
            .card { width: 600px; justify-content: center; padding: 40px 28px; }
            .login { margin: 0; }
        }
        @media (max-width: 560px) {
            .card { padding: 32px 18px; border-radius: 36px; }
            .field-row { grid-template-columns: 1fr; }
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

            <div class="field-row">
                <div class="field">
                    <input id="last_name" name="adminLastName" type="text" autocomplete="family-name" placeholder="Your Last Name" required>
                    <label for="last_name">Last Name</label>
                </div>
                <div class="field">
                    <input id="middle_name" name="adminMiddleName" type="text" autocomplete="additional-name" placeholder="Your Middle Name" required>
                    <label for="middle_name">Middle Name</label>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <input id="extension_name" name="adminExtensionName" type="text" autocomplete="honorific-suffix" placeholder="e.g. Jr., III">
                    <label for="extension_name">Extension Name <span class="optional">(optional)</span></label>
                </div>
                <div class="field">
                    <input id="username" name="adminUsername" type="text" autocomplete="username" placeholder="Your Username" required>
                    <label for="username">Admin Username</label>
                </div>
            </div>

            <div class="field">
                <input id="email" name="adminEmail" type="email" autocomplete="email" placeholder="Email@gordoncollege.edu.ph" required>
                <label for="email">Email</label>
            </div>

            <div class="field-row">
                <div class="field">
                    <input id="password" name="adminPassword" type="password" autocomplete="new-password" placeholder="Your Password" required>
                    <label for="password">Password</label>
                </div>
                <div class="field">
                    <input id="confirm_password" name="adminConfirmPassword" type="password" autocomplete="new-password" placeholder="Re-enter Password" required>
                    <label for="confirm_password">Confirm Password</label>
                </div>
            </div>

            <button class="submit" type="submit">Create Account</button>

            <p class="login-switch">Already have an account? <a href="newadminlogin.php">Log in</a></p>
        </form>
    </main>
</body>
</html>