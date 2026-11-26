<?php

require_once __DIR__ . '/dbConnection.php';




//overdue setter






function getStudent(PDO $pdo, int $id){
    $stmt = $pdo->prepare("SELECT studentID, studentLastName, studentFirstName, studentMiddleName, studentExtensionName, studentEmail, blockID AS classCode FROM tblstudent WHERE studentID = ? AND archivedAt IS NULL LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getStudentAssignment(PDO $pdo, $as_id, $blockID, $studentId): ?array {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM at_studentassignments
         WHERE as_id = ? AND blockID = ? AND studentID = ?"
    );
    $stmt->execute([(int) $as_id, $blockID, (int)$studentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getStudentFiles(PDO $pdo, $studentId, $asTitle, $blockID, $as_id): array {
    $stmt = $pdo->prepare(
        "SELECT id, file_name, OCTET_LENGTH(file_data) AS file_size
         FROM at_student_as_files
         WHERE studentID = :sid AND as_id = :aid AND as_title = :title AND blockID = :code
         ORDER BY uploaded_at"
    );
    $stmt->execute([':sid' => $studentId, ':aid' => (int) $as_id, ':title' => $asTitle, ':code' => $blockID]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function assignmentStatusChecker(PDO $pdo, $blockID, $studentId, $as_id): ?string {
    $stmt = $pdo->prepare(
        "SELECT as_status FROM at_studentassignments
         WHERE as_id = ? AND blockID = ? AND studentID = ?"
    );
    $stmt->execute([(int)$as_id, (int)$blockID, (int)$studentId]);
    $status = $stmt->fetchColumn();
    return $status === false ? null : $status;
}

function assignmentChangeStatus(PDO $pdo, $blockID, $studentId, $as_id, $status): void {
    $stmt = $pdo->prepare(
        "UPDATE at_studentassignments SET as_status = ?, edited_at = CASE WHEN ? IN ('Completed', 'Late') THEN NOW() ELSE edited_at END
         WHERE as_id = ? AND blockID = ? AND studentID = ?"
    );
    $stmt->execute([$status, $status, (int)$as_id, (int)$blockID, (int)$studentId]);
}

function addStudentFiles(PDO $pdo, $studentId, $asTitle, $blockID, ?array $files, $as_id): void {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO at_student_as_files (studentID, as_id, as_title, blockID, file_name, file_data)
             VALUES (:sid, :aid, :title, :code, :name, :data)"
        );
        foreach ($files ?? [] as $f) {
            if (isset($f['error']) && $f['error'] !== UPLOAD_ERR_OK) continue;   // skip empty pickers
            $stmt->execute([
                ':sid'   => $studentId,
                ':aid'   => (int) $as_id,
                ':title' => $asTitle,
                ':code'  => $blockID,
                ':name'  => $f['name'],
                ':data'  => file_get_contents($f['tmp_name']),
            ]);
        }

        // change the status only after the files were saved
        $status = assignmentStatusChecker($pdo, $blockID, $studentId, $as_id);
        if ($status === 'Pending') {
            assignmentChangeStatus($pdo, $blockID, $studentId, $as_id, 'Completed');
        } else if ($status === 'Overdue') {
            assignmentChangeStatus($pdo, $blockID, $studentId, $as_id, 'Late');
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function addAllGroupStudentFiles(PDO $pdo, $leaderID, $asTitle, $blockID, ?array $files, $as_id, array $members): void {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO at_student_as_files (studentID, as_id, as_title, blockID, file_name, file_data)
             VALUES (:sid, :aid, :title, :code, :name, :data)"
        );
        foreach ($files ?? [] as $f) {
            if (isset($f['error']) && $f['error'] !== UPLOAD_ERR_OK) continue;   // skip empty pickers
            $stmt->execute([
                ':sid'   => $leaderID,
                ':aid'   => (int) $as_id,
                ':title' => $asTitle,
                ':code'  => $blockID,
                ':name'  => $f['name'],
                ':data'  => file_get_contents($f['tmp_name']),
            ]);
        }

        // change the status only after the files were saved
        foreach($members as $student){
            $status = assignmentStatusChecker($pdo, $blockID, (int)$student['studentID'], $as_id);
        if ($status === 'Pending') {
            assignmentChangeStatus($pdo, $blockID, (int)$student['studentID'], $as_id, 'Completed');
        } else if ($status === 'Overdue') {
            assignmentChangeStatus($pdo, $blockID, (int)$student['studentID'], $as_id, 'Late');
        }
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function deleteAllGroupStudentFile(PDO $pdo, $fileId, $blockID, $leaderID, $as_id, array $members){
    $pdo->beginTransaction();
    try{

    $stmtt= $pdo->prepare("DELETE FROM at_student_as_files WHERE id = ? AND blockID = ? AND studentID = ? AND as_id = ?");
    $stmtt->execute([(int) $fileId, (int) $blockID, (int) $leaderID, (int) $as_id]);

    $pdo->commit();

    foreach($members as $member){
        assignmentChangeStatus($pdo, $blockID, $member['studentID'], $as_id, "Pending");
    }
    
    }catch(Exception $e){
        $pdo->rollBack();
        throw $e;
    }
}

function deleteFile(PDO $pdo, $fileId, $blockID, $studentID, $as, $as_id){
    $pdo->beginTransaction();
    try{

    $stmtt= $pdo->prepare("DELETE FROM at_student_as_files WHERE id = ? AND blockID = ? AND studentID = ? AND as_id = ?");
    $stmtt->execute([(int) $fileId, (int) $blockID, (int) $studentID, (int) $as_id]);

    $pdo->commit();

    if (isStudentPassedEmpty($pdo, $blockID, $studentID, $as, $as_id) == false){
        assignmentChangeStatus($pdo, $blockID, $studentID, $as_id, "Pending");
    };
    }catch(Exception $e){
        $pdo->rollBack();
        throw $e;
    }
}

function isStudentPassedEmpty(PDO $pdo, $blockID, $studentID, $as, $as_id){
    $pdo->beginTransaction();
    try{

    $stmt= $pdo->prepare("SELECT 1 FROM at_student_as_files WHERE blockID = ? AND studentID = ? AND as_title = ? AND as_id = ? LIMIT 1");
    $stmt->execute([(int) $blockID, (int) $studentID, (string) $as, (int) $as_id]);
    
    $result = $stmt->fetchColumn();

    $pdo->commit();

    return $result;
    }catch(Exception $e){
        $pdo->rollBack();
        throw $e;
    }
}

function getStudentGroupAs(PDO $pdo, int $blockID, int $studentID){
    $stmt = $pdo->prepare("SELECT * FROM at_studentassignments WHERE blockID = ? AND studentID = ? AND is_group_as = ?");
    $stmt->execute([$blockID, $studentID, (int) 1]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStudentGrouped(PDO $pdo, int $studentID, int $as_id){
    $stmt = $pdo->prepare("SELECT * FROM at_groupmembers WHERE studentID = ? AND as_id = ? LIMIT 1");
    $stmt->execute([$studentID, $as_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getYourGroup(PDO $pdo, int $groupID){
    $stmt = $pdo->prepare("SELECT * FROM at_groupas WHERE groupID = ? LIMIT 1");
    $stmt->execute([$groupID]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}




