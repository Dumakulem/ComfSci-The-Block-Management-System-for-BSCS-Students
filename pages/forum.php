<?php
// Standalone block forum for sticky notes, block chat, and support tickets.

declare(strict_types=1);

require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/../auth/roles.php';
require_once __DIR__ . '/../config/database.php';

$userContext = requireAnyRole(['student', 'block_rep', 'group_leader', 'admin']);
$userId = (int) $userContext['id'];
$userRoles = $userContext['roles'];
$isAdmin = in_array('admin', $userRoles, true);
$isBlockRep = in_array('block_rep', $userRoles, true);
$canManageTickets = $isAdmin || $isBlockRep;
$blockId = $userContext['blockID'] === null ? null : (int) $userContext['blockID'];
$pdo = db();

function forumEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function forumRedirect(string $message, string $kind = 'success'): void
{
    $_SESSION['forumFlash'] = ['message' => $message, 'kind' => $kind];
    header('Location: forum.php');
    exit;
}

function forumRequireStudent(array $context): void
{
    if (!in_array('student', $context['roles'], true) || empty($context['blockID'])) {
        forumRedirect('This action is available to students assigned to a block.', 'error');
    }
}

function forumUploadTicketProof(array $file): ?string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Choose a valid proof attachment.');
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The proof attachment could not be uploaded.');
    }
    if (!isset($file['size']) || (int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Proof attachments must be no larger than 5 MB.');
    }
    if (empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('The proof attachment is invalid.');
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedTypes = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
    ];
    if (!isset($allowedTypes[$extension])) {
        throw new RuntimeException('Use a JPG, PNG, or PDF proof attachment.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file((string) $file['tmp_name']);
    if ($mimeType !== $allowedTypes[$extension]) {
        throw new RuntimeException('The attachment extension does not match its file type.');
    }

    $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'forum';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('The upload directory is unavailable.');
    }
    if (is_link($uploadDirectory)) {
        throw new RuntimeException('The upload directory is invalid.');
    }
    $resolvedDirectory = realpath($uploadDirectory);
    $expectedDirectory = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'forum';
    if ($resolvedDirectory === false || strcasecmp($resolvedDirectory, $expectedDirectory) !== 0) {
        throw new RuntimeException('The upload directory is invalid.');
    }

    $accessRules = $uploadDirectory . DIRECTORY_SEPARATOR . '.htaccess';
    $denyRules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
        . "<IfModule mod_access_compat.c>\nDeny from all\n</IfModule>\nOptions -Indexes\n";
    if (file_put_contents($accessRules, $denyRules, LOCK_EX) === false) {
        throw new RuntimeException('The upload directory could not be secured.');
    }
    @chmod($accessRules, 0640);

    $storedName = bin2hex(random_bytes(24)) . '.' . $extension;
    $targetPath = $uploadDirectory . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        throw new RuntimeException('The proof attachment could not be saved.');
    }
    @chmod($targetPath, 0640);

    return 'uploads/forum/' . $storedName;
}

function forumCanReadAttachment(array $row, array $context): bool
{
    if (in_array('admin', $context['roles'], true)) {
        return true;
    }
    if ((int) $row['blockID'] !== (int) $context['blockID']) {
        return false;
    }
    if (in_array('block_rep', $context['roles'], true)) {
        return true;
    }
    return (int) $row['studentID'] === (int) $context['id'];
}

if (isset($_GET['attachment'])) {
    $relativePath = (string) $_GET['attachment'];
    if (!preg_match('/\Auploads\/forum\/[a-f0-9]{48}\.(?:jpg|png|pdf)\z/', $relativePath)) {
        http_response_code(404);
        exit('Attachment not found.');
    }

    $attachmentStatement = $pdo->prepare(
        'SELECT blockID, studentID, messageType, attachmentPath
         FROM tblmessage
         WHERE attachmentPath = ? AND messageType = \'ticket\'
         LIMIT 1'
    );
    $attachmentStatement->execute([$relativePath]);
    $attachment = $attachmentStatement->fetch();
    if (!$attachment || !forumCanReadAttachment($attachment, $userContext)) {
        http_response_code(404);
        exit('Attachment not found.');
    }

    $uploadDirectory = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'forum');
    $filePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    $expectedDirectory = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'forum';
    if ($uploadDirectory === false || strcasecmp($uploadDirectory, $expectedDirectory) !== 0 || $filePath === false
        || strpos($filePath, $uploadDirectory . DIRECTORY_SEPARATOR) !== 0
        || !is_file($filePath)) {
        http_response_code(404);
        exit('Attachment not found.');
    }

    $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
    header('Content-Type: ' . $mimeTypes[$extension]);
    header('Content-Length: ' . (string) filesize($filePath));
    header('Content-Disposition: attachment; filename="forum-proof.' . $extension . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($filePath);
    exit;
}

