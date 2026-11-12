<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';
$accountType = (string) ($_POST['account_type'] ?? 'student');
$pdo = db();
$blocks = $pdo->query('SELECT blockID, classBlock FROM tblblock ORDER BY blockID')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $middleName = trim((string) ($_POST['middle_name'] ?? ''));
    $extensionName = trim((string) ($_POST['extension_name'] ?? ''));

    if (!in_array($accountType, ['student', 'admin'], true)) {
        $error = 'Invalid account type.';
    } elseif ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'First name, last name, and a valid email are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (
        $accountType === 'student' &&
        count($blocks) > 0 &&
        !filter_var($_POST['block_id'] ?? null, FILTER_VALIDATE_INT)
    ) {
        $error = 'A valid block ID is required for student accounts.';
    } else {
        try {
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
            $blocks = $pdo->query('SELECT blockID, classBlock FROM tblblock ORDER BY blockID')->fetchAll();
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
                $error = 'The account could not be created. Check the database connection and schema.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Dummy Account</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f3f3f3; margin: 0; padding: 2rem; }
    main { background: #fff; max-width: 32rem; margin: auto; padding: 2rem; border-radius: 0.75rem; }
    h1 { margin-top: 0; }
    label { display: block; margin-top: 1rem; font-weight: bold; }
    input, select { width: 100%; padding: 0.65rem; margin-top: 0.35rem; box-sizing: border-box; }
    button { margin-top: 1.5rem; padding: 0.7rem 1rem; cursor: pointer; }
    .message { margin: 1rem 0; font-weight: bold; }
    .error { color: #b00020; }
    .success { color: #176b2c; }
  </style>
</head>
<body>
  <main>
    <h1>Create Dummy Account</h1>
    <p>This standalone page is for local test-account creation.</p>

    <?php if ($error !== ''): ?>
      <p class="message error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif ($success !== ''): ?>
      <p class="message success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post">
      <label for="account_type">Account type</label>
      <select id="account_type" name="account_type" onchange="toggleStudentFields()" required>
        <option value="student" <?= $accountType === 'student' ? 'selected' : '' ?>>Student</option>
        <option value="admin" <?= $accountType === 'admin' ? 'selected' : '' ?>>Admin</option>
      </select>

      <label for="first_name">First name</label>
      <input id="first_name" name="first_name" value="<?= htmlspecialchars((string) ($_POST['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>

      <label for="last_name">Last name</label>
      <input id="last_name" name="last_name" value="<?= htmlspecialchars((string) ($_POST['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>

      <div id="student-fields">
        <label for="middle_name">Middle name</label>
        <input id="middle_name" name="middle_name" value="<?= htmlspecialchars((string) ($_POST['middle_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

        <label for="extension_name">Extension name</label>
        <input id="extension_name" name="extension_name" value="<?= htmlspecialchars((string) ($_POST['extension_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

        <label for="block_id">Block</label>
        <?php if (count($blocks) > 0): ?>
          <select id="block_id" name="block_id">
            <?php foreach ($blocks as $block): ?>
              <option value="<?= (int) $block['blockID'] ?>" <?= (string) ($block['blockID'] ?? '') === (string) ($_POST['block_id'] ?? '') ? 'selected' : '' ?>>
                <?= htmlspecialchars($block['classBlock'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input id="block_id" name="block_id" type="text" value="A dummy block will be created automatically" readonly>
        <?php endif; ?>
      </div>

      <label for="email">Email</label>
      <input id="email" name="email" type="email" value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>

      <label for="password">Password</label>
      <input id="password" name="password" type="password" minlength="8" required>

      <button type="submit">Create account</button>
    </form>
  </main>
  <script>
    function toggleStudentFields() {
      const isStudent = document.getElementById('account_type').value === 'student';
      document.getElementById('student-fields').hidden = !isStudent;
      document.getElementById('block_id').required = isStudent;
    }

    toggleStudentFields();
  </script>
</body>
</html>
