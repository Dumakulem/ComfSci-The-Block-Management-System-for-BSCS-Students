<?php

require_once __DIR__ . '/dbConnection.php';
















function createAssignment(PDO $pdo, $blockID, $prof, $title, $subject, array $students, ?array $files, $des, $deadline) {

    // 1. Turn $_FILES['at_files'] into a normal list of at_files
    $uploaded = [];
    if ($files !== null && is_array($files['name'])) {
        foreach ($files['name'] as $i => $originalName) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;   // skips the empty picker

            if ($files['size'][$i] > 16000000) {
                throw new Exception("$originalName is too large (max about 16 MB).");
            }
            $tmp = $files['tmp_name'][$i];
            $uploaded[] = [
                'name' => basename($originalName),
                'type' => mime_content_type($tmp),
                'data' => file_get_contents($tmp),
            ];
        }
    }

    // 2. Insert everything together, or nothing
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO at_assignments (blockID, prof, as_title, as_subject, as_description, as_deadline)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bindValue(1, $blockID, PDO::PARAM_INT);
        $stmt->bindValue(2, $prof);
        $stmt->bindValue(3, $title);
        $stmt->bindValue(4, $subject);
        $stmt->bindValue(5, $des);
        $stmt->bindValue(6, $deadline);
        $stmt->execute();

        $st = $pdo->prepare(
            "INSERT INTO at_studentassignments (as_id, blockID, studentID, as_title, as_subject, as_description, as_deadline)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $as_id = ['as_id' => (int) $pdo->lastInsertId()];

        foreach ($students as $student) {
            $st->bindValue(1, $as_id['as_id'], PDO::PARAM_INT);
            $st->bindValue(2, $blockID, PDO::PARAM_INT);
            $st->bindValue(3, (int)$student['studentID'], PDO::PARAM_INT);
            $st->bindValue(4, $title);
            $st->bindValue(5, $subject);
            $st->bindValue(6, $des);
            $st->bindValue(7, $deadline);
            $st->execute();
        }

        $fs = $pdo->prepare(
            "INSERT INTO at_files (as_id, as_title, blockID, as_file, as_file_name, as_file_type)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($uploaded as $f) {
            $fs->bindValue(1, $as_id['as_id'], PDO::PARAM_INT);
            $fs->bindValue(2, $title);
            $fs->bindValue(3, $blockID, PDO::PARAM_INT);
            $fs->bindValue(4, $f['data'], PDO::PARAM_LOB);   // the actual file bytes
            $fs->bindValue(5, $f['name']);
            $fs->bindValue(6, $f['type']);
            $fs->execute();
        }

        $pdo->commit();
    } catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
}

