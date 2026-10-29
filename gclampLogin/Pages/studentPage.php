<?php

session_start();
require_once "../SQL/actions.php";
$std = new User();
$student = $std->nameTagStudent($_SESSION['studentEmail']);

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>

    <p>Hello, <?= htmlspecialchars($student['studentEmail']); ?></p>

</body>
</html>