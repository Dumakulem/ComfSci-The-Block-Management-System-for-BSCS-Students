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

function displayClasses(PDO $pdo){
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->query("SELECT * FROM blocks");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function createAssignment(PDO $pdo, $classCode, $prof, $title, $subject, ?array $file, $des, $deadline) {
    
    $fileData = null;
    $fileName = null;
    $fileType = null;

    // $_FILES is set even when nothing was chosen, so check the error code
    if ($file !== null && $file['error'] === UPLOAD_ERR_OK) {
        if ($file['size'] > 16000000) {
            throw new Exception("File is too large (max about 16 MB).");
        }
        $fileData = file_get_contents($file['tmp_name']);
        $fileName = basename($file['name']);
        $fileType = mime_content_type($file['tmp_name']);
    }

    $sql = "INSERT INTO assignments
            (classCode, prof, as_title, as_subject, as_file, as_file_name, as_file_type, as_description, as_deadline)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(1, $classCode, PDO::PARAM_INT);
    $stmt->bindValue(2, $prof);
    $stmt->bindValue(3, $title);
    $stmt->bindValue(4, $subject);
    $stmt->bindValue(5, $fileData, $fileData === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
    $stmt->bindValue(6, $fileName);
    $stmt->bindValue(7, $fileType);
    $stmt->bindValue(8, $des);
    $stmt->bindValue(9, $deadline);
    $stmt->execute();
    
}

function displayAssignments(PDO $pdo, $classCode){
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM assignments WHERE classCode = ?");
        $stmt->execute([$classCode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);      // commit first...           // ...then return
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function getAssignment(PDO $pdo, int $asId, $classCode): ?array {
    $stmt = $pdo->prepare(
        "SELECT as_id, classCode, prof, as_title, as_subject, as_file_name, as_file_type,
                as_description, as_deadline, created_at
         FROM assignments
         WHERE as_id = ? AND classCode = ?"
    );
    $stmt->execute([$asId, $classCode]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>