function createAsOnlyForStudent(PDO $pdo, int $as_id, int $blockID, array $studentIDS, string $as_title, string $as_subject, string $as_description, $as_deadline, int $isGroupAs){
$pdo->beginTransaction();
try{

$stmt = $pdo->prepare(
            "INSERT INTO at_studentassignments (as_id, blockID, studentID, as_title, as_subject, as_description, as_deadline, is_group_as)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

foreach($studentIDS as $id){
$stmt->execute([$as_id, $blockID, $id, $as_title, $as_subject, $as_description, $as_deadline, $isGroupAs]);
}

$pdo->commit();

}catch(Exception $e){
    $pdo->rollBack();
    throw $e;
}
}

function getGroupAs($pdo, $as_id, $groupName){
    $stmt = $pdo->prepare("SELECT * FROM at_groupas WHERE as_id = ? AND group_name = ? LIMIT 1");
    $stmt->execute([(int)$as_id, (string)$groupName]);
    return $stmt->fetch();
}

function createMembersAssignment(PDO $pdo, $blockID, $groupName, $leader, ?array $members, $assignment) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO at_groupas (as_id, group_name) VALUES (?, ?)");
        $stmt->execute([(int)$assignment['as_id'], $groupName]);

        $groupAs = getGroupAs($pdo, (int)$assignment['as_id'], $groupName);

        $stmt = $pdo->prepare("INSERT INTO at_groupmembers (groupID, studentID, is_leader, as_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([(int)$groupAs['groupID'], (int) $leader, 1, (int)$assignment['as_id']]);

        foreach($members as $member){
            $stmt = $pdo->prepare("INSERT INTO at_groupmembers (groupID, studentID, is_leader, as_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([(int)$groupAs['groupID'], (int) $member, 0, (int)$assignment['as_id']]); 
        }
        $pdo->commit();
    } catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
}

function getAllGroups(PDO $pdo, $as_id){
        $stmt = $pdo->prepare("SELECT * FROM at_groupas WHERE as_id = ?");
        $stmt->execute([$as_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);      // commit first...           // ...then retur
}

function getAllMembers(PDO $pdo, $groupID){
        $stmt = $pdo->prepare("SELECT * FROM at_groupmembers WHERE groupID = ?");
        $stmt->execute([$groupID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);      // commit first...           // ...then return
}

function getLeaderID(PDO $pdo, $groupID){
    $stmt = $pdo->prepare("SELECT * FROM at_groupmembers WHERE groupID = ? AND is_leader = 1 LIMIT 1");
    $stmt->execute([$groupID]);
    return $stmt->fetch(PDO::FETCH_ASSOC); 
}

function createGroupAssignment(PDO $pdo, $blockID, $prof, $title, $subject, array $students, ?array $files, $des, $deadline) {

    // 1. Turn $_FILES['at_files'] into a normal list of at_files
    $uploaded = [];
    if ($files !== null && is_array($files['name'])) {
        foreach ($files['name'] as $i => $originalName) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;   // skips the empty picker

            if ($files['size'][$i] > 16000000) {
                throw new Exception("$originalName is too large (max about 16 MB).");
            }
            $tmp = $files['tmp_name'][$i];
            $uploaded[] = [
                'name' => basename($originalName),
                'type' => mime_content_type($tmp),
                'data' => file_get_contents($tmp),
            ];
        }
    }

    // 2. Insert everything together, or nothing
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO at_assignments (blockID, prof, as_title, as_subject, as_description, as_deadline, is_group_as)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bindValue(1, $blockID, PDO::PARAM_INT);
        $stmt->bindValue(2, $prof);
        $stmt->bindValue(3, $title);
        $stmt->bindValue(4, $subject);
        $stmt->bindValue(5, $des);
        $stmt->bindValue(6, $deadline);
        $stmt->bindValue(7, (int) 1);
        $stmt->execute();
        $as_id = (int) $pdo->lastInsertId();

        $fs = $pdo->prepare(
            "INSERT INTO at_files (as_id, as_title, blockID, as_file, as_file_name, as_file_type)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($uploaded as $f) {
            $fs->bindValue(1, $as_id, PDO::PARAM_INT);
            $fs->bindValue(2, $title);
            $fs->bindValue(3, $blockID, PDO::PARAM_INT);
            $fs->bindValue(4, $f['data'], PDO::PARAM_LOB);   // the actual file bytes
            $fs->bindValue(5, $f['name']);
            $fs->bindValue(6, $f['type']);
            $fs->execute();
        }

        $pdo->commit();
    } catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
}

function displayAssignments(PDO $pdo, $blockID){
        $stmt = $pdo->prepare("SELECT * FROM at_assignments WHERE blockID = ? AND is_group_as = ?");
        $stmt->execute([$blockID, (int) 0]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);      // commit first...           // ...then return
}

function displayGroupAssignments(PDO $pdo, $blockID){
    $stmt = $pdo->prepare("SELECT * FROM at_assignments WHERE blockID = ? AND is_group_as = ?");
    $stmt->execute([$blockID, (int) 1]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);      // commit first...           // ...then return
}

function getAssignment(PDO $pdo, int $asId, $blockID): ?array {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM at_assignments
         WHERE as_id = ? AND blockID = ?"
    );
    $stmt->execute([$asId, $blockID]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getAllStudents(PDO $pdo, $classCode): array {
    $stmt = $pdo->prepare("SELECT studentID, studentLastName, studentFirstName FROM tblstudent WHERE blockID = ? AND archivedAt IS NULL");
    $stmt->execute([$classCode]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ifHasGroup(PDO $pdo, $studentID, $as_id){
    $stmt = $pdo->prepare("SELECT * FROM at_groupmembers WHERE studentID = ? AND as_id = ? LIMIT 1");
    $stmt->execute([$studentID, $as_id]);
    return $stmt->fetch();
}

function getAsFile(PDO $pdo, $as_id, $blockID): array {
    $stmt = $pdo->prepare(
        "SELECT sa_id, as_file_name, as_file_type, LENGTH(as_file) AS as_file_size
         FROM at_files
         WHERE as_id = ? AND blockID = ?"
    );
    $stmt->execute([$as_id, $blockID]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStudentName(PDO $pdo, $classCode, $id){
    $stmt = $pdo->prepare("SELECT studentLastName, studentFirstName, studentMiddleName FROM tblstudent WHERE blockID = ? AND archivedAt IS NULL AND studentID = ?");
    $stmt->execute([$classCode, (int)$id]);
    $name = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$name) return 'Unknown student';
    return trim($name['studentLastName'] . ", " . $name['studentFirstName'] . " " . ($name['studentMiddleName'] ?? ''));
}

function getAllStudentAssignments(PDO $pdo, $as_id, $blockID){
    $stmt = $pdo->prepare("SELECT * 
                            FROM at_studentassignments 
                            WHERE blockID = ? AND as_id = ?");
    $stmt->execute([$blockID, $as_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function asCreateForNewAddedStudent(PDO $pdo, int $studentId, $blockID): int {
    $stmt = $pdo->prepare(
        "INSERT INTO at_studentassignments
            (as_id, blockID, studentID, as_title, as_subject, as_description, as_deadline, is_group_as)
         SELECT a.as_id, a.blockID, ?, a.as_title, a.as_subject, a.as_description, a.as_deadline, a.is_group_as
         FROM at_assignments a
         WHERE a.blockID = ?
           AND NOT EXISTS (
               SELECT 1 FROM at_studentassignments sa
               WHERE sa.studentID = ? AND sa.as_id = a.as_id
           )"
    );
    $stmt->execute([$studentId, $blockID, $studentId]);
    return $stmt->rowCount();
}

function deleteAs(PDO $pdo, $as_id, $blockID){
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM at_assignments WHERE as_id = ? AND blockID = ?");
        $stmt->execute([(int)$as_id, (int)$blockID]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function downloadFile(PDO $pdo, $fileId, $blockID, bool $inline = false): void {
    // blockID in the WHERE clause stops people opening at_files from other classes
    $stmt = $pdo->prepare(
        "SELECT as_file, as_file_name, as_file_type
         FROM at_files
         WHERE sa_id = ? AND blockID = ?"
    );
    $stmt->execute([$fileId, $blockID]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || $row['as_file'] === null) {
        http_response_code(404);
        exit('File not found.');
    }

    $fileName = str_replace(['"', "\r", "\n", '/', '\\'], '', $row['as_file_name'] ?: 'download');
    $type     = $row['as_file_type'] ?: 'application/octet-stream';

    // only PDFs and images are safe to open in the browser; everything else downloads
    $canPreview  = $type === 'application/pdf' || str_starts_with($type, 'image/');
    $disposition = ($inline && $canPreview) ? 'inline' : 'attachment';

    while (ob_get_level()) {
        ob_end_clean();   // nothing may be sent before the file
    }

    header('Content-Type: ' . $type);
    header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
    header('Content-Length: ' . strlen($row['as_file']));
    header('X-Content-Type-Options: nosniff');

    echo $row['as_file'];
    exit;
}

function downloadStudentFile(PDO $pdo, int $fileId, $blockID, ?int $studentId = null, bool $inline = false): void {
    $sql = "SELECT file_name, file_data FROM at_student_as_files WHERE id = ? AND blockID = ?";
    $params = [$fileId, $blockID];

    // a student may only download their own at_files; an admin passes null to skip this check
    if ($studentId !== null) {
        $sql .= " AND studentID = ?";
        $params[] = $studentId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || $row['file_data'] === null) {
        http_response_code(404);
        exit('File not found.');
    }

    $fileName = str_replace(['"', "\r", "\n", '/', '\\'], '', $row['file_name'] ?: 'download');

    // there is no type column, so detect it from the file's contents
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $type  = $finfo->buffer($row['file_data']) ?: 'application/octet-stream';

    $canPreview  = $type === 'application/pdf' || str_starts_with($type, 'image/');
    $disposition = ($inline && $canPreview) ? 'inline' : 'attachment';

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: ' . $type);
    header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
    header('Content-Length: ' . strlen($row['file_data']));
    header('X-Content-Type-Options: nosniff');

    echo $row['file_data'];
    exit;
}

function isAdminHasFeedback(PDO $pdo, int $sa_id){
        $st = $pdo->prepare("SELECT 1 FROM at_feedback WHERE sa_id = ? LIMIT 1");
        $st->execute([$sa_id]);
        $result = $st->fetchColumn();
        return $result;
}

function createFeedback(PDO $pdo, int $sa_id, int $adminID, $at_feedback, int $score, int $scoreOver){
    $pdo->beginTransaction();
    try{
        $stmt = null;

        if(isAdminHasFeedback($pdo, $sa_id) == true){
            $stmt = $pdo->prepare("UPDATE at_feedback SET feedback = ?, score = ?, score_over = ? WHERE sa_id = ?");
            $stmt->execute([$at_feedback, $score, $scoreOver, $sa_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO at_feedback (sa_id, adminID, feedback, score, score_over) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$sa_id, $adminID, $at_feedback, $score, $scoreOver]);
        }

        $pdo->commit();
    }catch(Exception $e){
        $pdo->rollBack();
        throw $e;
    }
}

function getFeedback(PDO $pdo, int $sa_id){
    $st = $pdo->prepare("SELECT * FROM at_feedback WHERE sa_id = ? LIMIT 1");
    $st->execute([$sa_id]);
    return $st->fetch();
}

function deleteFeedback(PDO $pdo, int $id){
    $pdo->beginTransaction();
    try{

    $st = $pdo->prepare("DELETE FROM at_feedback WHERE feedback_id = ?");
    $st->execute([$id]);

    $pdo->commit();
    }catch(Exception $e){
        $pdo->rollBack();
        throw $e;
    }
}

function getAdminInfo(PDO $pdo, int $adminID): ?array {
    $stmt = $pdo->prepare("SELECT adminID, CONCAT(adminFirstName, ' ', adminLastName) AS adminName FROM tbladmin WHERE adminID = ? LIMIT 1");
    $stmt->execute([$adminID]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
