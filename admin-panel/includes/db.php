<?php
declare(strict_types=1);

const YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
const USER_ROLES  = ['Block Rep', 'Student'];
const PROGRAMS    = ['BSCS', 'BSIT', 'BSEMC-GD', 'BSEMC-DAT'];

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = require __DIR__ . '/config.php';
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function db_tx(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function db_block_for(PDO $pdo, string $program, string $year): int
{
    $st = $pdo->prepare('SELECT courseID FROM tblcourse WHERE courseName = ? LIMIT 1');
    $st->execute([$program]);
    $courseId = $st->fetchColumn();
    if ($courseId === false) {
        throw new RuntimeException("Program '$program' is missing from tblcourse. Import migration_portal.sql first.");
    }

    $st = $pdo->prepare('SELECT blockID FROM tblblock WHERE courseID = ? AND yearLevel = ? ORDER BY blockID LIMIT 1');
    $st->execute([$courseId, $year]);
    $blockId = $st->fetchColumn();
    if ($blockId !== false) {
        return (int) $blockId;
    }

    $st = $pdo->prepare('INSERT INTO tblblock (classBlock, courseID, yearLevel) VALUES (?, ?, ?)');
    $st->execute(['A', $courseId, $year]);
    return (int) $pdo->lastInsertId();
}

function db_next_block_name(PDO $pdo, string $program, string $year): ?string
{
    $st = $pdo->prepare(
        'SELECT b.classBlock FROM tblblock b JOIN tblcourse c ON c.courseID = b.courseID
          WHERE c.courseName = ? AND b.yearLevel = ?'
    );
    $st->execute([$program, $year]);
    $used = array_map('strtoupper', $st->fetchAll(PDO::FETCH_COLUMN));
    foreach (range('A', 'Z') as $letter) {
        if (!in_array($letter, $used, true)) {
            return $letter;
        }
    }
    return null;
}

function db_block_matches(PDO $pdo, int $blockId, string $program, string $year): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM tblblock b JOIN tblcourse c ON c.courseID = b.courseID
          WHERE b.blockID = ? AND c.courseName = ? AND b.yearLevel = ?'
    );
    $st->execute([$blockId, $program, $year]);
    return $st->fetchColumn() !== false;
}

function db_full_name(array $r): string
{
    return trim(implode(' ', array_filter([
        $r['studentFirstName'], $r['studentMiddleName'] ?? '', $r['studentLastName'], $r['studentExtensionName'] ?? '',
    ], static fn ($p) => $p !== null && $p !== '')));
}

function db_read(): array
{
    $pdo = db();

    $subjects = array_fill_keys(YEAR_LEVELS, []);
    foreach ($pdo->query('SELECT * FROM tblsubject ORDER BY subjectID') as $r) {
        if (!isset($subjects[$r['yearLevel']])) {
            continue;
        }
        $subjects[$r['yearLevel']][] = [
            'id'          => (string) $r['subjectID'],
            'program'     => (string) $r['program'],
            'name'        => $r['subjectName'],
            'code'        => $r['subjectCode'],
            'instructor'  => $r['instructor'],
            'schedule'    => (string) $r['schedule'],
            'room'        => (string) $r['room'],
            'description' => (string) $r['description'],
        ];
    }

    $blocks = [];
    $rows = $pdo->query(
        'SELECT b.blockID, b.classBlock, b.yearLevel, c.courseName AS program,
                (SELECT COUNT(*) FROM tblstudent s WHERE s.blockID = b.blockID) AS total
           FROM tblblock b JOIN tblcourse c ON c.courseID = b.courseID
          ORDER BY c.courseName, b.yearLevel, b.classBlock, b.blockID'
    );
    foreach ($rows as $r) {
        $blocks[] = [
            'id'        => (string) $r['blockID'],
            'name'      => (string) $r['classBlock'],
            'program'   => (string) $r['program'],
            'yearLevel' => (string) $r['yearLevel'],
            'total'     => (int) $r['total'], 
        ];
    }

    $studentSql =
        'SELECT s.studentID, s.studentNumber, s.studentFirstName, s.studentMiddleName, s.studentLastName,
                s.studentExtensionName, s.studentEmail, s.isClassRep, s.archivedAt, s.blockID, b.classBlock,
                c.courseName AS program, b.yearLevel
           FROM tblstudent s
           JOIN tblblock  b ON b.blockID  = s.blockID
           JOIN tblcourse c ON c.courseID = b.courseID
          WHERE s.isArchived = %d
          ORDER BY %s s.studentLastName, s.studentFirstName, s.studentID';
    $shape = static fn (array $r): array => [
        'id'            => (string) $r['studentID'],
        'firstName'     => $r['studentFirstName'],
        'middleName'    => (string) $r['studentMiddleName'],
        'lastName'      => $r['studentLastName'],
        'extensionName' => (string) $r['studentExtensionName'],
        'fullName'      => db_full_name($r),
        'studentNumber' => (string) $r['studentNumber'],
        'email'         => $r['studentEmail'],
        'program'       => $r['program'],
        'yearLevel'     => $r['yearLevel'],
        'blockId'       => (string) $r['blockID'],
        'block'         => (string) $r['classBlock'],
        'role'          => (int) $r['isClassRep'] === 1 ? 'Block Rep' : 'Student',
        'archivedAt'    => (string) $r['archivedAt'],
    ];

    $students = [];
    foreach ($pdo->query(sprintf($studentSql, 0, '')) as $r) {
        $students[] = $shape($r);
    }
    $archived = [];
    foreach ($pdo->query(sprintf($studentSql, 1, 's.archivedAt DESC,')) as $r) {
        $archived[] = $shape($r);
    }

    $admins = [];
    $rows = $pdo->query(
        'SELECT adminID, adminFirstName, adminMiddleName, adminLastName, adminExtensionName, adminEmail
           FROM tbladmin ORDER BY adminLastName, adminFirstName, adminID'
    );
    foreach ($rows as $r) {
        $admins[] = [
            'id'       => (string) $r['adminID'],
            'fullName' => trim(implode(' ', array_filter(
                [$r['adminFirstName'], $r['adminMiddleName'] ?? '', $r['adminLastName'], $r['adminExtensionName'] ?? ''],
                static fn ($p) => $p !== null && $p !== ''
            ))),
            'email'    => $r['adminEmail'],
        ];
    }

    return ['subjects' => $subjects, 'students' => $students, 'blocks' => $blocks, 'archived' => $archived, 'admins' => $admins];
}
