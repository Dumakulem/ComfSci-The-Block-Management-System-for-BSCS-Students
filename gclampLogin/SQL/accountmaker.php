<?php

require_once "database.php";

$pdo = new Database();
$pdos = $pdo->connect();

$user = "admin@123";
$password = "123456";
$role = "admin";

$stmt = $pdos->prepare("INSERT INTO admintbl (adminEmail, adminPassword, role) VALUES (?, ?, ?)");
$hashed = password_hash($password, PASSWORD_BCRYPT);
$stmt->execute([$user, $hashed, $role]);
$student = $stmt->fetch();


?>