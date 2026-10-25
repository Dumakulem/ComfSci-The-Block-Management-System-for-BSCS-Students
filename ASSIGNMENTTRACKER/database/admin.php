<?php

require_once __DIR__ . '/dbConnection.php';

function createAdmin(PDO $pdo,$name, $password){
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO admins (adminName, adminPassword) VALUES (?, ?)");
        $stmt->execute([$name, password_hash($password, PASSWORD_DEFAULT)]);
        $pdo->commit();        // commit first...
        return true;           // ...then return
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

?>