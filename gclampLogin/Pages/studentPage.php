<?php

session_start();
require_once "../SQL/actions.php";
$std = new User();
$std->nameTagStudent($_SESSION['studentEmail']);
$students = $std->nameTagStudent($_SESSION['studentEmail']);

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
        <?php $isRep = ($student['studentRole'] === 'representative'); ?>
            <button type="submit" style="background-color: red;" <?= $isRep ? '' : 'disabled' ?>>Delete
            </button>
    <?php endforeach; ?>
        

</body>
</html>