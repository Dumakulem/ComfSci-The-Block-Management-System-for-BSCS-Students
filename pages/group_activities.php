<?php

declare(strict_types=1);

// Provides block activity creation, group assignment, and group collaboration views.
require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/../auth/roles.php';
require_once __DIR__ . '/../config/database.php';

$userContext = requireAnyRole(['student', 'block_rep', 'group_leader', 'admin']);
$pdo = db();
$userId = (int) $userContext['id'];
$blockId = $userContext['blockID'] === null ? null : (int) $userContext['blockID'];
$roles = $userContext['roles'];
$isAdmin = in_array('admin', $roles, true);
$isBlockRep = in_array('block_rep', $roles, true);
$isGroupLeader = in_array('group_leader', $roles, true);
$message = (string) ($_SESSION['groupActivityFlash'] ?? '');
$isError = !empty($_SESSION['groupActivityFlashError']);
unset($_SESSION['groupActivityFlash'], $_SESSION['groupActivityFlashError']);

if (empty($_SESSION['groupActivityCsrf'])) {
    $_SESSION['groupActivityCsrf'] = bin2hex(random_bytes(32));
}

function groupActivityEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function groupActivityRedirect(): void
{
    header('Location: ./group_activities.php');
    exit;
}

function groupActivityFlash(string $message, bool $isError = false): void
{
    $_SESSION['groupActivityFlash'] = $message;
    $_SESSION['groupActivityFlashError'] = $isError;
}

