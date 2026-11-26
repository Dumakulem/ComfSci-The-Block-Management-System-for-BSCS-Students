<?php

declare(strict_types=1);

// Lists block assignments and lets representatives manage them and students track completion.
require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/../auth/roles.php';

$userContext = requireAnyRole(['student', 'block_rep', 'admin']);
$pdo = db();
$isAdmin = in_array('admin', $userContext['roles'], true);
$isBlockRep = in_array('block_rep', $userContext['roles'], true);
$blockId = $userContext['blockID'];
$studentId = $userContext['id'];

if (empty($_SESSION['assignmentTrackerCsrf'])) {
    $_SESSION['assignmentTrackerCsrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['assignmentTrackerCsrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid form token. Reload the page and try again.');
    }

    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'mark_done') {
            $assignmentId = (int) ($_POST['assignmentID'] ?? 0);
            $done = (int) ($_POST['isCompleted'] ?? 0);
            if ($assignmentId < 1 || !in_array($done, [0, 1], true)) {
                throw new DomainException('Select a valid assignment status.');
            }
            $assignmentStatement = $pdo->prepare(
                'SELECT assignmentID FROM tblassignment
                 WHERE assignmentID = ? AND archivedAt IS NULL
                   AND blockID = (SELECT blockID FROM tblstudent WHERE studentID = ? AND archivedAt IS NULL)'
            );
            $assignmentStatement->execute([$assignmentId, $studentId]);
            if (!$assignmentStatement->fetchColumn()) {
                throw new DomainException('Assignment not found for this block.');
            }
            $completionStatement = $pdo->prepare(
                'INSERT INTO tblassignmentcompletion (assignmentID, studentID, isCompleted, completedAt)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE isCompleted = VALUES(isCompleted),
                    completedAt = VALUES(completedAt)'
            );
            $completionStatement->execute([
                $assignmentId,
                $studentId,
                $done,
                $done ? date('Y-m-d H:i:s') : null,
            ]);
            $_SESSION['assignmentTrackerFlash'] = $done ? 'Assignment marked complete.' : 'Assignment marked incomplete.';
        } elseif (in_array($action, ['create', 'update', 'archive'], true)) {
            if (!$isBlockRep && !$isAdmin) {
                http_response_code(403);
                exit('Only administrators or block representatives can manage block assignments.');
            }
            $assignmentId = (int) ($_POST['assignmentID'] ?? 0);
            if ($action === 'archive') {
                if ($assignmentId < 1) {
                    throw new DomainException('Select a valid assignment.');
                }
                $archiveStatement = $isAdmin
                    ? $pdo->prepare('UPDATE tblassignment SET archivedAt = NOW() WHERE assignmentID = ? AND archivedAt IS NULL')
                    : $pdo->prepare('UPDATE tblassignment SET archivedAt = NOW() WHERE assignmentID = ? AND blockID = ? AND archivedAt IS NULL');
                $archiveStatement->execute($isAdmin ? [$assignmentId] : [$assignmentId, $blockId]);
                if ($archiveStatement->rowCount() !== 1) {
                    throw new DomainException('Assignment not found or already archived.');
                }
                $_SESSION['assignmentTrackerFlash'] = 'Assignment archived.';
            } else {
                $title = trim((string) ($_POST['assignmentTitle'] ?? ''));
                $subject = trim((string) ($_POST['assignmentSubject'] ?? ''));
                $description = trim((string) ($_POST['assignmentDescription'] ?? ''));
                $deadline = trim((string) ($_POST['assignmentDeadline'] ?? ''));
                $targetBlockId = $isAdmin ? (int) ($_POST['blockID'] ?? 0) : (int) $blockId;
                $date = DateTime::createFromFormat('!Y-m-d\TH:i', $deadline);
                if ($title === '' || mb_strlen($title) > 150 || $subject === '' || mb_strlen($subject) > 150
                    || mb_strlen($description) > 10000 || !$date || $date->format('Y-m-d\TH:i') !== $deadline) {
                    throw new DomainException('Enter a title, subject, and valid deadline.');
                }
                $blockStatement = $pdo->prepare(
                    'SELECT blockID FROM tblblock WHERE blockID = ? AND archivedAt IS NULL'
                );
                $blockStatement->execute([$targetBlockId]);
                if (!$blockStatement->fetchColumn()) {
                    throw new DomainException('Choose an active block.');
                }

                if ($action === 'create') {
                    $saveStatement = $pdo->prepare(
                        "INSERT INTO tblassignment
                            (blockID, assignmentTitle, assignmentSubject, assignmentDescription,
                             assignmentStatus, assignmentDeadline)
                         VALUES (?, ?, ?, ?, 'Active', ?)"
                    );
                    $saveStatement->execute([
                        $targetBlockId, $title, $subject, $description === '' ? null : $description,
                        $date->format('Y-m-d H:i:s'),
                    ]);
                    $_SESSION['assignmentTrackerFlash'] = 'Assignment created.';
                } else {
                    if ($assignmentId < 1) {
                        throw new DomainException('Select a valid assignment.');
                    }
                    $saveStatement = $isAdmin
                        ? $pdo->prepare(
                            'UPDATE tblassignment SET blockID = ?, assignmentTitle = ?, assignmentSubject = ?,
                                assignmentDescription = ?, assignmentDeadline = ?
                             WHERE assignmentID = ? AND archivedAt IS NULL'
                        )
                        : $pdo->prepare(
                            'UPDATE tblassignment SET assignmentTitle = ?, assignmentSubject = ?,
                                assignmentDescription = ?, assignmentDeadline = ?
                             WHERE assignmentID = ? AND blockID = ? AND archivedAt IS NULL'
                        );
                    $saveStatement->execute($isAdmin
                        ? [$targetBlockId, $title, $subject, $description === '' ? null : $description, $date->format('Y-m-d H:i:s'), $assignmentId]
                        : [$title, $subject, $description === '' ? null : $description, $date->format('Y-m-d H:i:s'), $assignmentId, $blockId]
                    );
                    if ($saveStatement->rowCount() === 0) {
                        throw new DomainException('Assignment not found or no details changed.');
                    }
                    $_SESSION['assignmentTrackerFlash'] = 'Assignment updated.';
                }
            }
        } else {
            http_response_code(400);
            exit('Invalid assignment action.');
        }
    } catch (DomainException $exception) {
        $_SESSION['assignmentTrackerFlash'] = $exception->getMessage();
        $_SESSION['assignmentTrackerFlashError'] = true;
    } catch (PDOException $exception) {
        error_log('Assignment tracker action failed: ' . $exception->getMessage());
        $_SESSION['assignmentTrackerFlash'] = 'Assignment changes could not be saved.';
        $_SESSION['assignmentTrackerFlashError'] = true;
    }

    header('Location: ./assignments.php');
    exit;
}

