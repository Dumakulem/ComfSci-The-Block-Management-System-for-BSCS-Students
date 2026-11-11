<?php

require_once __DIR__ . '/dbConnection.php';

//overdue setter


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

function getStudentAssignment(PDO $pdo, string $astitle, $classCode, $studentId): ?array {
    $stmt = $pdo->prepare(
        "SELECT as_id, classCode, studentID, as_title, as_subject, as_status,
                as_description, as_deadline, created_at
         FROM studentassignments
         WHERE as_title = ? AND classCode = ? AND studentID = ?"
    );
    $stmt->execute([$astitle, $classCode, (int)$studentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getStudentFiles(PDO $pdo, $studentId, $asTitle, $classCode): array {
    $stmt = $pdo->prepare(
        "SELECT id, file_name, OCTET_LENGTH(file_data) AS file_size
         FROM student_as_files
         WHERE studentID = :sid AND as_title = :title AND classCode = :code
         ORDER BY uploaded_at"
    );
    $stmt->execute([':sid' => $studentId, ':title' => $asTitle, ':code' => $classCode]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function assignmentStatusChecker(PDO $pdo, $classCode, $studentId, $as_id): ?string {
    $stmt = $pdo->prepare(
        "SELECT as_status FROM studentassignments
         WHERE as_id = ? AND classCode = ? AND studentID = ?"
    );
    $stmt->execute([(int)$as_id, (int)$classCode, (int)$studentId]);
    $status = $stmt->fetchColumn();
    return $status === false ? null : $status;
}

function assignmentChangeStatus(PDO $pdo, $classCode, $studentId, $as_id, $status): void {
    $stmt = $pdo->prepare(
        "UPDATE studentassignments SET as_status = ?
         WHERE as_id = ? AND classCode = ? AND studentID = ?"
    );
    $stmt->execute([$status, (int)$as_id, (int)$classCode, (int)$studentId]);
}

function addStudentFiles(PDO $pdo, $studentId, $asTitle, $classCode, ?array $files, $as_id): void {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO student_as_files (studentID, as_title, classCode, file_name, file_data)
             VALUES (:sid, :title, :code, :name, :data)"
        );
        foreach ($files ?? [] as $f) {
            if (isset($f['error']) && $f['error'] !== UPLOAD_ERR_OK) continue;   // skip empty pickers
            $stmt->execute([
                ':sid'   => $studentId,
                ':title' => $asTitle,
                ':code'  => $classCode,
                ':name'  => $f['name'],
                ':data'  => file_get_contents($f['tmp_name']),
            ]);
        }

        // change the status only after the files were saved
        $status = assignmentStatusChecker($pdo, $classCode, $studentId, $as_id);
        if ($status === 'Pending') {
            assignmentChangeStatus($pdo, $classCode, $studentId, $as_id, 'Completed');
        } else if ($status === 'Overdue') {
            assignmentChangeStatus($pdo, $classCode, $studentId, $as_id, 'Late');
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
?>