function groupActivityDateTime(string $value, bool $required): ?string
{
    if ($value === '') {
        if ($required) {
            throw new DomainException('Enter a valid date and time.');
        }
        return null;
    }

    $date = DateTime::createFromFormat('!Y-m-d\TH:i', $value);
    $errors = DateTime::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d\TH:i') !== $value
    ) {
        throw new DomainException('Enter a valid date and time.');
    }

    return $date->format('Y-m-d H:i:s');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['groupActivityCsrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid form token. Reload the page and try again.');
    }

    try {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'create_activity') {
            if (!$isBlockRep || $blockId === null) {
                throw new DomainException('Only a block representative can create activities.');
            }

            $subject = trim((string) ($_POST['subject'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $deadline = groupActivityDateTime((string) ($_POST['deadline'] ?? ''), true);
            $mode = (string) ($_POST['assignmentMode'] ?? 'manual');
            $rawLeaders = $_POST['leaders'] ?? [];

            if ($subject === '' || mb_strlen($subject) > 100
                || $title === '' || mb_strlen($title) > 150
                || mb_strlen($description) > 3000
            ) {
                throw new DomainException('Subject, title, and description must fit their field limits.');
            }
            if (!in_array($mode, ['manual', 'random', 'round_robin'], true)) {
                throw new DomainException('Choose a valid member assignment method.');
            }
            if (!is_array($rawLeaders) || count($rawLeaders) < 1 || count($rawLeaders) > 50) {
                throw new DomainException('Choose between 1 and 50 group leaders.');
            }

            $leaderIds = [];
            foreach ($rawLeaders as $rawLeaderId) {
                $leaderId = filter_var($rawLeaderId, FILTER_VALIDATE_INT);
                if ($leaderId === false || $leaderId < 1) {
                    throw new DomainException('One or more selected group leaders are invalid.');
                }
                $leaderIds[] = (int) $leaderId;
            }
            $leaderIds = array_values(array_unique($leaderIds));

            $leaderPlaceholders = implode(',', array_fill(0, count($leaderIds), '?'));
            $leaderStatement = $pdo->prepare(
                "SELECT studentID
                 FROM tblstudent
                 WHERE blockID = ? AND archivedAt IS NULL AND isGroupLeader = 1
                   AND studentID IN ($leaderPlaceholders)"
            );
            $leaderStatement->execute(array_merge([$blockId], $leaderIds));
            $validLeaderIds = array_map('intval', array_column($leaderStatement->fetchAll(), 'studentID'));
            if (count($validLeaderIds) !== count($leaderIds)) {
                throw new DomainException('Every selected leader must be an active group leader in your block.');
            }

            $pdo->beginTransaction();
            $activityStatement = $pdo->prepare(
                'INSERT INTO tblgroupactivity (blockID, createdBy, subject, activityTitle, activityDescription, deadline)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $activityStatement->execute([$blockId, $userId, $subject, $title, $description, $deadline]);
            $activityId = (int) $pdo->lastInsertId();

            $groupIds = [];
            $groupStatement = $pdo->prepare(
                'INSERT INTO tblgroupactivitygroup (activityID, groupName, leaderID) VALUES (?, ?, ?)'
            );
            $memberStatement = $pdo->prepare(
                'INSERT INTO tblgroupactivitymember (activityID, groupID, studentID) VALUES (?, ?, ?)'
            );
            foreach ($validLeaderIds as $index => $leaderId) {
                $groupStatement->execute([$activityId, 'Group ' . ($index + 1), $leaderId]);
                $groupId = (int) $pdo->lastInsertId();
                $groupIds[] = $groupId;
                $memberStatement->execute([$activityId, $groupId, $leaderId]);
            }

            if ($mode !== 'manual') {
                $studentStatement = $pdo->prepare(
                    'SELECT studentID
                     FROM tblstudent
                     WHERE blockID = ? AND archivedAt IS NULL
                     ORDER BY studentID'
                );
                $studentStatement->execute([$blockId]);
                $studentIds = array_map('intval', array_column($studentStatement->fetchAll(), 'studentID'));
                $studentIds = array_values(array_diff($studentIds, $validLeaderIds));
                if ($mode === 'random') {
                    shuffle($studentIds);
                }

                foreach ($studentIds as $index => $studentId) {
                    $groupId = $groupIds[$index % count($groupIds)];
                    $memberStatement->execute([$activityId, $groupId, $studentId]);
                }
            }

            $pdo->commit();
            groupActivityFlash(
                $mode === 'manual'
                    ? 'Activity created. Add members to each group below.'
                    : 'Activity created and block members assigned to groups.'
            );
            groupActivityRedirect();
        }

        if ($action === 'add_member') {
            if (!$isBlockRep || $blockId === null) {
                throw new DomainException('Only a block representative can assign group members.');
            }

            $groupId = filter_var($_POST['groupID'] ?? null, FILTER_VALIDATE_INT);
            $studentId = filter_var($_POST['studentID'] ?? null, FILTER_VALIDATE_INT);
            if ($groupId === false || $groupId < 1 || $studentId === false || $studentId < 1) {
                throw new DomainException('Choose a valid group member.');
            }

            $groupStatement = $pdo->prepare(
                'SELECT g.activityID
                 FROM tblgroupactivitygroup g
                 JOIN tblgroupactivity a ON a.activityID = g.activityID
                 WHERE g.groupID = ? AND a.blockID = ?'
            );
            $groupStatement->execute([(int) $groupId, $blockId]);
            $group = $groupStatement->fetch();
            if (!$group) {
                throw new DomainException('That group is not part of your block.');
            }

            $studentStatement = $pdo->prepare(
                'SELECT studentID
                 FROM tblstudent
                 WHERE studentID = ? AND blockID = ? AND archivedAt IS NULL'
            );
            $studentStatement->execute([(int) $studentId, $blockId]);
            if (!$studentStatement->fetch()) {
                throw new DomainException('Choose an active student from your block.');
            }

            $alreadyAssigned = $pdo->prepare(
                'SELECT studentID FROM tblgroupactivitymember WHERE activityID = ? AND studentID = ?'
            );
            $alreadyAssigned->execute([(int) $group['activityID'], (int) $studentId]);
            if ($alreadyAssigned->fetch()) {
                throw new DomainException('That student is already assigned to a group in this activity.');
            }

            $insertStatement = $pdo->prepare(
                'INSERT INTO tblgroupactivitymember (activityID, groupID, studentID) VALUES (?, ?, ?)'
            );
            $insertStatement->execute([(int) $group['activityID'], (int) $groupId, (int) $studentId]);
            groupActivityFlash('Student added to the group.');
            groupActivityRedirect();
        }

        if ($action === 'add_timeline') {
            if (!$isGroupLeader) {
                throw new DomainException('Only an assigned group leader can add timeline entries.');
            }

            $groupId = filter_var($_POST['groupID'] ?? null, FILTER_VALIDATE_INT);
            $entryTitle = trim((string) ($_POST['entryTitle'] ?? ''));
            $entryDetails = trim((string) ($_POST['entryDetails'] ?? ''));
            if ($groupId === false || $groupId < 1 || $entryTitle === '' || mb_strlen($entryTitle) > 150
                || mb_strlen($entryDetails) > 2000
            ) {
                throw new DomainException('Enter a timeline title and keep the details within the field limit.');
            }

            $ownedGroup = $pdo->prepare(
                'SELECT groupID FROM tblgroupactivitygroup WHERE groupID = ? AND leaderID = ?'
            );
            $ownedGroup->execute([(int) $groupId, $userId]);
            if (!$ownedGroup->fetch()) {
                throw new DomainException('You can only manage the timeline for your assigned group.');
            }

            $timelineStatement = $pdo->prepare(
                'INSERT INTO tblgroupactivitytimeline (groupID, createdBy, entryTitle, entryDetails)
                 VALUES (?, ?, ?, ?)'
            );
            $timelineStatement->execute([(int) $groupId, $userId, $entryTitle, $entryDetails]);
            groupActivityFlash('Timeline entry added.');
            groupActivityRedirect();
        }

        if ($action === 'edit_timeline') {
            if (!$isGroupLeader) {
                throw new DomainException('Only an assigned group leader can edit timeline entries.');
            }

            $groupId = filter_var($_POST['groupID'] ?? null, FILTER_VALIDATE_INT);
            $timelineId = filter_var($_POST['timelineID'] ?? null, FILTER_VALIDATE_INT);
            $entryTitle = trim((string) ($_POST['entryTitle'] ?? ''));
            $entryDetails = trim((string) ($_POST['entryDetails'] ?? ''));
            if ($groupId === false || $groupId < 1 || $timelineId === false || $timelineId < 1
                || $entryTitle === '' || mb_strlen($entryTitle) > 150 || mb_strlen($entryDetails) > 2000
            ) {
                throw new DomainException('Enter a timeline title and keep the details within the field limit.');
            }

            $entryStatement = $pdo->prepare(
                'SELECT t.timelineID
                 FROM tblgroupactivitytimeline t
                 JOIN tblgroupactivitygroup g ON g.groupID = t.groupID
                 WHERE t.timelineID = ? AND t.groupID = ? AND g.leaderID = ?'
            );
            $entryStatement->execute([(int) $timelineId, (int) $groupId, $userId]);
            if (!$entryStatement->fetch()) {
                throw new DomainException('You can only edit timeline entries in your assigned group.');
            }

            $updateStatement = $pdo->prepare(
                'UPDATE tblgroupactivitytimeline
                 SET entryTitle = ?, entryDetails = ?
                 WHERE timelineID = ? AND groupID = ?'
            );
            $updateStatement->execute([$entryTitle, $entryDetails, (int) $timelineId, (int) $groupId]);
            groupActivityFlash('Timeline entry updated.');
            groupActivityRedirect();
        }

        if ($action === 'delete_timeline') {
            if (!$isGroupLeader) {
                throw new DomainException('Only an assigned group leader can delete timeline entries.');
            }

            $groupId = filter_var($_POST['groupID'] ?? null, FILTER_VALIDATE_INT);
            $timelineId = filter_var($_POST['timelineID'] ?? null, FILTER_VALIDATE_INT);
            if ($groupId === false || $groupId < 1 || $timelineId === false || $timelineId < 1) {
                throw new DomainException('Choose a valid timeline entry.');
            }

            $entryStatement = $pdo->prepare(
                'SELECT t.timelineID
                 FROM tblgroupactivitytimeline t
                 JOIN tblgroupactivitygroup g ON g.groupID = t.groupID
                 WHERE t.timelineID = ? AND t.groupID = ? AND g.leaderID = ?'
            );
            $entryStatement->execute([(int) $timelineId, (int) $groupId, $userId]);
            if (!$entryStatement->fetch()) {
                throw new DomainException('You can only delete timeline entries in your assigned group.');
            }

            $deleteStatement = $pdo->prepare(
                'DELETE FROM tblgroupactivitytimeline WHERE timelineID = ? AND groupID = ?'
            );
            $deleteStatement->execute([(int) $timelineId, (int) $groupId]);
            groupActivityFlash('Timeline entry deleted.');
            groupActivityRedirect();
        }

        if ($action === 'add_task') {
            if (!$isGroupLeader) {
                throw new DomainException('Only an assigned group leader can assign group tasks.');
            }

            $groupId = filter_var($_POST['groupID'] ?? null, FILTER_VALIDATE_INT);
            $studentId = filter_var($_POST['studentID'] ?? null, FILTER_VALIDATE_INT);
            $taskTitle = trim((string) ($_POST['taskTitle'] ?? ''));
            $taskDetails = trim((string) ($_POST['taskDetails'] ?? ''));
            $taskDeadline = groupActivityDateTime((string) ($_POST['taskDeadline'] ?? ''), false);
            if ($groupId === false || $groupId < 1 || $studentId === false || $studentId < 1
                || $taskTitle === '' || mb_strlen($taskTitle) > 150 || mb_strlen($taskDetails) > 2000
            ) {
                throw new DomainException('Enter a valid task title, member, and task description.');
            }

            $ownedGroup = $pdo->prepare(
                'SELECT groupID FROM tblgroupactivitygroup WHERE groupID = ? AND leaderID = ?'
            );
            $ownedGroup->execute([(int) $groupId, $userId]);
            if (!$ownedGroup->fetch()) {
                throw new DomainException('You can only assign tasks within your assigned group.');
            }

            $memberStatement = $pdo->prepare(
                'SELECT studentID FROM tblgroupactivitymember WHERE groupID = ? AND studentID = ?'
            );
            $memberStatement->execute([(int) $groupId, (int) $studentId]);
            if (!$memberStatement->fetch()) {
                throw new DomainException('Tasks can only be assigned to members of your group.');
            }

            $taskStatement = $pdo->prepare(
                'INSERT INTO tblgroupactivitytask (groupID, assignedTo, createdBy, taskTitle, taskDetails, deadline)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $taskStatement->execute([
                (int) $groupId,
                (int) $studentId,
                $userId,
                $taskTitle,
                $taskDetails,
                $taskDeadline,
            ]);
            groupActivityFlash('Task assigned to the group member.');
            groupActivityRedirect();
        }

        if ($action === 'set_task_completion') {
            $taskId = filter_var($_POST['taskID'] ?? null, FILTER_VALIDATE_INT);
            $isComplete = (string) ($_POST['isComplete'] ?? '');
            if ($taskId === false || $taskId < 1 || !in_array($isComplete, ['0', '1'], true)) {
                throw new DomainException('Choose a valid task status.');
            }

            $taskStatement = $pdo->prepare(
                'SELECT t.taskID, t.assignedTo, g.leaderID
                 FROM tblgroupactivitytask t
                 JOIN tblgroupactivitygroup g ON g.groupID = t.groupID
                 WHERE t.taskID = ?'
            );
            $taskStatement->execute([(int) $taskId]);
            $task = $taskStatement->fetch();
            $isAssignee = $task && !$isAdmin && (int) $task['assignedTo'] === $userId;
            $isAssignedLeader = $task && $isGroupLeader && (int) $task['leaderID'] === $userId;
            if (!$isAssignee && !$isAssignedLeader) {
                throw new DomainException('Only the assigned student or their group leader can update this task.');
            }

            $completionStatement = $pdo->prepare(
                'UPDATE tblgroupactivitytask
                 SET isComplete = ?, completedAt = IF(? = 1, CURRENT_TIMESTAMP, NULL)
                 WHERE taskID = ?'
            );
            $completionStatement->execute([(int) $isComplete, (int) $isComplete, (int) $taskId]);
            groupActivityFlash($isComplete === '1' ? 'Task marked complete.' : 'Task marked incomplete.');
            groupActivityRedirect();
        }

        throw new DomainException('Choose a valid activity action.');
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        groupActivityFlash($exception->getMessage(), true);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Group activity operation failed: ' . $exception->getMessage());
        groupActivityFlash('The activity change could not be saved. Refresh and try again.', true);
    }

    groupActivityRedirect();
}

$subjectFilter = trim((string) ($_GET['subject'] ?? ''));
$activitiesSql = 'SELECT a.activityID, a.blockID, a.subject, a.activityTitle, a.activityDescription,
                         a.deadline, a.createdAt, s.studentFirstName, s.studentLastName,
                         (SELECT COUNT(*) FROM tblgroupactivitygroup g WHERE g.activityID = a.activityID) AS groupCount
                  FROM tblgroupactivity a
                  JOIN tblstudent s ON s.studentID = a.createdBy
                  WHERE ';
$activityParams = [];
if ($isAdmin) {
    $activitiesSql .= '1 = 1';
} elseif ($isBlockRep && $blockId !== null) {
    $activitiesSql .= 'a.blockID = ?';
    $activityParams[] = $blockId;
} else {
    $activitiesSql .= 'EXISTS (
        SELECT 1
        FROM tblgroupactivitygroup visibleGroup
        JOIN tblgroupactivitymember visibleMember ON visibleMember.groupID = visibleGroup.groupID
        WHERE visibleGroup.activityID = a.activityID AND visibleMember.studentID = ?
    )';
    $activityParams[] = $userId;
}
if ($subjectFilter !== '') {
    $activitiesSql .= ' AND a.subject = ?';
    $activityParams[] = $subjectFilter;
}
$activitiesSql .= ' ORDER BY a.deadline ASC, a.activityID DESC';
$activityStatement = $pdo->prepare($activitiesSql);
$activityStatement->execute($activityParams);
$activities = $activityStatement->fetchAll();

