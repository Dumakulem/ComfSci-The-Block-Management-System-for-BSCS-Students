<?php

declare(strict_types=1);

// Where to go after logging in. Change this if your dashboard lives somewhere else.
const DASHBOARD_URL = '/group-project/pages/dashboard.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($_SESSION['authenticated']) && !empty($_SESSION['user_type'])) {
    header('Location: ' . DASHBOARD_URL);
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = 'Your form expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        require_once __DIR__ . '/../config/database.php';

        try {
            $pdo = db();
            $match = null;

            // Check students first, then admins.
            foreach (['student', 'admin'] as $t) {
                $statement = $pdo->prepare("SELECT * FROM tbl{$t} WHERE {$t}Email = ?");
                $statement->execute([$email]);
                $row = $statement->fetch(PDO::FETCH_ASSOC);

                if ($row && password_verify($password, (string) $row[$t . 'Password'])) {
                    $match = [
                        'type' => $t,
                        'id' => (int) $row[$t . 'ID'],
                        'email' => (string) $row[$t . 'Email'],
                        'name' => trim($row[$t . 'FirstName'] . ' ' . $row[$t . 'LastName']),
                    ];
                    break;
                }
            }

            if ($match !== null) {
                session_regenerate_id(true);
                $_SESSION['authenticated'] = true;
                $_SESSION['user_type'] = $match['type'];
                $_SESSION['user_id'] = $match['id'];
                $_SESSION['user_email'] = $match['email'];
                $_SESSION['user_name'] = $match['name'];
                $_SESSION['flash'] = 'Successfully logged in.';

                header('Location: ' . DASHBOARD_URL);
                exit;
            }

            $error = 'Invalid email or password.';
        } catch (PDOException $exception) {
            error_log('Login failed: ' . $exception->getMessage());
            $error = 'Unable to connect to the database.';
        }
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Shown as a top-right notification (see toast.php).
$toast = isset($_GET['logged_out']) ? 'Successfully logged out.' : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gordon College Portal - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/auth/portal-auth.css">
    <script src="../assets/auth/auth.js" defer></script>
</head>
<body>
    <main class="card">
        <div class="art">
            <img class="art__wave art__wave--top" src="../assets/auth/vector-7.svg" alt="">
            <img class="art__wave art__wave--bottom" src="../assets/auth/vector-5.svg" alt="">
            <img class="art__cloud" src="../assets/auth/cloud-upload.png" alt="">
        </div>
        <section class="panel">
            <form action="login.php" method="post" class="signin">
                <div class="brand">
                    <img src="../assets/auth/portal.png" alt="">
                    <span>Gordon College Portal</span>
                </div>
                <h1>Login</h1>

                <?php if ($error !== ''): ?>
                    <p class="message error" role="alert"><?= e($error) ?></p>
                <?php endif; ?>

                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

                <label for="email">Gmail</label>
                <input id="email" name="email" type="email" autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= e($email) ?>" placeholder="yourname@gmail.com" required>

                <label for="password">Password</label>
                <div class="password">
                    <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Your Password" required>
                    <button type="button" class="toggle" data-toggle="password" aria-pressed="false">Show</button>
                </div>

                <button class="button" type="submit">Login</button>

                <p class="switch">Don't have an account? <a href="register.php">Create Account</a></p>
            </form>
        </section>
    </main>
    <?php require __DIR__ . '/toast.php'; ?>
</body>
</html>
