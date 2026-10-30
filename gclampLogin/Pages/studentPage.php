<?php


session_start();

require_once "../SQL/actions.php";
$std = new User();
$students = $std->nameTagStudent();

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <?php foreach ($students as $student): ?>
        <p>Hello, <?= htmlspecialchars($student['studentEmail']); ?></p><br>
        <p>Role, <?= htmlspecialchars($student['studentRole']); ?></p><br>
        <?php $isRep = ($student['studentRole'] === 'Representative'); ?>
            <button type="submit" style="background-color: red;" <?= $isRep ? '' : 'disabled' ?>>Delete
            </button>
    <?php endforeach; ?>
    <form action="../Pages/studentPage.php" method="post">
        <button type="submit" name="logout">Logout</button>
    </form>

    <?php

    if (isset($_COOKIE['type']) && $_COOKIE['type'] === 'admin') {
        echo '<p>Welcome, Admin!</p>';
    } else {
        echo '<p>Welcome, Student!</p>';
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST'){

        if (isset($_POST['logout'])) {
        session_destroy();
        header("Location: ../Pages/loginPage.php");
        exit();
    }
    }

    ?>
</body>
</html>