$subjectSql = 'SELECT DISTINCT a.subject FROM tblgroupactivity a WHERE ';
$subjectParams = [];
if ($isAdmin) {
    $subjectSql .= '1 = 1';
} elseif ($isBlockRep && $blockId !== null) {
    $subjectSql .= 'a.blockID = ?';
    $subjectParams[] = $blockId;
} else {
    $subjectSql .= 'EXISTS (
        SELECT 1
        FROM tblgroupactivitygroup visibleGroup
        JOIN tblgroupactivitymember visibleMember ON visibleMember.groupID = visibleGroup.groupID
        WHERE visibleGroup.activityID = a.activityID AND visibleMember.studentID = ?
    )';
    $subjectParams[] = $userId;
}
$subjectSql .= ' ORDER BY a.subject';
$subjectStatement = $pdo->prepare($subjectSql);
$subjectStatement->execute($subjectParams);
$subjects = $subjectStatement->fetchAll(PDO::FETCH_COLUMN);

$leaderChoices = [];
if ($isBlockRep && $blockId !== null) {
    $leaderStatement = $pdo->prepare(
        'SELECT studentID, studentFirstName, studentLastName
         FROM tblstudent
         WHERE blockID = ? AND archivedAt IS NULL AND isGroupLeader = 1
         ORDER BY studentLastName, studentFirstName'
    );
    $leaderStatement->execute([$blockId]);
    $leaderChoices = $leaderStatement->fetchAll();
}