$csrfToken = $_SESSION['forumCsrfToken'] ?? '';
if (!is_string($csrfToken) || strlen($csrfToken) !== 64) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['forumCsrfToken'] = $csrfToken;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrfToken'] ?? '');
    if (!hash_equals($csrfToken, $submittedToken)) {
        forumRedirect('Your session token expired. Please try again.', 'error');
    }

    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'create_note') {
            forumRequireStudent($userContext);
            $content = trim((string) ($_POST['messageContent'] ?? ''));
            if ($content === '' || strlen($content) > 2000) {
                throw new RuntimeException('Sticky notes must contain 1–2,000 characters.');
            }

            $todayStatement = $pdo->query("SELECT DATE_FORMAT(CURDATE(), '%Y-%m-%d')");
            $today = (string) $todayStatement->fetchColumn();
            $lockName = 'forum-note-' . substr(hash('sha256', $blockId . ':' . $userId . ':' . $today), 0, 40);
            $lockStatement = $pdo->prepare('SELECT GET_LOCK(?, 5)');
            $lockStatement->execute([$lockName]);
            if ((int) $lockStatement->fetchColumn() !== 1) {
                throw new RuntimeException('Could not reserve today’s sticky note. Please try again.');
            }

            try {
                $existingNote = $pdo->prepare(
                    "SELECT 1 FROM tblmessage
                     WHERE blockID = ? AND studentID = ? AND messageType = 'note'
                       AND createdAt >= CURDATE() AND createdAt < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                     LIMIT 1"
                );
                $existingNote->execute([$blockId, $userId]);
                if ($existingNote->fetchColumn()) {
                    throw new RuntimeException('You have already posted a sticky note for your block today.');
                }

                $insertNote = $pdo->prepare(
                    "INSERT INTO tblmessage (blockID, studentID, messageContent, messageType, createdAt)
                     VALUES (?, ?, ?, 'note', NOW())"
                );
                $insertNote->execute([$blockId, $userId, $content]);
            } finally {
                $releaseLock = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $releaseLock->execute([$lockName]);
            }
            forumRedirect('Your sticky note was posted.');
        }

        if ($action === 'send_message') {
            forumRequireStudent($userContext);
            $content = trim((string) ($_POST['messageContent'] ?? ''));
            if ($content === '' || strlen($content) > 2000) {
                throw new RuntimeException('Messages must contain 1–2,000 characters.');
            }
            $insertMessage = $pdo->prepare(
                "INSERT INTO tblmessage (blockID, studentID, messageContent, messageType, createdAt)
                 VALUES (?, ?, ?, 'message', NOW())"
            );
            $insertMessage->execute([$blockId, $userId, $content]);
            forumRedirect('Your message was sent.');
        }

        if ($action === 'submit_ticket') {
            forumRequireStudent($userContext);
            $category = (string) ($_POST['ticketCategory'] ?? '');
            $content = trim((string) ($_POST['messageContent'] ?? ''));
            if (!in_array($category, ['emergency', 'illness', 'complaint'], true)) {
                throw new RuntimeException('Choose a valid ticket category.');
            }
            if ($content === '' || strlen($content) > 2000) {
                throw new RuntimeException('Ticket details must contain 1–2,000 characters.');
            }

            $attachmentPath = forumUploadTicketProof($_FILES['proofAttachment'] ?? []);
            try {
                $insertTicket = $pdo->prepare(
                    "INSERT INTO tblmessage
                        (blockID, studentID, messageContent, attachmentPath, messageType, ticketCategory, ticketStatus, createdAt)
                     VALUES (?, ?, ?, ?, 'ticket', ?, 'open', NOW())"
                );
                $insertTicket->execute([$blockId, $userId, $content, $attachmentPath, $category]);
            } catch (Throwable $exception) {
                if ($attachmentPath !== null) {
                    $savedPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $attachmentPath);
                    if (is_file($savedPath)) {
                        @unlink($savedPath);
                    }
                }
                throw $exception;
            }
            forumRedirect('Your support ticket was submitted.');
        }

        if ($action === 'update_ticket') {
            if (!$canManageTickets) {
                http_response_code(403);
                exit('You do not have permission to manage tickets.');
            }
            $ticketId = filter_var($_POST['ticketId'] ?? null, FILTER_VALIDATE_INT);
            $status = (string) ($_POST['ticketStatus'] ?? '');
            $response = trim((string) ($_POST['ticketResponse'] ?? ''));
            if (!$ticketId || !in_array($status, ['open', 'in_progress', 'closed'], true)) {
                throw new RuntimeException('Choose a valid ticket and status.');
            }
            if (strlen($response) > 4000) {
                throw new RuntimeException('Responses cannot exceed 4,000 characters.');
            }

            $ticketScope = "messageID = ? AND messageType = 'ticket'";
            $ticketParameters = [$ticketId];
            if (!$isAdmin) {
                $ticketScope .= ' AND blockID = ?';
                $ticketParameters[] = $blockId;
            }
            $updateTicket = $pdo->prepare(
                "UPDATE tblmessage
                 SET ticketStatus = ?, ticketResponse = ?, respondedAt = NOW()
                 WHERE {$ticketScope}"
            );
            $updateTicket->execute(array_merge([$status, $response === '' ? null : $response], $ticketParameters));
            if ($updateTicket->rowCount() < 1) {
                $checkTicket = $pdo->prepare("SELECT 1 FROM tblmessage WHERE {$ticketScope} LIMIT 1");
                $checkTicket->execute($ticketParameters);
                if (!$checkTicket->fetchColumn()) {
                    throw new RuntimeException('That ticket is unavailable.');
                }
            }
            forumRedirect('Ticket status and response were updated.');
        }

        throw new RuntimeException('Choose a valid forum action.');
    } catch (PDOException $exception) {
        error_log('Forum action failed: ' . $exception->getMessage());
        forumRedirect('The request could not be completed. Please try again.', 'error');
    } catch (RuntimeException $exception) {
        forumRedirect($exception->getMessage(), 'error');
    }
}

