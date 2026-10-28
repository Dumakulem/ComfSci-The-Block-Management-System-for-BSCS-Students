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

function loginAdmin(PDO $pdo, string $name, string $password){
    // 1. Look for the name
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE adminName = ? LIMIT 1");
    $stmt->execute([$name]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Name doesn't exist
    if (!$admin) {
        return false;
    }

    // 3. Name exists, so check the password against the stored hash
    if (!password_verify($password, $admin['adminPassword'])) {
        return false;
    }

    // 4. Both correct
    return true;
}

function createClass(PDO $pdo, string $adminName, int $classCode, string $blockName){
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO blocks (adminName, classCode, blockName) VALUES (?, ?, ?)");
        $stmt->execute([$adminName, $classCode, $blockName]);
        $pdo->commit();        // commit first...
        return true;           // ...then return
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function displayClasses(PDO $pdo, string $adminName){
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM blocks WHERE adminName = ?");
        $stmt->execute([$adminName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);      // commit first...           // ...then return
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function createAssignment(PDO $pdo, int $classCode, 
                          string $title, string $subject,
                          $file, string $description, $deadline){
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO assignments (classCode, as_title, as_subject, as_file, as_description, as_deadline) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$classCode, $title, $subject, $file, $description, $deadline]);
        $pdo->commit();
        return true; 
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

}

?>