$message = (string) ($_SESSION['assignmentTrackerFlash'] ?? '');
$isError = !empty($_SESSION['assignmentTrackerFlashError']);
unset($_SESSION['assignmentTrackerFlash'], $_SESSION['assignmentTrackerFlashError']);

$blockStatement = $isAdmin
    ? $pdo->query('SELECT blockID, classBlock FROM tblblock WHERE archivedAt IS NULL ORDER BY classBlock')
    : $pdo->prepare('SELECT blockID, classBlock FROM tblblock WHERE blockID = ? AND archivedAt IS NULL');
if (!$isAdmin) {
    $blockStatement->execute([$blockId]);
}
$blocks = $blockStatement->fetchAll();

$filterBlockId = $isAdmin ? (int) ($_GET['blockID'] ?? 0) : (int) $blockId;
$filterSubject = trim((string) ($_GET['subject'] ?? ''));
$parameters = [];
$conditions = ['a.archivedAt IS NULL'];
if ($filterBlockId > 0) {
    $conditions[] = 'a.blockID = ?';
    $parameters[] = $filterBlockId;
} elseif (!$isAdmin) {
    $conditions[] = 'a.blockID = ?';
    $parameters[] = (int) $blockId;
}
if ($filterSubject !== '') {
    $conditions[] = 'a.assignmentSubject = ?';
    $parameters[] = $filterSubject;
}
$listStatement = $pdo->prepare(
    'SELECT a.assignmentID, a.blockID, a.assignmentTitle, a.assignmentSubject,
            a.assignmentDescription, a.assignmentDeadline, b.classBlock,
            COALESCE(c.isCompleted, 0) AS isCompleted
     FROM tblassignment a
     JOIN tblblock b ON b.blockID = a.blockID
     LEFT JOIN tblassignmentcompletion c
       ON c.assignmentID = a.assignmentID AND c.studentID = ?
     WHERE ' . implode(' AND ', $conditions) . '
     ORDER BY a.assignmentDeadline'
);
$listStatement->execute(array_merge([$studentId], $parameters));
$assignments = $listStatement->fetchAll();

