<?php

require_once __DIR__ . '/dbConnection.php';
require_once __DIR__ . '/../includes/mailer.php';

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

function getAdmin(PDO $pdo, $adminName){
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE adminName = ?");
    $stmt->execute([$adminName]);
    return $stmt->fetch();
}

function getAdminInfo(PDO $pdo, $adminID){
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE adminID = ? LIMIT 1");
    $stmt->execute([$adminID]);
    return $stmt->fetch();
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

function parseUploadedFiles(?array $files): array {
    $uploaded = [];
    if ($files !== null && isset($files['name']) && is_array($files['name'])) {
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
    return $uploaded;
}

/**
 * Edits an assignment: its details, the copies stored for each student,
 * removes the files in $removeFileIds and adds the newly uploaded files.
 * Everything is saved together, or nothing is.
 */
function updateAssignment(
    PDO $pdo,
    int $as_id,
    $classCode,
    string $title,
    string $subject,
    string $des,
    string $deadline,
    ?array $files = null,
    array $removeFileIds = []
 ): void {
    $maxFiles = 10;

    if ($title === '' || $subject === '' || $des === '') {
        throw new Exception("Title, subject and description are required.");
    }

    // sa_id values that were posted -> clean list of ints
    $removeFileIds = array_values(array_unique(array_filter(array_map('intval', $removeFileIds))));

    // read the uploads before touching the database
    $uploaded = parseUploadedFiles($files);

    $pdo->beginTransaction();
    try {
        // 1. The current title (the files table finds its files by title + classCode)
        $stmt = $pdo->prepare("SELECT as_title FROM assignments WHERE as_id = ? AND classCode = ?");
        $stmt->execute([$as_id, (int) $classCode]);
        $oldTitle = $stmt->fetchColumn();
        if ($oldTitle === false) {
            throw new Exception("Assignment not found.");
        }

        // 2. Titles must stay unique per class, otherwise two assignments would share the same files
        $dup = $pdo->prepare(
            "SELECT 1 FROM assignments WHERE classCode = ? AND as_title = ? AND as_id <> ? LIMIT 1"
        );
        $dup->execute([(int) $classCode, $title, $as_id]);
        if ($dup->fetchColumn()) {
            throw new Exception("Another assignment in this class already uses that title.");
        }

        // 3. Update the assignment itself
        $stmt = $pdo->prepare(
            "UPDATE assignments
             SET as_title = ?, as_subject = ?, as_description = ?, as_deadline = ?
             WHERE as_id = ? AND classCode = ?"
        );
        $stmt->execute([$title, $subject, $des, $deadline, $as_id, (int) $classCode]);

        // 4. Update the copy every student has
        $stmt = $pdo->prepare(
            "UPDATE studentassignments
             SET as_title = ?, as_subject = ?, as_description = ?, as_deadline = ?
             WHERE as_id = ? AND classCode = ?"
        );
        $stmt->execute([$title, $subject, $des, $deadline, $as_id, (int) $classCode]);

        // 5. If the title changed, keep the existing files attached to the assignment
        if ($oldTitle !== $title) {
            $stmt = $pdo->prepare("UPDATE files SET as_title = ? WHERE as_title = ? AND classCode = ?");
            $stmt->execute([$title, $oldTitle, (int) $classCode]);
        }

        // 6. Delete the files the admin removed (title + classCode in the WHERE keeps it to this assignment)
        if (!empty($removeFileIds)) {
            $del = $pdo->prepare("DELETE FROM files WHERE sa_id = ? AND classCode = ? AND as_title = ?");
            foreach ($removeFileIds as $fileId) {
                $del->execute([$fileId, (int) $classCode, $title]);
            }
        }

        // 7. Check the 10 file limit (files left + new files)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM files WHERE classCode = ? AND as_title = ?");
        $stmt->execute([(int) $classCode, $title]);
        $remaining = (int) $stmt->fetchColumn();
        if ($remaining + count($uploaded) > $maxFiles) {
            throw new Exception("An assignment can have up to $maxFiles files.");
        }

        // 8. Add the new files
        $fs = $pdo->prepare(
            "INSERT INTO files (as_title, classCode, as_file, as_file_name, as_file_type)
             VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($uploaded as $f) {
            $fs->bindValue(1, $title);
            $fs->bindValue(2, (int) $classCode, PDO::PARAM_INT);
            $fs->bindValue(3, $f['data'], PDO::PARAM_LOB);
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

function createAsOnlyForStudent(PDO $pdo, int $as_id, int $classCode, array $studentIDS, string $as_title, string $as_subject, string $as_description, $as_deadline, int $isGroupAs){
 $pdo->beginTransaction();
 try{

 $stmt = $pdo->prepare(
            "INSERT INTO studentassignments (as_id, classCode, studentID, as_title, as_subject, as_description, as_deadline, is_group_as)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

 foreach($studentIDS as $id){
 $stmt->execute([$as_id, $classCode, $id, $as_title, $as_subject, $as_description, $as_deadline, $isGroupAs]);
 }

  $pdo->commit();

  }catch(Exception $e){
    $pdo->rollBack();
    throw $e;
  }
}

function getGroupAs($pdo, $as_id, $groupName){
    $stmt = $pdo->prepare("SELECT * FROM groupas WHERE as_id = ? AND group_name = ? LIMIT 1");
    $stmt->execute([(int)$as_id, (string)$groupName]);
    return $stmt->fetch();
}

function createMembersAssignment(PDO $pdo, $classCode, $groupName, $leader, ?array $members, $assignment) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO groupas (as_id, group_name) VALUES (?, ?)");
        $stmt->execute([(int)$assignment['as_id'], $groupName]);

        $groupAs = getGroupAs($pdo, (int)$assignment['as_id'], $groupName);

        $stmt = $pdo->prepare("INSERT INTO groupmembers (groupID, studentID, is_leader, as_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([(int)$groupAs['groupID'], (int) $leader, 1, (int)$assignment['as_id']]);

        foreach($members as $member){
            $stmt = $pdo->prepare("INSERT INTO groupmembers (groupID, studentID, is_leader, as_id) VALUES (?, ?, ?, ?)");
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
        $stmt = $pdo->prepare("SELECT * FROM groupas WHERE as_id = ?");
        $stmt->execute([$as_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);      // commit first...           // ...then retur
}

function getAllMembers(PDO $pdo, $groupID){
        $stmt = $pdo->prepare("SELECT * FROM groupmembers WHERE groupID = ?");
        $stmt->execute([$groupID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);      // commit first...           // ...then return
}

function getLeaderID(PDO $pdo, $groupID){
    $stmt = $pdo->prepare("SELECT * FROM groupmembers WHERE groupID = ? AND is_leader = 1 LIMIT 1");
    $stmt->execute([$groupID]);
    return $stmt->fetch(PDO::FETCH_ASSOC); 
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
        "SELECT *
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

function ifHasGroup(PDO $pdo, $studentID, $as_id){
    $stmt = $pdo->prepare("SELECT * FROM groupmembers WHERE studentID = ? AND as_id = ? LIMIT 1");
    $stmt->execute([$studentID, $as_id]);
    return $stmt->fetch();
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
    $stmt = $pdo->prepare("SELECT * 
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

function isAdminHasFeedback(PDO $pdo, int $sa_id){
        $st = $pdo->prepare("SELECT 1 FROM feedback WHERE sa_id = ? LIMIT 1");
        $st->execute([$sa_id]);
        $result = $st->fetchColumn();
        return $result;
}

function createFeedback(PDO $pdo, int $sa_id, int $adminID, $feedback, int $score, int $scoreOver){
    $pdo->beginTransaction();
    try{
        $stmt = null;

        if(isAdminHasFeedback($pdo, $sa_id) == true){
            $stmt = $pdo->prepare("UPDATE feedback SET feedback = ?, score = ?, score_over = ? WHERE sa_id = ?");
            $stmt->execute([$feedback, $score, $scoreOver, $sa_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO feedback (sa_id, adminID, feedback, score, score_over) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$sa_id, $adminID, $feedback, $score, $scoreOver]);
        }

        $pdo->commit();
    }catch(Exception $e){
        $pdo->rollBack();
        throw $e;
    }
}

function getFeedback(PDO $pdo, int $sa_id){
    $st = $pdo->prepare("SELECT * FROM feedback WHERE sa_id = ? LIMIT 1");
    $st->execute([$sa_id]);
    return $st->fetch();
}

function deleteFeedback(PDO $pdo, int $id){
    $pdo->beginTransaction();
    try{

    $st = $pdo->prepare("DELETE FROM feedback WHERE feedback_id = ?");
    $st->execute([$id]);

    $pdo->commit();
    }catch(Exception $e){
        $pdo->rollBack();
        throw $e;
    }
}
?>