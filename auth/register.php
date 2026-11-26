<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';
$accountType = (string) ($_POST['account_type'] ?? 'student');
$pdo = db();
$blocks = $pdo->query('SELECT blockID, classBlock FROM tblblock ORDER BY blockID')->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $middleName = trim((string) ($_POST['middle_name'] ?? ''));
    $extensionName = trim((string) ($_POST['extension_name'] ?? ''));

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = 'Your form expired. Please try again.';
    } elseif (!in_array($accountType, ['student', 'admin'], true)) {
        $error = 'Invalid account type.';
    } elseif ($firstName === '' || $lastName === '') {
        $error = 'First and last name are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@gmail.com')) {
        $error = 'Use a Gmail address (@gmail.com).';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== (string) ($_POST['confirm_password'] ?? '')) {
        $error = 'Passwords do not match.';
    } elseif (
        $accountType === 'student' &&
        count($blocks) > 0 &&
        !filter_var($_POST['block_id'] ?? null, FILTER_VALIDATE_INT)
    ) {
        $error = 'Please select a valid block.';
    } else {
        try {
            // The email must be unused in BOTH tables.
            $taken = $pdo->prepare(
                'SELECT (SELECT COUNT(*) FROM tblstudent WHERE studentEmail = ?)
                      + (SELECT COUNT(*) FROM tbladmin WHERE adminEmail = ?)'
            );
            $taken->execute([$email, $email]);
            if ((int) $taken->fetchColumn() > 0) {
                throw new InvalidArgumentException('That email address is already registered.');
            }

            $pdo->beginTransaction();
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            if ($accountType === 'student') {
                if (count($blocks) === 0) {
                    $courseStatement = $pdo->prepare(
                        'INSERT INTO tblcourse (courseName, curriculumName) VALUES (?, ?)'
                    );
                    $courseStatement->execute(['BSCS Dummy Course', 'Dummy Curriculum']);
                    $courseId = (int) $pdo->lastInsertId();

                    $blockStatement = $pdo->prepare(
                        'INSERT INTO tblblock (classBlock, courseID) VALUES (?, ?)'
                    );
                    $blockStatement->execute(['Dummy Block', $courseId]);
                    $blockId = (int) $pdo->lastInsertId();
                } else {
                    $blockId = (int) $_POST['block_id'];
                    $blockCheck = $pdo->prepare('SELECT 1 FROM tblblock WHERE blockID = ?');
                    $blockCheck->execute([$blockId]);
                    if (!$blockCheck->fetchColumn()) {
                        throw new InvalidArgumentException('Selected block does not exist.');
                    }
                }

                $statement = $pdo->prepare(
                    'INSERT INTO tblstudent
                     (studentLastName, studentFirstName, studentMiddleName, studentExtensionName,
                      studentEmail, studentPassword, blockID)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $statement->execute([
                    $lastName,
                    $firstName,
                    $middleName !== '' ? $middleName : null,
                    $extensionName !== '' ? $extensionName : null,
                    $email,
                    $hashedPassword,
                    $blockId,
                ]);
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO tbladmin
                     (adminLastName, adminFirstName, adminEmail, adminPassword)
                     VALUES (?, ?, ?, ?)'
                );
                $statement->execute([$lastName, $firstName, $email, $hashedPassword]);
            }

            $pdo->commit();
            $success = ucfirst($accountType) . ' account created successfully.';
            $_POST = [];
            $accountType = 'student';
            $blocks = $pdo->query('SELECT blockID, classBlock FROM tblblock ORDER BY blockID')->fetchAll(PDO::FETCH_ASSOC);
        } catch (InvalidArgumentException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (($exception->errorInfo[1] ?? null) === 1062) {
                $error = 'That email address is already registered.';
            } else {
                error_log('Registration failed: ' . $exception->getMessage());
                $error = 'The account could not be created. Check the database connection and schema.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gordon College Portal - Create Account</title>
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
            <form action="register.php" method="post" class="register">
                <h1>Create Account</h1>

                <?php if ($error !== ''): ?>
                    <p class="message error" role="alert"><?= e($error) ?></p>
                <?php elseif ($success !== ''): ?>
                    <p class="message success" role="status"><?= e($success) ?></p>
                <?php endif; ?>

                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

                <div class="row">
                    <div>
                        <label for="account_type">Account type</label>
                        <select id="account_type" name="account_type">
                            <option value="student" <?= $accountType === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="admin" <?= $accountType === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div id="block-field">
                        <label for="block_id">Block</label>
                        <?php if (count($blocks) > 0): ?>
                            <select id="block_id" name="block_id">
                                <?php foreach ($blocks as $block): ?>
                                    <option value="<?= (int) $block['blockID'] ?>" <?= (string) $block['blockID'] === (string) ($_POST['block_id'] ?? '') ? 'selected' : '' ?>><?= e((string) $block['classBlock']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input id="block_id" type="text" value="Dummy block (auto)" readonly>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">
                    <div>
                        <label for="first_name">First name</label>
                        <input id="first_name" name="first_name" type="text" value="<?= e((string) ($_POST['first_name'] ?? '')) ?>" required>
                    </div>
                    <div>
                        <label for="last_name">Last name</label>
                        <input id="last_name" name="last_name" type="text" value="<?= e((string) ($_POST['last_name'] ?? '')) ?>" required>
                    </div>
                </div>

                <div id="student-fields">
                    <div class="row">
                        <div>
                            <label for="middle_name">Middle name</label>
                            <input id="middle_name" name="middle_name" type="text" value="<?= e((string) ($_POST['middle_name'] ?? '')) ?>">
                        </div>
                        <div>
                            <label for="extension_name">Extension name</label>
                            <input id="extension_name" name="extension_name" type="text" value="<?= e((string) ($_POST['extension_name'] ?? '')) ?>">
                        </div>
                    </div>
                </div>

                <label for="email">Gmail</label>
                <input id="email" name="email" type="email" autocomplete="email" value="<?= e((string) ($_POST['email'] ?? '')) ?>" placeholder="yourname@gmail.com" pattern=".+@gmail\.com" required>

                <div class="row">
                    <div>
                        <label for="password">Password</label>
                        <div class="password">
                            <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                            <button type="button" class="toggle" data-toggle="password" aria-pressed="false">Show</button>
                        </div>
                    </div>
                    <div>
                        <label for="confirm_password">Confirm password</label>
                        <div class="password">
                            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>
                            <button type="button" class="toggle" data-toggle="confirm_password" aria-pressed="false">Show</button>
                        </div>
                    </div>
                </div>

                <button class="button" type="submit">Create Account</button>

                <p class="switch">Already have an account? <a href="login.php">Login</a></p>
            </form>
        </section>
    </main>
</body>
</html>
