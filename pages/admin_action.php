<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/renderers/dashboard_helpers.php';

if (strtolower((string) $_SESSION['user_type']) !== 'admin') {
    http_response_code(403);
    exit('This action is only available to administrators.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$submittedToken = (string) ($_POST['csrf'] ?? '');
if (!hash_equals((string) ($_SESSION['dashboard_csrf'] ?? ''), $submittedToken)) {
    http_response_code(400);
    exit('Invalid form token. Reload the dashboard and try again.');
}

$entities = [
    'students' => [
        'table' => 'tblstudent',
        'pk' => 'studentID',
        'fields' => [
            'studentFirstName' => ['text', 25],
            'studentLastName' => ['text', 25],
            'studentMiddleName' => ['text', 25, true],
            'studentExtensionName' => ['text', 10, true],
            'studentEmail' => ['email', 100],
            'password' => ['password'],
            'isClassRep' => ['checkbox'],
            'isGroupLeader' => ['checkbox'],
            'blockID' => ['reference', 'tblblock', 'blockID'],
        ],
    ],
    'blocks' => [
        'table' => 'tblblock',
        'pk' => 'blockID',
        'fields' => [
            'classBlock' => ['text', 30],
            'courseID' => ['reference', 'tblcourse', 'courseID'],
            'facultyID' => ['reference', 'tblfaculty', 'facultyID', true],
        ],
    ],
    'assignments' => [
        'table' => 'tblassignment',
        'pk' => 'assignmentID',
        'fields' => [
            'blockID' => ['reference', 'tblblock', 'blockID'],
            'assignmentTitle' => ['text', 150],
            'assignmentSubject' => ['text', 150],
            'assignmentDescription' => ['text', null, true],
            'assignmentFilePath' => ['text', 255, true],
            'assignmentStatus' => ['choice', ['Active', 'Completed', 'Cancelled']],
            'assignmentDeadline' => ['datetime'],
        ],
    ],
    'events' => [
        'table' => 'tblevent',
        'pk' => 'eventID',
        'fields' => [
            'eventTitle' => ['text', 150],
            'eventDescription' => ['text', null, true],
            'eventDate' => ['date'],
            'eventStartTime' => ['time'],
            'eventEndTime' => ['time'],
            'eventLocation' => ['text', 150],
            'participationFee' => ['money'],
            'eventStatus' => ['choice', ['Upcoming', 'Ongoing', 'Completed', 'Cancelled']],
        ],
    ],
    'announcements' => [
        'table' => 'tblannouncement',
        'pk' => 'announcementID',
        'fields' => [
            'announcementTitle' => ['text', 150],
            'announcementContent' => ['text', null],
        ],
    ],
];

$entityKey = (string) ($_POST['entity'] ?? '');
$action = (string) ($_POST['action'] ?? '');
if (!isset($entities[$entityKey]) || !in_array($action, ['create', 'update', 'archive', 'restore'], true)) {
    http_response_code(400);
    exit('Invalid admin action.');
}

$entity = $entities[$entityKey];
$table = $entity['table'];
$primaryKey = $entity['pk'];
$recordId = (int) ($_POST['id'] ?? 0);
$pdo = db();
$returnUrl = './admin_' . $entityKey . '.php';
$redirect = static function (string $message) use ($returnUrl): void {
    $_SESSION['dashboard_flash'] = $message;
    header('Location: ' . $returnUrl);
    exit;
};

try {
    if ($action === 'archive' || $action === 'restore') {
        if ($recordId < 1) {
            http_response_code(400);
            exit('Invalid record ID.');
        }
        if ($action === 'restore' && in_array($entityKey, ['students', 'assignments'], true)) {
            $relatedBlock = $pdo->prepare(
                'SELECT b.archivedAt
                 FROM ' . $table . ' r
                 JOIN tblblock b ON b.blockID = r.blockID
                 WHERE r.' . $primaryKey . ' = ? AND r.archivedAt IS NOT NULL'
            );
            $relatedBlock->execute([$recordId]);
            $blockArchivedAt = $relatedBlock->fetchColumn();
            if ($blockArchivedAt !== false && $blockArchivedAt !== null) {
                throw new InvalidArgumentException('Restore the related block before restoring this record.');
            }
        }
        if ($action === 'archive' && $entityKey === 'blocks') {
            $students = $pdo->prepare('SELECT COUNT(*) FROM tblstudent WHERE blockID = ? AND archivedAt IS NULL');
            $students->execute([$recordId]);
            $assignments = $pdo->prepare('SELECT COUNT(*) FROM tblassignment WHERE blockID = ? AND archivedAt IS NULL');
            $assignments->execute([$recordId]);
            if ((int) $students->fetchColumn() > 0 || (int) $assignments->fetchColumn() > 0) {
                throw new InvalidArgumentException('Move or archive this block’s active students and assignments first.');
            }
        }
        $archivedAt = $action === 'archive' ? 'NOW()' : 'NULL';
        $currentState = $action === 'archive' ? 'IS NULL' : 'IS NOT NULL';
        $statement = $pdo->prepare(
            'UPDATE ' . $table . ' SET archivedAt = ' . $archivedAt
            . ' WHERE ' . $primaryKey . ' = ? AND archivedAt ' . $currentState
        );
        $statement->execute([$recordId]);
        if ($statement->rowCount() === 0) {
            http_response_code(404);
            exit('Record not found or already in that archive state.');
        }
        $redirect($action === 'archive' ? 'Record archived.' : 'Record restored.');
    }

    $values = [];
    foreach ($entity['fields'] as $fieldName => $definition) {
        $type = $definition[0];
        $rawValue = $_POST[$fieldName] ?? '';

        if ($type === 'checkbox') {
            $values[$fieldName] = isset($_POST[$fieldName]) ? 1 : 0;
            continue;
        }

        if ($type === 'password') {
            $password = (string) $rawValue;
            if ($action === 'create' && strlen($password) < 8) {
                throw new InvalidArgumentException('Use a password with at least 8 characters.');
            }
            if ($password !== '') {
                $values['studentPassword'] = password_hash($password, PASSWORD_DEFAULT);
            }
            continue;
        }

        if ($type === 'reference') {
            $referenceId = (int) $rawValue;
            $optional = !empty($definition[3]);
            if ($referenceId < 1 && $optional) {
                $values[$fieldName] = null;
                continue;
            }
            if ($referenceId < 1) {
                throw new InvalidArgumentException('Choose a valid related record.');
            }
            $referenceTable = $definition[1];
            $referenceKey = $definition[2];
            $activeFilter = $referenceTable === 'tblblock' ? ' AND archivedAt IS NULL' : '';
            $check = $pdo->prepare(
                'SELECT COUNT(*) FROM ' . $referenceTable . ' WHERE ' . $referenceKey . ' = ?' . $activeFilter
            );
            $check->execute([$referenceId]);
            if (!(int) $check->fetchColumn()) {
                throw new InvalidArgumentException('The selected related record is not available.');
            }
            $values[$fieldName] = $referenceId;
            continue;
        }

        $value = trim((string) $rawValue);
        $optional = !empty($definition[2]);
        if ($value === '') {
            if ($optional) {
                $values[$fieldName] = null;
                continue;
            }
            throw new InvalidArgumentException('Complete all required fields.');
        }

        if ($type === 'text') {
            $maximumLength = $definition[1];
            if ($maximumLength !== null && mb_strlen($value) > $maximumLength) {
                throw new InvalidArgumentException('One or more text fields exceed their maximum length.');
            }
            $values[$fieldName] = $value;
        } elseif ($type === 'email') {
            if (mb_strlen($value) > $definition[1] || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }
            $values[$fieldName] = $value;
        } elseif ($type === 'choice') {
            if (!in_array($value, $definition[1], true)) {
                throw new InvalidArgumentException('Choose one of the available options.');
            }
            $values[$fieldName] = $value;
        } elseif ($type === 'date' || $type === 'time' || $type === 'datetime') {
            $format = ['date' => 'Y-m-d', 'time' => 'H:i', 'datetime' => 'Y-m-d\TH:i'][$type];
            $date = DateTime::createFromFormat('!' . $format, $value);
            if (!$date || $date->format($format) !== $value) {
                throw new InvalidArgumentException('Enter a valid date and time.');
            }
            $values[$fieldName] = $type === 'datetime'
                ? $date->format('Y-m-d H:i:s')
                : ($type === 'time' ? $date->format('H:i:s') : $date->format('Y-m-d'));
        } elseif ($type === 'money') {
            if (!is_numeric($value) || (float) $value < 0) {
                throw new InvalidArgumentException('Enter a valid non-negative fee.');
            }
            $values[$fieldName] = number_format((float) $value, 2, '.', '');
        }
    }

    if ($action === 'create' && ($entityKey === 'events' || $entityKey === 'announcements')) {
        $values['adminID'] = (int) $_SESSION['user_id'];
    }

    if ($entityKey === 'events' && $values['eventEndTime'] <= $values['eventStartTime']) {
        throw new InvalidArgumentException('Event end time must be after its start time.');
    }

    if ($action === 'create') {
        $columns = array_keys($values);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $statement = $pdo->prepare(
            'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );
        $statement->execute(array_values($values));
        $redirect('Record created.');
    }

    if ($recordId < 1) {
        http_response_code(400);
        exit('Invalid record ID.');
    }
    if ($entityKey === 'students' && !array_key_exists('studentPassword', $values)) {
        unset($values['studentPassword']);
    }
    $columns = array_keys($values);
    if (!$columns) {
        throw new InvalidArgumentException('No changes were submitted.');
    }
    $setClause = implode(', ', array_map(static function (string $column): string {
        return $column . ' = ?';
    }, $columns));
    $statement = $pdo->prepare(
        'UPDATE ' . $table . ' SET ' . $setClause
        . ' WHERE ' . $primaryKey . ' = ? AND archivedAt IS NULL'
    );
    $statement->execute(array_merge(array_values($values), [$recordId]));
    if ($statement->rowCount() === 0) {
        $exists = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $primaryKey . ' = ? AND archivedAt IS NULL'
        );
        $exists->execute([$recordId]);
        if (!(int) $exists->fetchColumn()) {
            http_response_code(404);
            exit('Record not found.');
        }
    }
    $redirect('Record updated.');
} catch (InvalidArgumentException $exception) {
    $redirect($exception->getMessage());
} catch (PDOException $exception) {
    error_log('Admin dashboard action failed: ' . $exception->getMessage());
    $redirect('Could not save this record. Check for duplicate emails and valid relationships.');
}
