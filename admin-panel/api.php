<?php

declare(strict_types=1);

require __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $body)
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function clean($v, int $max = 200): string
{
    return mb_substr(trim((string) ($v ?? '')), 0, $max);
}

function student_fields(array $in): array
{
    $f = [
        'firstName'     => clean($in['firstName'] ?? '', 25),
        'middleName'    => clean($in['middleName'] ?? '', 25),
        'lastName'      => clean($in['lastName'] ?? '', 25),
        'extensionName' => clean($in['extensionName'] ?? '', 10),
        'studentNumber' => clean($in['studentNumber'] ?? '', 40),
        'email'         => clean($in['email'] ?? '', 100),
        'program'       => clean($in['program'] ?? '', 20),
        'yearLevel'     => clean($in['yearLevel'] ?? '', 20),
    ];
    $required = [
        'firstName' => 'First name', 'lastName' => 'Last name', 'studentNumber' => 'Student number',
        'email' => 'Email address', 'program' => 'Program', 'yearLevel' => 'Year level',
    ];
    foreach ($required as $key => $label) {
        if ($f[$key] === '') {
            respond(422, ['error' => "$label is required."]);
        }
    }
    if (!in_array($f['program'], PROGRAMS, true)) {
        respond(422, ['error' => 'Please choose one of the listed programs.']);
    }
    if (!in_array($f['yearLevel'], YEAR_LEVELS, true)) {
        respond(422, ['error' => 'Unknown year level.']);
    }
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        respond(422, ['error' => 'Please enter a valid email address.']);
    }
    return $f;
}

function subject_fields(array $in): array
{
    $f = [
        'year'        => clean($in['year'] ?? '', 20),
        'program'     => clean($in['program'] ?? '', 20),
        'name'        => clean($in['name'] ?? '', 100),
        'code'        => clean($in['code'] ?? '', 30),
        'instructor'  => clean($in['instructor'] ?? '', 100),
        'schedule'    => clean($in['schedule'] ?? '', 100),
        'room'        => clean($in['room'] ?? '', 60),
        'description' => clean($in['description'] ?? '', 500),
    ];
    if (!in_array($f['year'], YEAR_LEVELS, true)) {
        respond(422, ['error' => 'Unknown year level.']);
    }
    if (!in_array($f['program'], PROGRAMS, true)) {
        respond(422, ['error' => 'Please choose one of the listed programs.']);
    }
    if ($f['name'] === '' || $f['code'] === '' || $f['instructor'] === '') {
        respond(422, ['error' => 'Subject name, code and instructor are required.']);
    }
    return $f;
}

function nullable(string $v): ?string
{
    return $v === '' ? null : $v;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') {
    respond(405, ['error' => 'Method not allowed.']);
}