$activityGroups = [];
$groupMembers = [];
$groupTasks = [];
$groupTimelines = [];
$availableMembers = [];
foreach ($activities as $activity) {
    $groupSql = 'SELECT g.groupID, g.activityID, g.groupName, g.leaderID,
                        leader.studentFirstName AS leaderFirstName, leader.studentLastName AS leaderLastName
                 FROM tblgroupactivitygroup g
                 JOIN tblstudent leader ON leader.studentID = g.leaderID
                 WHERE g.activityID = ?';
    $groupParams = [(int) $activity['activityID']];
    if (!$isAdmin && !$isBlockRep) {
        $groupSql .= ' AND (
            g.leaderID = ? OR EXISTS (
                SELECT 1 FROM tblgroupactivitymember memberAccess
                WHERE memberAccess.groupID = g.groupID AND memberAccess.studentID = ?
            )
        )';
        $groupParams[] = $userId;
        $groupParams[] = $userId;
    }
    $groupSql .= ' ORDER BY g.groupID';
    $groupStatement = $pdo->prepare($groupSql);
    $groupStatement->execute($groupParams);
    $activityGroups[(int) $activity['activityID']] = $groupStatement->fetchAll();

    foreach ($activityGroups[(int) $activity['activityID']] as $group) {
        $groupId = (int) $group['groupID'];
        $memberStatement = $pdo->prepare(
            'SELECT m.studentID, s.studentFirstName, s.studentLastName
             FROM tblgroupactivitymember m
             JOIN tblstudent s ON s.studentID = m.studentID
             WHERE m.groupID = ?
             ORDER BY s.studentLastName, s.studentFirstName'
        );
        $memberStatement->execute([$groupId]);
        $groupMembers[$groupId] = $memberStatement->fetchAll();

        $taskStatement = $pdo->prepare(
            'SELECT t.taskID, t.assignedTo, t.isComplete, t.completedAt,
                    t.taskTitle, t.taskDetails, t.deadline, t.createdAt,
                    s.studentFirstName, s.studentLastName
             FROM tblgroupactivitytask t
             JOIN tblstudent s ON s.studentID = t.assignedTo
             WHERE t.groupID = ?
             ORDER BY t.deadline IS NULL, t.deadline, t.taskID DESC'
        );
        $taskStatement->execute([$groupId]);
        $groupTasks[$groupId] = $taskStatement->fetchAll();

        $timelineStatement = $pdo->prepare(
            'SELECT timelineID, entryTitle, entryDetails, createdAt
             FROM tblgroupactivitytimeline
             WHERE groupID = ?
             ORDER BY createdAt DESC, timelineID DESC'
        );
        $timelineStatement->execute([$groupId]);
        $groupTimelines[$groupId] = $timelineStatement->fetchAll();

        if ($isBlockRep && $blockId !== null) {
            $availableStatement = $pdo->prepare(
                'SELECT s.studentID, s.studentFirstName, s.studentLastName
                 FROM tblstudent s
                 WHERE s.blockID = ? AND s.archivedAt IS NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM tblgroupactivitymember existingMember
                       WHERE existingMember.activityID = ? AND existingMember.studentID = s.studentID
                   )
                 ORDER BY s.studentLastName, s.studentFirstName'
            );
            $availableStatement->execute([$blockId, (int) $activity['activityID']]);
            $availableMembers[$groupId] = $availableStatement->fetchAll();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-sidebar="closed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group activities</title>
    <link rel="stylesheet" href="./assets/group_activities.css">
    <link rel="stylesheet" href="./assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="ga-page">
    <header class="ga-header">
        <div>
            <a class="ga-back" href="./dashboard.php">← Back to dashboard</a>
            <p class="ga-kicker">ComfSci · Group workspace</p>
            <h1>Group activities</h1>
            <p class="ga-intro">Coordinate subject activities, group tasks, and progress in one place.</p>
        </div>
        <span class="ga-role"><?= $isAdmin ? 'Administrator' : ($isBlockRep ? 'Block representative' : ($isGroupLeader ? 'Group leader' : 'Student')) ?></span>
    </header>

    <?php if ($message !== ''): ?>
        <p class="ga-notice<?= $isError ? ' ga-notice-error' : '' ?>" role="status"><?= groupActivityEscape($message) ?></p>
    <?php endif; ?>

    <?php if ($isBlockRep): ?>
        <section class="ga-panel ga-create">
            <div class="ga-section-heading">
                <div><p class="ga-kicker">Block representative</p><h2>Create an activity</h2></div>
                <p>Choose leaders, then assign members manually or distribute the block automatically.</p>
            </div>
            <?php if (!$leaderChoices): ?>
                <p class="ga-empty">No active group leaders are set for your block yet.</p>
            <?php else: ?>
                <form method="post" class="ga-form">
                    <input type="hidden" name="csrf" value="<?= groupActivityEscape((string) $_SESSION['groupActivityCsrf']) ?>">
                    <input type="hidden" name="action" value="create_activity">
                    <div class="ga-form-grid">
                        <label>Subject
                            <input name="subject" maxlength="100" required placeholder="e.g. Object-Oriented Programming">
                        </label>
                        <label>Activity title
                            <input name="title" maxlength="150" required placeholder="e.g. Design patterns case study">
                        </label>
                        <label>Deadline
                            <input type="datetime-local" name="deadline" required>
                        </label>
                        <label>Member assignment
                            <select name="assignmentMode">
                                <option value="manual">Manual — add members to each group below</option>
                                <option value="random">Random distribution</option>
                                <option value="round_robin">Cyclic / round-robin</option>
                            </select>
                        </label>
                        <label class="ga-wide">Description
                            <textarea name="description" rows="3" maxlength="3000" placeholder="Activity instructions or expected outcome"></textarea>
                        </label>
                        <label class="ga-wide">Group leaders
                            <select name="leaders[]" multiple required size="<?= min(5, max(2, count($leaderChoices))) ?>">
                                <?php foreach ($leaderChoices as $leader): ?>
                                    <option value="<?= (int) $leader['studentID'] ?>"><?= groupActivityEscape($leader['studentLastName'] . ', ' . $leader['studentFirstName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small>Use Ctrl (Windows) or Command (Mac) to select multiple leaders.</small>
                        </label>
                    </div>
                    <button class="ga-button" type="submit">Create activity</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="ga-panel">
        <div class="ga-section-heading">
            <div><p class="ga-kicker">Activities</p><h2>Activity list</h2></div>
            <form method="get" class="ga-filter">
                <label for="subject-filter">Filter by subject</label>
                <select id="subject-filter" name="subject">
                    <option value="">All subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= groupActivityEscape((string) $subject) ?>"<?= $subjectFilter === $subject ? ' selected' : '' ?>><?= groupActivityEscape((string) $subject) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="ga-button ga-button-light" type="submit">Filter</button>
            </form>
        </div>

        <?php if (!$activities): ?>
            <p class="ga-empty">No group activities are available<?= $subjectFilter !== '' ? ' for this subject' : '' ?>.</p>
        <?php endif; ?>

        <?php foreach ($activities as $activity): ?>
            <article class="ga-activity">
                <div class="ga-activity-heading">
                    <div>
                        <span class="ga-subject"><?= groupActivityEscape($activity['subject']) ?></span>
                        <h3><?= groupActivityEscape($activity['activityTitle']) ?></h3>
                    </div>
                    <div class="ga-deadline"><small>Deadline</small><strong><?= groupActivityEscape(date('M j, Y · g:i A', strtotime((string) $activity['deadline']))) ?></strong></div>
                </div>
                <?php if ((string) $activity['activityDescription'] !== ''): ?>
                    <p class="ga-description"><?= nl2br(groupActivityEscape($activity['activityDescription'])) ?></p>
                <?php endif; ?>
                <p class="ga-meta">Created by <?= groupActivityEscape($activity['studentFirstName'] . ' ' . $activity['studentLastName']) ?> · <?= (int) $activity['groupCount'] ?> group<?= (int) $activity['groupCount'] === 1 ? '' : 's' ?></p>

                <div class="ga-groups">
                    <?php foreach ($activityGroups[(int) $activity['activityID']] as $group): ?>
                        <?php $groupId = (int) $group['groupID']; ?>
                        <?php $isThisLeader = $isGroupLeader && (int) $group['leaderID'] === $userId; ?>
                        <section class="ga-group">
                            <div class="ga-group-heading">
                                <h4><?= groupActivityEscape($group['groupName']) ?></h4>
                                <span>Leader: <?= groupActivityEscape($group['leaderFirstName'] . ' ' . $group['leaderLastName']) ?></span>
                            </div>
                            <div class="ga-group-columns">
                                <div>
                                    <h5>Members</h5>
                                    <ul class="ga-list">
                                        <?php foreach ($groupMembers[$groupId] as $member): ?>
                                            <li><?= groupActivityEscape($member['studentFirstName'] . ' ' . $member['studentLastName']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div>
                                    <h5>Tasks</h5>
                                    <?php if (!$groupTasks[$groupId]): ?><p class="ga-muted">No tasks assigned yet.</p><?php endif; ?>
                                    <ul class="ga-list ga-task-list">
                                        <?php foreach ($groupTasks[$groupId] as $task): ?>
                                            <li>
                                                <strong><?= groupActivityEscape($task['taskTitle']) ?> <span class="ga-task-state<?= (int) $task['isComplete'] === 1 ? ' is-complete' : '' ?>"><?= (int) $task['isComplete'] === 1 ? 'Complete' : 'Open' ?></span></strong>
                                                <span>→ <?= groupActivityEscape($task['studentFirstName'] . ' ' . $task['studentLastName']) ?></span>
                                                <?php if (!empty($task['deadline'])): ?><small>Due <?= groupActivityEscape(date('M j, Y · g:i A', strtotime((string) $task['deadline']))) ?></small><?php endif; ?>
                                                <?php if ((string) $task['taskDetails'] !== ''): ?><small><?= nl2br(groupActivityEscape($task['taskDetails'])) ?></small><?php endif; ?>
                                                <?php if (!empty($task['completedAt'])): ?><small>Completed <?= groupActivityEscape(date('M j, Y · g:i A', strtotime((string) $task['completedAt']))) ?></small><?php endif; ?>
                                                <?php if (!$isAdmin && ((int) $task['assignedTo'] === $userId || $isThisLeader)): ?>
                                                    <form method="post" class="ga-task-status-form">
                                                        <input type="hidden" name="csrf" value="<?= groupActivityEscape((string) $_SESSION['groupActivityCsrf']) ?>">
                                                        <input type="hidden" name="action" value="set_task_completion">
                                                        <input type="hidden" name="taskID" value="<?= (int) $task['taskID'] ?>">
                                                        <input type="hidden" name="isComplete" value="<?= (int) $task['isComplete'] === 1 ? '0' : '1' ?>">
                                                        <button class="ga-button ga-button-small ga-button-light" type="submit"><?= (int) $task['isComplete'] === 1 ? 'Reopen task' : 'Mark complete' ?></button>
                                                    </form>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>

                            <div class="ga-timeline">
                                <h5>Timeline</h5>
                                <?php if (!$groupTimelines[$groupId]): ?><p class="ga-muted">No progress entries yet.</p><?php endif; ?>
                                <?php foreach ($groupTimelines[$groupId] as $entry): ?>
                                    <article class="ga-timeline-entry">
                                        <strong><?= groupActivityEscape($entry['entryTitle']) ?></strong>
                                        <small><?= groupActivityEscape(date('M j, Y · g:i A', strtotime((string) $entry['createdAt']))) ?></small>
                                        <?php if ((string) $entry['entryDetails'] !== ''): ?><p><?= nl2br(groupActivityEscape($entry['entryDetails'])) ?></p><?php endif; ?>
                                        <?php if ($isThisLeader): ?>
                                            <div class="ga-timeline-actions">
                                                <form method="post" class="ga-timeline-edit-form">
                                                    <input type="hidden" name="csrf" value="<?= groupActivityEscape((string) $_SESSION['groupActivityCsrf']) ?>">
                                                    <input type="hidden" name="action" value="edit_timeline">
                                                    <input type="hidden" name="groupID" value="<?= $groupId ?>">
                                                    <input type="hidden" name="timelineID" value="<?= (int) $entry['timelineID'] ?>">
                                                    <label>Title<input name="entryTitle" maxlength="150" required value="<?= groupActivityEscape($entry['entryTitle']) ?>"></label>
                                                    <label>Details<textarea name="entryDetails" rows="2" maxlength="2000"><?= groupActivityEscape($entry['entryDetails']) ?></textarea></label>
                                                    <button class="ga-button ga-button-small ga-button-light" type="submit">Save edit</button>
                                                </form>
                                                <form method="post" class="ga-timeline-delete-form">
                                                    <input type="hidden" name="csrf" value="<?= groupActivityEscape((string) $_SESSION['groupActivityCsrf']) ?>">
                                                    <input type="hidden" name="action" value="delete_timeline">
                                                    <input type="hidden" name="groupID" value="<?= $groupId ?>">
                                                    <input type="hidden" name="timelineID" value="<?= (int) $entry['timelineID'] ?>">
                                                    <button class="ga-button ga-button-small ga-button-danger" type="submit">Delete entry</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($isBlockRep): ?>
                                <form method="post" class="ga-inline-form">
                                    <input type="hidden" name="csrf" value="<?= groupActivityEscape((string) $_SESSION['groupActivityCsrf']) ?>">
                                    <input type="hidden" name="action" value="add_member">
                                    <input type="hidden" name="groupID" value="<?= $groupId ?>">
                                    <label>Add a block member
                                        <select name="studentID" required>
                                            <option value="">Choose student</option>
                                            <?php foreach ($availableMembers[$groupId] as $member): ?>
                                                <option value="<?= (int) $member['studentID'] ?>"><?= groupActivityEscape($member['studentLastName'] . ', ' . $member['studentFirstName']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <button class="ga-button ga-button-small" type="submit">Add member</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($isThisLeader): ?>
                                <div class="ga-leader-tools">
                                    <form method="post" class="ga-tool-form">
                                        <input type="hidden" name="csrf" value="<?= groupActivityEscape((string) $_SESSION['groupActivityCsrf']) ?>">
                                        <input type="hidden" name="action" value="add_timeline">
                                        <input type="hidden" name="groupID" value="<?= $groupId ?>">
                                        <h5>Add timeline entry</h5>
                                        <label>Entry title<input name="entryTitle" maxlength="150" required></label>
                                        <label>Details<textarea name="entryDetails" rows="2" maxlength="2000"></textarea></label>
                                        <button class="ga-button ga-button-small" type="submit">Post update</button>
                                    </form>
                                    <form method="post" class="ga-tool-form">
                                        <input type="hidden" name="csrf" value="<?= groupActivityEscape((string) $_SESSION['groupActivityCsrf']) ?>">
                                        <input type="hidden" name="action" value="add_task">
                                        <input type="hidden" name="groupID" value="<?= $groupId ?>">
                                        <h5>Assign a task</h5>
                                        <label>Group member
                                            <select name="studentID" required>
                                                <option value="">Choose member</option>
                                                <?php foreach ($groupMembers[$groupId] as $member): ?>
                                                    <option value="<?= (int) $member['studentID'] ?>"><?= groupActivityEscape($member['studentLastName'] . ', ' . $member['studentFirstName']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>Task title<input name="taskTitle" maxlength="150" required></label>
                                        <label>Details<textarea name="taskDetails" rows="2" maxlength="2000"></textarea></label>
                                        <label>Due date (optional)<input type="datetime-local" name="taskDeadline"></label>
                                        <button class="ga-button ga-button-small" type="submit">Assign task</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
    <footer class="ga-footer">Group activities · ComfSci</footer>
</main>
<?php require __DIR__ . '/includes/ai_chat_widget.php'; ?>
</body>
</html>
