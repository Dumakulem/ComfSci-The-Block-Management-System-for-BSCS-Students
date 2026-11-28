<?php
require_once __DIR__ . '/dbConnection.php';

function createAdminForOtherDB(PDO $pdo2, $ln, $fn, $mn, $en, $email, $password){
    $pdo2->beginTransaction();
    try {
        $stmt = $pdo2->prepare("INSERT INTO tbladmin (adminLastName, adminFirstName, adminMiddleName, adminExtensionName, adminEmail, adminPassword) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ln, $fn, $mn, $en, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $pdo2->commit();        // commit first...
        return true;           // ...then return
    } catch (Exception $e) {
        $pdo2->rollBack();
        throw $e;
    }
}