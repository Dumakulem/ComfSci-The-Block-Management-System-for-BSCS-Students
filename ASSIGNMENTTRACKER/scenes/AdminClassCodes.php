<?php

if (isset($_POST['adminUsername']) && isset($_POST['adminPassword']) && isset($_POST['adminConfirmPassword'])){

$name = $_POST['adminUsername'];
$pass = $_POST['adminPassword'];
$conPass = $_POST['adminConfirmPassword'];

require_once __DIR__ . '/../database/admin.php';
createAdmin($pdo, $name, $pass);

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
    <title>Class Codes</title>
</head>
<body>

<div style="display:flex; flex-direction:column;">

<form action="../scenes/AdminLoginScene.php">
    <button type="submit">Back</button>
</form>

<h1>CLASS CODES</h1>

<h1><?= $name ?></h1>
<h1><?= $pass ?></h1>
<h1><?= $conPass ?></h1>

</div>
    
</body>
</html>