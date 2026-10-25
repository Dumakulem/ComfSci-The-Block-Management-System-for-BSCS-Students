<?php
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=blockwidewebappdb;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "Connected!";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}