$subjectStatement = $pdo->prepare(
    'SELECT DISTINCT assignmentSubject FROM tblassignment
     WHERE archivedAt IS NULL AND (? = 0 OR blockID = ?)
     ORDER BY assignmentSubject'
);
$subjectStatement->execute([$filterBlockId, $filterBlockId]);
$subjects = $subjectStatement->fetchAll(PDO::FETCH_COLUMN);

function assignmentTrackerEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-sidebar="closed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Tracker</title>
    <link rel="stylesheet" href="./assets/dashboard.css?v=10">
    <link rel="stylesheet" href="./assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="content" style="max-width:1100px;margin:30px auto">
    <p><a href="./dashboard.php">← Back to dashboard</a></p>
    <section class="hero">
        <h1>Assignment tracker</h1>
        <p>Track assignment deadlines and completion status by subject.</p>
    </section>
    <?php if ($message !== ''): ?>
        <p class="toast" role="status"<?= $isError ? ' style="background:#fff0ee;color:#8f2d22"' : '' ?>><?= assignmentTrackerEscape($message) ?></p>
    <?php endif; ?>

    <form method="get" class="card" style="display:flex;gap:12px;padding:16px;flex-wrap:wrap">
        <?php if ($isAdmin): ?>
            <label>Block
                <select name="blockID">
                    <option value="0">All blocks</option>
                    <?php foreach ($blocks as $block): ?>
                        <option value="<?= (int) $block['blockID'] ?>"<?= $filterBlockId === (int) $block['blockID'] ? ' selected' : '' ?>><?= assignmentTrackerEscape($block['classBlock']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label>Subject
            <select name="subject">
                <option value="">All subjects</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= assignmentTrackerEscape((string) $subject) ?>"<?= $filterSubject === $subject ? ' selected' : '' ?>><?= assignmentTrackerEscape((string) $subject) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-solid" type="submit">Filter</button>
    </form>

    <?php if ($isBlockRep || $isAdmin): ?>
        <details class="card" style="padding:18px;margin:16px 0">
            <summary>Create assignment</summary>
            <form method="post" style="display:grid;gap:12px;margin-top:12px">
                <input type="hidden" name="csrf" value="<?= assignmentTrackerEscape((string) $_SESSION['assignmentTrackerCsrf']) ?>">
                <input type="hidden" name="action" value="create">
                <?php if ($isAdmin): ?>
                    <label>Block
                        <select name="blockID" required>
                            <?php foreach ($blocks as $block): ?><option value="<?= (int) $block['blockID'] ?>"><?= assignmentTrackerEscape($block['classBlock']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <label>Title <input name="assignmentTitle" maxlength="150" required></label>
                <label>Subject <input name="assignmentSubject" maxlength="150" required></label>
                <label>Description <textarea name="assignmentDescription" maxlength="10000"></textarea></label>
                <label>Deadline <input type="datetime-local" name="assignmentDeadline" required></label>
                <button class="btn btn-solid" type="submit">Create assignment</button>
            </form>
        </details>
    <?php endif; ?>

    <?php foreach ($assignments as $assignment): ?>
        <article class="card" style="padding:18px;margin:14px 0">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <div>
                    <h2><?= assignmentTrackerEscape($assignment['assignmentTitle']) ?></h2>
                    <p><?= assignmentTrackerEscape($assignment['assignmentSubject']) ?> · <?= assignmentTrackerEscape($assignment['classBlock']) ?></p>
                    <p>Due <?= assignmentTrackerEscape(date('M j, Y g:i A', strtotime((string) $assignment['assignmentDeadline']))) ?></p>
                    <?php if (!empty($assignment['assignmentDescription'])): ?><p><?= nl2br(assignmentTrackerEscape($assignment['assignmentDescription'])) ?></p><?php endif; ?>
                </div>
                <div>
                    <?php if (in_array('student', $userContext['roles'], true)): ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= assignmentTrackerEscape((string) $_SESSION['assignmentTrackerCsrf']) ?>">
                            <input type="hidden" name="action" value="mark_done">
                            <input type="hidden" name="assignmentID" value="<?= (int) $assignment['assignmentID'] ?>">
                            <input type="hidden" name="isCompleted" value="<?= (int) !$assignment['isCompleted'] ?>">
                            <button class="btn btn-solid" type="submit"><?= $assignment['isCompleted'] ? 'Mark incomplete' : 'Mark complete' ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($isBlockRep || $isAdmin): ?>
                        <details style="margin-top:8px">
                            <summary>Edit</summary>
                            <form method="post" style="display:grid;gap:8px;padding:10px">
                                <input type="hidden" name="csrf" value="<?= assignmentTrackerEscape((string) $_SESSION['assignmentTrackerCsrf']) ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="assignmentID" value="<?= (int) $assignment['assignmentID'] ?>">
                                <?php if ($isAdmin): ?><select name="blockID"><?php foreach ($blocks as $block): ?><option value="<?= (int) $block['blockID'] ?>"<?= (int) $block['blockID'] === (int) $assignment['blockID'] ? ' selected' : '' ?>><?= assignmentTrackerEscape($block['classBlock']) ?></option><?php endforeach; ?></select><?php endif; ?>
                                <input name="assignmentTitle" maxlength="150" value="<?= assignmentTrackerEscape($assignment['assignmentTitle']) ?>" required>
                                <input name="assignmentSubject" maxlength="150" value="<?= assignmentTrackerEscape($assignment['assignmentSubject']) ?>" required>
                                <textarea name="assignmentDescription" maxlength="10000"><?= assignmentTrackerEscape($assignment['assignmentDescription']) ?></textarea>
                                <input type="datetime-local" name="assignmentDeadline" value="<?= assignmentTrackerEscape(date('Y-m-d\TH:i', strtotime((string) $assignment['assignmentDeadline']))) ?>" required>
                                <button class="btn btn-solid" type="submit">Save changes</button>
                            </form>
                            <form method="post" style="padding:10px">
                                <input type="hidden" name="csrf" value="<?= assignmentTrackerEscape((string) $_SESSION['assignmentTrackerCsrf']) ?>">
                                <input type="hidden" name="action" value="archive">
                                <input type="hidden" name="assignmentID" value="<?= (int) $assignment['assignmentID'] ?>">
                                <button class="btn" type="submit">Archive</button>
                            </form>
                        </details>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$assignments): ?><section class="card"><p class="empty">No assignments match your filters.</p></section><?php endif; ?>
</main>
<?php require __DIR__ . '/includes/ai_chat_widget.php'; ?>
</body>
</html>
