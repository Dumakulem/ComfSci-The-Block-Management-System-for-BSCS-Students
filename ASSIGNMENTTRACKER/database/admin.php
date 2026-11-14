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
        $stmt = $pdo->query("SELECT * FROM blocks");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createAssignment(PDO $pdo, $classCode, $prof, $title, $subject, array $students, ?array $files, $des, $deadline) {

    // 1. Turn $_FILES['files'] into a normal list of files
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
            "INSERT INTO assignments (classCode, prof, as_title, as_subject, as_description, as_deadline)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bindValue(1, $classCode, PDO::PARAM_INT);
        $stmt->bindValue(2, $prof);
        $stmt->bindValue(3, $title);
        $stmt->bindValue(4, $subject);
        $stmt->bindValue(5, $des);
        $stmt->bindValue(6, $deadline);
        $stmt->execute();

        $st = $pdo->prepare(
            "INSERT INTO studentassignments (as_id, classCode, studentID, as_title, as_subject, as_description, as_deadline)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $getAs = $pdo->prepare(
        "SELECT as_id
         FROM assignments
         WHERE as_title = ? AND classCode = ? AND prof = ?"
        );
    $getAs->execute([$title, $classCode, $prof]);
    $as_id = $getAs->fetch(PDO::FETCH_ASSOC) ?: null;

        foreach ($students as $student) {
            $st->bindValue(1, $as_id['as_id'], PDO::PARAM_INT);
            $st->bindValue(2, $classCode, PDO::PARAM_INT);
            $st->bindValue(3, (int)$student['studentID'], PDO::PARAM_INT);
            $st->bindValue(4, $title);
            $st->bindValue(5, $subject);
            $st->bindValue(6, $des);
            $st->bindValue(7, $deadline);
            $st->execute();
        }

        $fs = $pdo->prepare(
            "INSERT INTO files (as_title, classCode, as_file, as_file_name, as_file_type)
             VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($uploaded as $f) {
            $fs->bindValue(1, $title);
            $fs->bindValue(2, $classCode, PDO::PARAM_INT);
            $fs->bindValue(3, $f['data'], PDO::PARAM_LOB);   // the actual file bytes
            $fs->bindValue(4, $f['name']);
            $fs->bindValue(5, $f['type']);
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

function createMembersAssignment(PDO $pdo, $classCode, $groupName, $leader, ?array $members, $assignment) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO groupas (as_id, group_name) VALUES (?, ?)");
        $stmt->execute([(int)$assignment['as_id'], $groupName]);

        $stmt = $pdo->prepare("INSERT INTO groupmembers (group_name, studentID, is_leader) VALUES (?, ?, ?)");
        $stmt->execute([$groupName, (int) $leader, 1]);

        foreach($members as $member){
            $stmt = $pdo->prepare("INSERT INTO groupmembers (group_name, studentID, is_leader) VALUES (?, ?, ?)");
            $stmt->execute([$groupName, (int) $member, 0]); 
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
        $stmt = $pdo->prepare("SELECT * FROM groupas WHERE as_id = ?");
        $stmt->execute([$as_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);      // commit first...           // ...then retur
}

function getAllMembers(PDO $pdo, $groupName){
        $stmt = $pdo->prepare("SELECT * FROM groupmembers WHERE group_name = ?");
        $stmt->execute([$groupName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);      // commit first...           // ...then return
}

function createGroupAssignment(PDO $pdo, $classCode, $prof, $title, $subject, array $students, ?array $files, $des, $deadline) {

    // 1. Turn $_FILES['files'] into a normal list of files
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
            "INSERT INTO assignments (classCode, prof, as_title, as_subject, as_description, as_deadline, is_group_as)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bindValue(1, $classCode, PDO::PARAM_INT);
        $stmt->bindValue(2, $prof);
        $stmt->bindValue(3, $title);
        $stmt->bindValue(4, $subject);
        $stmt->bindValue(5, $des);
        $stmt->bindValue(6, $deadline);
        $stmt->bindValue(7, (int) 1);
        $stmt->execute();

        $fs = $pdo->prepare(
            "INSERT INTO files (as_title, classCode, as_file, as_file_name, as_file_type)
             VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($uploaded as $f) {
            $fs->bindValue(1, $title);
            $fs->bindValue(2, $classCode, PDO::PARAM_INT);
            $fs->bindValue(3, $f['data'], PDO::PARAM_LOB);   // the actual file bytes
            $fs->bindValue(4, $f['name']);
            $fs->bindValue(5, $f['type']);
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

function displayAssignments(PDO $pdo, $classCode){
        $stmt = $pdo->prepare("SELECT * FROM assignments WHERE classCode = ? AND is_group_as = ?");
        $stmt->execute([$classCode, (int) 0]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);      // commit first...           // ...then return
}

function displayGroupAssignments(PDO $pdo, $classCode){
        $stmt = $pdo->prepare("SELECT * FROM assignments WHERE classCode = ? AND is_group_as = ?");
        $stmt->execute([$classCode, (int) 1]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);      // commit first...           // ...then return
}

function getAssignment(PDO $pdo, int $asId, $classCode): ?array {
    $stmt = $pdo->prepare(
        "SELECT as_id, classCode, prof, as_title, as_subject,
                as_description, as_deadline, created_at
         FROM assignments
         WHERE as_id = ? AND classCode = ?"
    );
    $stmt->execute([$asId, $classCode]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getAllStudents(PDO $pdo, $classCode): array {
    $stmt = $pdo->prepare("SELECT studentID, studentLastName, studentFirstName FROM students WHERE classCode = ?");
    $stmt->execute([$classCode]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAsFile(PDO $pdo, $as_title, $classCode): array {
    $stmt = $pdo->prepare(
        "SELECT sa_id, as_file_name, as_file_type, LENGTH(as_file) AS as_file_size
         FROM files
         WHERE classCode = ? AND as_title = ?"
    );
    $stmt->execute([$classCode, $as_title]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStudentName(PDO $pdo, $classCode,  $id){
    $stmt = $pdo->prepare("SELECT studentLastName, studentFirstName, studentMiddleName FROM students WHERE classCode = ? AND studentID = ?");
    $stmt->execute([$classCode, (int)$id]);
    $name = $stmt->fetch(PDO::FETCH_ASSOC);
    return $name['studentLastName'] . ", " . $name['studentFirstName'] . " " . $name['studentMiddleName'];
}

function getAllStudentAssignments(PDO $pdo, $as_id, $classCode){
    $stmt = $pdo->prepare("SELECT studentID, as_status, edited_at 
                            FROM studentassignments 
                            WHERE classCode = ? AND as_id = ?");
    $stmt->execute([$classCode, $as_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function asCreateForNewAddedStudent(PDO $pdo, int $studentId, $classCode): int {
    $stmt = $pdo->prepare(
        "INSERT INTO studentassignments
            (as_id, classCode, studentID, as_title, as_subject, as_description, as_deadline, is_group_as)
         SELECT a.as_id, a.classCode, ?, a.as_title, a.as_subject, a.as_description, a.as_deadline, a.is_group_as
         FROM assignments a
         WHERE a.classCode = ?
           AND NOT EXISTS (
               SELECT 1 FROM studentassignments sa
               WHERE sa.studentID = ? AND sa.as_id = a.as_id
           )"
    );
    $stmt->execute([$studentId, $classCode, $studentId]);
    return $stmt->rowCount();
}

function deleteAs(PDO $pdo, $as_id, $classCode){
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM assignments WHERE as_id = ? AND classCode = ?");
        $stmt->execute([(int)$as_id, (int)$classCode]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function downloadFile(PDO $pdo, $fileId, $classCode, bool $inline = false): void {
    // classCode in the WHERE clause stops people opening files from other classes
    $stmt = $pdo->prepare(
        "SELECT as_file, as_file_name, as_file_type
         FROM files
         WHERE sa_id = ? AND classCode = ?"
    );
    $stmt->execute([$fileId, $classCode]);
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

function downloadStudentFile(PDO $pdo, int $fileId, $classCode, ?int $studentId = null, bool $inline = false): void {
    $sql = "SELECT file_name, file_data FROM student_as_files WHERE id = ? AND classCode = ?";
    $params = [$fileId, $classCode];

    // a student may only download their own files; an admin passes null to skip this check
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
?>