<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/../auth/roles.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/ai_consultation_service.php';

try {
    $userContext = requireAnyRole(['student', 'admin']);
} catch (PDOException $exception) {
    error_log('AI consultation user-context database failure (code ' . (string) $exception->getCode() . ').');
    http_response_code(500);
    exit('AI consultation is temporarily unavailable. Please try again later.');
}

$isAdmin = in_array('admin', $userContext['roles'], true);
$userId = (int) $userContext['id'];
$dailyLimit = 10;
$today = (new DateTimeImmutable('now'))->format('Y-m-d');
$providerConfiguration = aiConsultationProviders();
$message = '';
$messageType = '';
$usageCount = 0;
$adminStudentId = 0;
$adminStudentStatus = null;

if (empty($_SESSION['aiConsultationCsrf'])) {
    $_SESSION['aiConsultationCsrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['aiConsultationCsrf'];

function aiConsultationEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    $database = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = (string) ($_POST['csrfToken'] ?? '');
        if (!hash_equals($csrfToken, $submittedToken)) {
            http_response_code(400);
            $message = 'Your session token is invalid. Refresh the page and try again.';
            $messageType = 'error';
        } elseif (!$isAdmin) {
            http_response_code(403);
            $message = 'Only administrators can manage AI access.';
            $messageType = 'error';
        } else {
            $adminStudentId = filter_var($_POST['studentID'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]) ?: 0;
            $adminAction = (string) ($_POST['adminAction'] ?? '');
            if ($adminStudentId < 1 || !in_array($adminAction, ['revoke', 'restore'], true)) {
                $message = 'Enter a valid student number and choose a valid access action.';
                $messageType = 'error';
            } else {
                $studentStatement = $database->prepare(
                    'SELECT studentID FROM tblstudent WHERE studentID = ? AND archivedAt IS NULL'
                );
                $studentStatement->execute([$adminStudentId]);
                if (!$studentStatement->fetchColumn()) {
                    $message = 'No active student was found with that student number.';
                    $messageType = 'error';
                } else {
                    $revoked = $adminAction === 'revoke' ? 1 : 0;
                    $accessStatement = $database->prepare(
                        'INSERT INTO ai_consultation_access (studentID, isRevoked, changedBy, changedAt)
                         VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                         ON DUPLICATE KEY UPDATE isRevoked = VALUES(isRevoked),
                             changedBy = VALUES(changedBy), changedAt = CURRENT_TIMESTAMP'
                    );
                    $accessStatement->execute([$adminStudentId, $revoked, $userId]);
                    $message = $revoked
                        ? 'AI assistant access has been revoked for that student.'
                        : 'AI assistant access has been restored for that student.';
                    $messageType = 'success';
                }
            }
        }
    }

    if (!$isAdmin) {
        $usageCount = aiConsultationUsageCount($database, $userId, $today);
    }
    if ($isAdmin && $adminStudentId > 0) {
        $statusStatement = $database->prepare(
            'SELECT isRevoked FROM ai_consultation_access WHERE studentID = ?'
        );
        $statusStatement->execute([$adminStudentId]);
        $statusValue = $statusStatement->fetchColumn();
        $adminStudentStatus = $statusValue === false ? false : ((int) $statusValue === 1);
    }
} catch (PDOException $exception) {
    error_log('AI consultation database failure (code ' . (string) $exception->getCode() . ').');
    http_response_code(500);
    $message = 'AI consultation is temporarily unavailable. Please try again later.';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en" data-sidebar="closed">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Assistant | ComfSci</title>
    <link rel="stylesheet" href="./assets/ai_consultation.css">
    <link rel="stylesheet" href="./assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="consultation-shell">
    <header class="page-header">
        <a class="back-link" href="./dashboard.php">← Back to dashboard</a>
        <p class="eyebrow">COMFSCI LEARNING SUPPORT</p>
        <h1>AI Assistant</h1>
        <p class="intro">Open ComfiBot from the chat button in the lower-right corner to ask about ComfSci or computer science.</p>
    </header>

    <?php if ($providerConfiguration['errors']): ?>
        <div class="notice notice-warning" role="status">
            <?php if ($isAdmin): ?>
                Some configured AI providers are invalid and were skipped:
                <?= aiConsultationEscape(implode(' ', $providerConfiguration['errors'])) ?>
                <?php if ($providerConfiguration['providers']): ?> Valid providers remain available.<?php endif; ?>
                Correct the provider environment settings.
            <?php else: ?>
                AI consultation has invalid provider configuration. Please contact an administrator.
            <?php endif; ?>
        </div>
    <?php elseif (!$providerConfiguration['providers']): ?>
        <div class="notice notice-warning" role="status">
            The AI assistant is not configured yet. Please contact an administrator.
        </div>
    <?php endif; ?>

    <?php if ($message !== ''): ?>
        <div class="notice notice-<?= aiConsultationEscape($messageType) ?>" role="status"><?= aiConsultationEscape($message) ?></div>
    <?php endif; ?>

    <section class="consultation-card" aria-labelledby="assistant-heading">
        <div class="card-heading">
            <div>
                <p class="eyebrow">COMFIBOT</p>
                <h2 id="assistant-heading">Your website and study assistant</h2>
            </div>
            <?php if ($isAdmin): ?>
                <span class="usage-badge">Admin access · no daily cap</span>
            <?php else: ?>
                <span class="usage-badge"><?= max(0, $dailyLimit - $usageCount) ?> of <?= $dailyLimit ?> questions left today</span>
            <?php endif; ?>
        </div>
        <p>Chat history stays in this browser tab and is not saved by ComfSci. The assistant receives the page title and path with each message. Visible page text is sent only when you opt in for that message; review it for personal information first.</p>
    </section>

    <?php if ($isAdmin): ?>
        <section class="admin-card" aria-labelledby="access-heading">
            <p class="eyebrow">ADMIN CONTROLS</p>
            <h2 id="access-heading">Manage student AI access</h2>
            <p>Enter an active student number to revoke or restore access to the AI assistant.</p>
            <form method="post" action="./ai_consultation.php" class="admin-form">
                <input type="hidden" name="csrfToken" value="<?= aiConsultationEscape($csrfToken) ?>">
                <label for="studentID">Student number</label>
                <input id="studentID" name="studentID" type="number" min="1" required
                       value="<?= $adminStudentId > 0 ? aiConsultationEscape((string) $adminStudentId) : '' ?>">
                <div class="admin-actions">
                    <button type="submit" name="adminAction" value="revoke" class="button-danger">Revoke access</button>
                    <button type="submit" name="adminAction" value="restore" class="button-secondary">Restore access</button>
                </div>
            </form>
            <?php if ($adminStudentStatus !== null): ?>
                <p class="access-status">
                    Student <?= aiConsultationEscape((string) $adminStudentId) ?>:
                    <strong><?= $adminStudentStatus ? 'Access revoked' : 'Access enabled' ?></strong>
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/ai_chat_widget.php'; ?>
</body>
</html>
