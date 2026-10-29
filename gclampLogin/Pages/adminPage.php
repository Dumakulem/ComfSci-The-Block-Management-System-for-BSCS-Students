<?php
// eto ay admin page.
session_start();
require_once "../actions.php";// we require again kasi ginamit ko siya to view the email of the admin.

$admin = new User();
$admins = $admin->nameTagAdmin($_SESSION['adminEmail']);// eto nag create ng object sabay ginamit yung function na nameTagAdmin para ma display yung email ng admin sa page na ito.


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <p>Hello, <?= htmlspecialchars($admins['adminEmail'])?></p>// eto yung display na email.
</body>
</html>