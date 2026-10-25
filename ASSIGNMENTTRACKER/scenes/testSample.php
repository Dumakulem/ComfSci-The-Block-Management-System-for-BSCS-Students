<?php

if (isset($_POST['adminUsername']) && isset($_POST['adminPassword']) && isset($_POST['adminConfirmPassword'])){

$name = $_POST['adminUsername'];
$pass = $_POST['adminPassword'];
$conPass = $_POST['adminConfirmPassword'];

} else {
    header('Location: AdminCreateAccount.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1>IT WORKS</h1>
    <h1><?= $name ?></h1>
    <h1><?= $pass ?></h1>
    <h1><?= $conPass ?></h1>
</body>
</html>