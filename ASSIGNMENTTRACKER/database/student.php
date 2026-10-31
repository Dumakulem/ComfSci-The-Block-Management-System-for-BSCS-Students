<?php

require_once __DIR__ . '/dbConnection.php';

function createStudent(PDO $pdo, int $id, int $classCode, string $lastName,
                       string $firstName, string $middleName, string $extName,
                       string $email, string $password, string $rep){

    $pdo->beginTransaction();
    $isRep = $rep === 'Yes' ? 1 : 0;
    try {
        $stmt = $pdo->prepare("INSERT INTO students (studentID, classCode,
                                                     studentLastName, studentFirstName, 
                                                     studentMiddleName, studentExtName, studentEmail, 
                                                     studentPassword, studentIsClassRep) 
                                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $classCode, $lastName,
                        $firstName, $middleName, $extName,
                        $email, password_hash($password, PASSWORD_DEFAULT),
                        $isRep]);
        $pdo->commit();        // commit first...
        return true;           // ...then return
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

}

function loginStudent(PDO $pdo, $id, string $password){
    // 1. Look for the name
    $stmt = $pdo->prepare("SELECT * FROM students WHERE studentID = ? LIMIT 1");
    $stmt->execute([$id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Name doesn't exist
    if (!$student) {
        return false;
    }

    // 3. Name exists, so check the password against the stored hash
    if (!password_verify($password, $student['studentPassword'])) {
        return false;
    }

    // 4. Both correct
    return true;
}

function getStudent(PDO $pdo, int $id){
    $stmt = $pdo->prepare("SELECT * FROM students WHERE studentID = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;   // one row, or null if not found
}

?>