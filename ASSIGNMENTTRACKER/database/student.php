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

?>