$flash = $_SESSION['forumFlash'] ?? null;
unset($_SESSION['forumFlash']);
$pageError = '';
$blocks = [];
$selectedBlockId = $blockId;
$notes = [];
$messages = [];
$tickets = [];
$myNoteToday = false;

try {
    if ($isAdmin) {
        $blockStatement = $pdo->query(
            'SELECT DISTINCT blockID FROM tblstudent WHERE archivedAt IS NULL ORDER BY blockID'
        );
        $blocks = array_map('intval', $blockStatement->fetchAll(PDO::FETCH_COLUMN));
        $requestedBlock = filter_var($_GET['block'] ?? null, FILTER_VALIDATE_INT);
        if ($requestedBlock && in_array((int) $requestedBlock, $blocks, true)) {
            $selectedBlockId = (int) $requestedBlock;
        } elseif ($blocks) {
            $selectedBlockId = $blocks[0];
        } else {
            $selectedBlockId = null;
        }
    }

    if ($selectedBlockId !== null) {
        $noteStatement = $pdo->prepare(
            "SELECT m.messageContent, m.createdAt, s.studentFirstName, s.studentLastName
             FROM tblmessage m
             JOIN tblstudent s ON s.studentID = m.studentID
             WHERE m.blockID = ? AND m.messageType = 'note' AND s.archivedAt IS NULL
             ORDER BY m.createdAt DESC LIMIT 100"
        );
        $noteStatement->execute([$selectedBlockId]);
        $notes = $noteStatement->fetchAll();

        $messageStatement = $pdo->prepare(
            "SELECT m.messageContent, m.createdAt, s.studentFirstName, s.studentLastName
             FROM tblmessage m
             JOIN tblstudent s ON s.studentID = m.studentID
             WHERE m.blockID = ? AND m.messageType = 'message' AND s.archivedAt IS NULL
             ORDER BY m.createdAt DESC LIMIT 100"
        );
        $messageStatement->execute([$selectedBlockId]);
        $messages = array_reverse($messageStatement->fetchAll());

        if (!$isAdmin && in_array('student', $userRoles, true)) {
            $todayStatement = $pdo->prepare(
                "SELECT 1 FROM tblmessage
                 WHERE blockID = ? AND studentID = ? AND messageType = 'note'
                   AND createdAt >= CURDATE() AND createdAt < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                 LIMIT 1"
            );
            $todayStatement->execute([$selectedBlockId, $userId]);
            $myNoteToday = (bool) $todayStatement->fetchColumn();
        }
    }

    if ($canManageTickets) {
        if ($isAdmin) {
            $ticketStatement = $pdo->query(
                "SELECT m.messageID, m.blockID, m.studentID, m.messageContent, m.attachmentPath,
                        m.ticketCategory, m.ticketStatus, m.ticketResponse, m.respondedAt, m.createdAt,
                        s.studentFirstName, s.studentLastName
                 FROM tblmessage m
                 JOIN tblstudent s ON s.studentID = m.studentID
                 WHERE m.messageType = 'ticket'
                 ORDER BY m.createdAt DESC LIMIT 200"
            );
        } else {
            $ticketStatement = $pdo->prepare(
                "SELECT m.messageID, m.blockID, m.studentID, m.messageContent, m.attachmentPath,
                        m.ticketCategory, m.ticketStatus, m.ticketResponse, m.respondedAt, m.createdAt,
                        s.studentFirstName, s.studentLastName
                 FROM tblmessage m
                 JOIN tblstudent s ON s.studentID = m.studentID
                 WHERE m.messageType = 'ticket' AND m.blockID = ?
                 ORDER BY m.createdAt DESC LIMIT 200"
            );
            $ticketStatement->execute([$blockId]);
        }
        $tickets = $ticketStatement->fetchAll();
    } else {
        $ticketStatement = $pdo->prepare(
            "SELECT m.messageID, m.blockID, m.studentID, m.messageContent, m.attachmentPath,
                    m.ticketCategory, m.ticketStatus, m.ticketResponse, m.respondedAt, m.createdAt
             FROM tblmessage m
             WHERE m.messageType = 'ticket' AND m.blockID = ? AND m.studentID = ?
             ORDER BY m.createdAt DESC LIMIT 100"
        );
        $ticketStatement->execute([$blockId, $userId]);
        $tickets = $ticketStatement->fetchAll();
    }
} catch (PDOException $exception) {
    error_log('Forum page query failed: ' . $exception->getMessage());
    $pageError = 'Forum information is temporarily unavailable.';
}