try {
    if ($method === 'GET') {
        respond(200, db_read());
    }

    $in = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($in) || !isset($in['action'])) {
        respond(400, ['error' => 'Invalid request body.']);
    }
    $id = (int) ($in['id'] ?? 0);

    switch ($in['action']) {

        case 'admin_add': {
            $f = [
                'first' => clean($in['firstName'] ?? '', 25),
                'middle' => clean($in['middleName'] ?? '', 25),
                'last' => clean($in['lastName'] ?? '', 25),
                'ext' => clean($in['extensionName'] ?? '', 10),
                'email' => clean($in['email'] ?? '', 100),
            ];
            if ($f['first'] === '' || $f['last'] === '') {
                respond(422, ['error' => 'First name and last name are required.']);
            }
            if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
                respond(422, ['error' => 'Please enter a valid email address.']);
            }
            $password = (string) ($in['password'] ?? '');
            if (strlen($password) < 8) {
                respond(422, ['error' => 'The password must be at least 8 characters.']);
            }
            if (strlen($password) > 72) {
                respond(422, ['error' => 'The password can be at most 72 characters.']);
            }
            db()->prepare(
                'INSERT INTO tbladmin (adminLastName, adminFirstName, adminMiddleName, adminExtensionName, adminEmail, adminPassword)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $f['last'], $f['first'], nullable($f['middle']), nullable($f['ext']), $f['email'],
                password_hash($password, PASSWORD_DEFAULT),
            ]);
            break;
        }

        case 'admin_delete': {
            if ((int) db()->query('SELECT COUNT(*) FROM tbladmin')->fetchColumn() <= 1) {
                respond(409, ['error' => 'You can’t remove the only admin account.']);
            }
            db()->prepare('DELETE FROM tbladmin WHERE adminID = ?')->execute([$id]);
            break;
        }

        case 'subject_add': {
            $f = subject_fields($in);
            db()->prepare(
                'INSERT INTO tblsubject (yearLevel, program, subjectName, subjectCode, instructor, schedule, room, description)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $f['year'], $f['program'], $f['name'], $f['code'], $f['instructor'],
                nullable($f['schedule']), nullable($f['room']), nullable($f['description']),
            ]);
            break;
        }

        case 'subject_update': {
            $f = subject_fields($in);
            db()->prepare(
                'UPDATE tblsubject SET yearLevel = ?, program = ?, subjectName = ?, subjectCode = ?, instructor = ?,
                                       schedule = ?, room = ?, description = ? WHERE subjectID = ?'
            )->execute([
                $f['year'], $f['program'], $f['name'], $f['code'], $f['instructor'],
                nullable($f['schedule']), nullable($f['room']), nullable($f['description']), $id,
            ]);
            break;
        }

        case 'subject_delete': {
            db()->prepare('DELETE FROM tblsubject WHERE subjectID = ?')->execute([$id]);
            break;
        }

        case 'block_add': {
            $program = clean($in['program'] ?? '', 20);
            $year = clean($in['yearLevel'] ?? '', 20);
            if (!in_array($program, PROGRAMS, true) || !in_array($year, YEAR_LEVELS, true)) {
                respond(422, ['error' => 'Choose a program and a year level for the new block.']);
            }
            db_tx(function (PDO $pdo) use ($program, $year) {
                $name = db_next_block_name($pdo, $program, $year);
                if ($name === null) {
                    respond(409, ['error' => 'That program and year level already has 26 blocks.']);
                }
                $st = $pdo->prepare('SELECT courseID FROM tblcourse WHERE courseName = ? LIMIT 1');
                $st->execute([$program]);
                $courseId = $st->fetchColumn();
                if ($courseId === false) {
                    throw new RuntimeException("Program '$program' is missing from tblcourse. Import migration_portal.sql first.");
                }
                $pdo->prepare('INSERT INTO tblblock (classBlock, courseID, yearLevel) VALUES (?, ?, ?)')
                    ->execute([$name, $courseId, $year]);
            });
            break;
        }

        case 'block_delete': {
            $st = db()->prepare('SELECT COUNT(*) FROM tblstudent WHERE blockID = ?');
            $st->execute([$id]);
            if ((int) $st->fetchColumn() > 0) {
                respond(409, ['error' => 'Only an empty block can be deleted. Move or permanently delete its students (archived ones too) first.']);
            }
            db()->prepare('DELETE FROM tblblock WHERE blockID = ?')->execute([$id]);
            break;
        }

        case 'student_add': {
            $f = student_fields($in);
            $wanted = (int) ($in['blockId'] ?? 0);
            db_tx(function (PDO $pdo) use ($f, $wanted) {
                $blockId = $wanted > 0 && db_block_matches($pdo, $wanted, $f['program'], $f['yearLevel'])
                    ? $wanted : db_block_for($pdo, $f['program'], $f['yearLevel']);
                $pdo->prepare(
                    'INSERT INTO tblstudent (studentNumber, studentLastName, studentFirstName, studentMiddleName,
                                             studentExtensionName, studentEmail, studentPassword, isClassRep, isGroupLeader, blockID)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, ?)'
                )->execute([
                    $f['studentNumber'], $f['lastName'], $f['firstName'], nullable($f['middleName']),
                    nullable($f['extensionName']), $f['email'], password_hash($f['studentNumber'], PASSWORD_DEFAULT), $blockId,
                ]);
            });
            break;
        }

        case 'student_update': {
            $f = student_fields($in);
            $wanted = (int) ($in['blockId'] ?? 0);
            db_tx(function (PDO $pdo) use ($f, $id, $wanted) {
                $blockId = $wanted > 0 && db_block_matches($pdo, $wanted, $f['program'], $f['yearLevel'])
                    ? $wanted : db_block_for($pdo, $f['program'], $f['yearLevel']);
                $pdo->prepare(
                    'UPDATE tblstudent
                        SET studentNumber = ?, studentLastName = ?, studentFirstName = ?, studentMiddleName = ?,
                            studentExtensionName = ?, studentEmail = ?, blockID = ?
                      WHERE studentID = ? AND isArchived = 0'
                )->execute([
                    $f['studentNumber'], $f['lastName'], $f['firstName'], nullable($f['middleName']),
                    nullable($f['extensionName']), $f['email'], $blockId, $id,
                ]);
            });
            break;
        }

        case 'student_delete': {
            db()->prepare('UPDATE tblstudent SET isArchived = 1, archivedAt = NOW() WHERE studentID = ? AND isArchived = 0')
                ->execute([$id]);
            break;
        }

        case 'student_restore': {
            db()->prepare('UPDATE tblstudent SET isArchived = 0, archivedAt = NULL WHERE studentID = ? AND isArchived = 1')
                ->execute([$id]);
            break;
        }

        case 'student_purge': {
            db()->prepare('DELETE FROM tblstudent WHERE studentID = ? AND isArchived = 1')->execute([$id]);
            break;
        }

        case 'role_update': {
            $role = clean($in['role'] ?? '', 20);
            if (!in_array($role, USER_ROLES, true)) {
                respond(422, ['error' => 'Unknown role.']);
            }
            db()->prepare('UPDATE tblstudent SET isClassRep = ? WHERE studentID = ? AND isArchived = 0')
                ->execute([$role === 'Block Rep' ? 1 : 0, $id]);
            break;
        }

        default:
            respond(400, ['error' => 'Unknown action.']);
    }

    respond(200, db_read());

} catch (PDOException $e) {
    $driverCode = (int) ($e->errorInfo[1] ?? 0);
    if ($driverCode === 1062) {
        if (str_contains($e->getMessage(), 'adminEmail')) {
            respond(409, ['error' => 'An admin with that email already exists.']);
        }
        respond(409, ['error' => 'That email or student number is already registered (check the Archive too).']);
    }
    if ($driverCode === 1451) {
        respond(409, ['error' => 'This record is still linked to other data (for example events or quizzes created by this admin, or a group leader), so it can’t be deleted.']);
    }
    if (in_array($e->getCode(), ['42S02', '42S22'], true)) {
        respond(500, ['error' => 'The database is missing the portal tables/columns. Import migration_portal.sql in phpMyAdmin first.']);
    }
    $connCode = (int) $e->getCode(); 
    if (in_array($connCode, [1045, 1049, 2002], true) || in_array($driverCode, [1045, 1049], true)) {
        respond(500, ['error' => 'Cannot connect to the database. Check includes/config.php and that MySQL/MariaDB is running.']);
    }
    respond(500, ['error' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    respond(500, ['error' => 'Server error: ' . $e->getMessage()]);
}
