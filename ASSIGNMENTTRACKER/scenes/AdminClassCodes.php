<?php
session_start();

if (!isset($_SESSION['name'])) {
    header('Location: AdminLoginScene.php');
    exit;
}

$name = $_SESSION['name'];

if(isset($_POST['back'])){
    session_start();
    session_destroy();
    header('Location: AdminLoginScene.php');
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

<form method="POST">
    <button name="back" type="submit">Back</button>
</form>

<h1>CLASS CODES</h1>
<h1><?= $name ?></h1>


</div>
    
</body>
</html>