$categoryLabels = ['emergency' => 'Emergency', 'illness' => 'Illness', 'complaint' => 'Complaint'];
$statusLabels = ['open' => 'Open', 'in_progress' => 'In progress', 'closed' => 'Closed'];
?>
<!doctype html>
<html lang="en" data-sidebar="closed">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Block Forum</title>
    <link rel="stylesheet" href="assets/forum.css">
    <link rel="stylesheet" href="assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="forum-shell">
    <header class="forum-header">
        <div>
            <p class="eyebrow">ComfSci • Block community</p>
            <h1>Block Forum</h1>
            <p class="header-copy">Share a daily note, catch up with your block, or reach out for support.</p>
        </div>
        <a class="dashboard-link" href="dashboard.php">Back to dashboard</a>
    </header>

    <?php if ($flash): ?>
        <div class="notice notice-<?= forumEscape($flash['kind'] ?? 'success') ?>" role="status">
            <?= forumEscape($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>
    <?php if ($pageError !== ''): ?>
        <div class="notice notice-error" role="alert"><?= forumEscape($pageError) ?></div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <form class="block-picker" method="get" action="forum.php">
            <label for="block-picker">Preview block conversation</label>
            <select id="block-picker" name="block" onchange="this.form.submit()">
                <?php foreach ($blocks as $availableBlock): ?>
                    <option value="<?= (int) $availableBlock ?>" <?= $selectedBlockId === $availableBlock ? 'selected' : '' ?>>
                        Block <?= (int) $availableBlock ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>

    <?php if ($selectedBlockId !== null): ?>
        <section class="forum-section notes-section" aria-labelledby="notes-heading">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">A little from everyone</p>
                    <h2 id="notes-heading">Block sticky notes</h2>
                </div>
                <span class="section-count"><?= count($notes) ?> recent</span>
            </div>
            <?php if (in_array('student', $userRoles, true)): ?>
                <form class="composer note-composer" method="post" action="forum.php">
                    <input type="hidden" name="csrfToken" value="<?= forumEscape($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_note">
                    <label for="note-content">Your one note for today</label>
                    <textarea id="note-content" name="messageContent" maxlength="2000" rows="3"
                              placeholder="Leave a reminder, shout-out, or thought…" required <?= $myNoteToday ? 'disabled' : '' ?>></textarea>
                    <div class="composer-footer">
                        <span><?= $myNoteToday ? 'You’ve already posted today.' : 'One note per student, per block, each day.' ?></span>
                        <button type="submit" <?= $myNoteToday ? 'disabled' : '' ?>>Post sticky note</button>
                    </div>
                </form>
            <?php endif; ?>
            <?php if (!$notes): ?>
                <p class="empty-state">No sticky notes yet. Start the conversation today.</p>
            <?php else: ?>
                <div class="note-grid">
                    <?php foreach ($notes as $note): ?>
                        <article class="sticky-note">
                            <p><?= nl2br(forumEscape($note['messageContent'])) ?></p>
                            <footer>
                                <span><?= forumEscape($note['studentFirstName'] . ' ' . $note['studentLastName']) ?></span>
                                <time datetime="<?= forumEscape(date(DATE_ATOM, strtotime((string) $note['createdAt']))) ?>">
                                    <?= forumEscape(date('M j, g:i a', strtotime((string) $note['createdAt']))) ?>
                                </time>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="forum-section chat-section" aria-labelledby="chat-heading">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Block <?= (int) $selectedBlockId ?></p>
                    <h2 id="chat-heading">Block chat</h2>
                </div>
                <span class="section-count">Shared conversation</span>
            </div>
            <div class="chat-list" aria-live="polite">
                <?php if (!$messages): ?>
                    <p class="empty-state">No messages yet. Say hello to your block.</p>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <article class="chat-message">
                            <div class="message-meta">
                                <strong><?= forumEscape($message['studentFirstName'] . ' ' . $message['studentLastName']) ?></strong>
                                <time datetime="<?= forumEscape(date(DATE_ATOM, strtotime((string) $message['createdAt']))) ?>">
                                    <?= forumEscape(date('M j, g:i a', strtotime((string) $message['createdAt']))) ?>
                                </time>
                            </div>
                            <p><?= nl2br(forumEscape($message['messageContent'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if (in_array('student', $userRoles, true)): ?>
                <form class="composer chat-composer" method="post" action="forum.php">
                    <input type="hidden" name="csrfToken" value="<?= forumEscape($csrfToken) ?>">
                    <input type="hidden" name="action" value="send_message">
                    <label class="sr-only" for="chat-content">Write a message</label>
                    <textarea id="chat-content" name="messageContent" maxlength="2000" rows="2"
                              placeholder="Write a message to your block…" required></textarea>
                    <button type="submit">Send message</button>
                </form>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="forum-section"><p class="empty-state">No active blocks are available.</p></section>
    <?php endif; ?>

    <?php if (in_array('student', $userRoles, true)): ?>
        <section class="forum-section ticket-submit-section" aria-labelledby="ticket-submit-heading">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Private support request</p>
                    <h2 id="ticket-submit-heading">Submit a ticket</h2>
                </div>
                <span class="section-count">Visible to your block rep and admins</span>
            </div>
            <form class="ticket-form" method="post" action="forum.php" enctype="multipart/form-data">
                <input type="hidden" name="csrfToken" value="<?= forumEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="submit_ticket">
                <label for="ticket-category">Ticket category</label>
                <select id="ticket-category" name="ticketCategory" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categoryLabels as $category => $label): ?>
                        <option value="<?= forumEscape($category) ?>"><?= forumEscape($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="ticket-content">What would you like help with?</label>
                <textarea id="ticket-content" name="messageContent" maxlength="2000" rows="4" required></textarea>
                <label for="ticket-proof">Proof attachment <span>(optional; JPG, PNG, or PDF; max 5 MB)</span></label>
                <input id="ticket-proof" name="proofAttachment" type="file" accept=".jpg,.png,.pdf,image/jpeg,image/png,application/pdf">
                <button type="submit">Send private ticket</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="forum-section tickets-section" aria-labelledby="tickets-heading">
        <div class="section-heading">
            <div>
                <p class="eyebrow"><?= $canManageTickets ? 'Support inbox' : 'Private to you' ?></p>
                <h2 id="tickets-heading"><?= $canManageTickets ? 'Block tickets' : 'Your tickets' ?></h2>
            </div>
            <span class="section-count"><?= count($tickets) ?> shown</span>
        </div>
        <?php if (!$tickets): ?>
            <p class="empty-state"><?= $canManageTickets ? 'There are no tickets to review.' : 'You have not submitted any tickets yet.' ?></p>
        <?php else: ?>
            <div class="ticket-list">
                <?php foreach ($tickets as $ticket): ?>
                    <?php
                    $ticketStatus = (string) ($ticket['ticketStatus'] ?? 'open');
                    $ticketCategory = (string) ($ticket['ticketCategory'] ?? '');
                    ?>
                    <article class="ticket-card">
                        <header class="ticket-header">
                            <div>
                                <span class="category-pill"><?= forumEscape($categoryLabels[$ticketCategory] ?? 'Support') ?></span>
                                <?php if ($canManageTickets): ?>
                                    <span class="block-tag">Block <?= (int) $ticket['blockID'] ?></span>
                                    <span class="ticket-owner"><?= forumEscape($ticket['studentFirstName'] . ' ' . $ticket['studentLastName']) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="status-pill status-<?= forumEscape($ticketStatus) ?>">
                                <?= forumEscape($statusLabels[$ticketStatus] ?? 'Open') ?>
                            </span>
                        </header>
                        <p class="ticket-content"><?= nl2br(forumEscape($ticket['messageContent'])) ?></p>
                        <?php if (!empty($ticket['attachmentPath'])): ?>
                            <a class="attachment-link" href="forum.php?attachment=<?= rawurlencode((string) $ticket['attachmentPath']) ?>">
                                Download proof attachment
                            </a>
                        <?php endif; ?>
                        <p class="ticket-date">Submitted <?= forumEscape(date('M j, Y · g:i a', strtotime((string) $ticket['createdAt']))) ?></p>
                        <?php if (!empty($ticket['ticketResponse'])): ?>
                            <div class="ticket-response">
                                <strong>Response</strong>
                                <p><?= nl2br(forumEscape($ticket['ticketResponse'])) ?></p>
                                <?php if (!empty($ticket['respondedAt'])): ?>
                                    <time datetime="<?= forumEscape(date(DATE_ATOM, strtotime((string) $ticket['respondedAt']))) ?>">
                                        <?= forumEscape(date('M j, g:i a', strtotime((string) $ticket['respondedAt']))) ?>
                                    </time>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($canManageTickets): ?>
                            <form class="ticket-update-form" method="post" action="forum.php">
                                <input type="hidden" name="csrfToken" value="<?= forumEscape($csrfToken) ?>">
                                <input type="hidden" name="action" value="update_ticket">
                                <input type="hidden" name="ticketId" value="<?= (int) $ticket['messageID'] ?>">
                                <label for="ticket-status-<?= (int) $ticket['messageID'] ?>">Status</label>
                                <select id="ticket-status-<?= (int) $ticket['messageID'] ?>" name="ticketStatus">
                                    <?php foreach ($statusLabels as $status => $label): ?>
                                        <option value="<?= forumEscape($status) ?>" <?= $ticketStatus === $status ? 'selected' : '' ?>>
                                            <?= forumEscape($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="ticket-response-<?= (int) $ticket['messageID'] ?>">Response</label>
                                <textarea id="ticket-response-<?= (int) $ticket['messageID'] ?>" name="ticketResponse"
                                          maxlength="4000" rows="2"><?= forumEscape($ticket['ticketResponse'] ?? '') ?></textarea>
                                <button type="submit">Save update</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <footer class="forum-footer">Be kind, keep private information in tickets, and look out for one another.</footer>
</main>
<?php require __DIR__ . '/includes/ai_chat_widget.php'; ?>
</body>
</html>
