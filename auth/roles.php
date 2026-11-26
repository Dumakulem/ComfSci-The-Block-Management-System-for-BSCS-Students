<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function currentUserContext(): array
{
    $userType = strtolower((string) ($_SESSION['user_type'] ?? ''));
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($userType === 'admin' && $userId > 0) {
        return [
            'id' => $userId,
            'roles' => ['admin'],
            'blockID' => null,
        ];
    }

    if ($userType !== 'student' || $userId < 1) {
        throw new RuntimeException('Authenticated account context is unavailable.');
    }

    $statement = db()->prepare(
        'SELECT studentID, blockID, isClassRep, isGroupLeader
         FROM tblstudent
         WHERE studentID = ? AND archivedAt IS NULL'
    );
    $statement->execute([$userId]);
    $student = $statement->fetch();

    if (!$student) {
        throw new RuntimeException('The authenticated student is not active.');
    }

    $roles = ['student'];
    if ((int) $student['isClassRep'] === 1) {
        $roles[] = 'block_rep';
    }
    if ((int) $student['isGroupLeader'] === 1) {
        $roles[] = 'group_leader';
    }

    return [
        'id' => (int) $student['studentID'],
        'roles' => $roles,
        'blockID' => (int) $student['blockID'],
    ];
}

function requireAnyRole(array $allowedRoles): array
{
    $context = currentUserContext();

    if (!array_intersect($context['roles'], $allowedRoles)) {
        http_response_code(403);
        exit('You do not have permission to access this feature.');
    }

    return $context;
}
