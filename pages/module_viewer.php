<?php

declare(strict_types=1);

/**
 * In-app module PDF/document viewer with ComfiBot context so the AI
 * can answer questions about the open resource.
 */

require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/../auth/roles.php';
require_once __DIR__ . '/../config/database.php';

$userContext = requireAnyRole(['student', 'block_rep', 'admin']);
$isAdmin = in_array('admin', $userContext['roles'], true);

$moduleId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($moduleId === false || $moduleId === null) {
    http_response_code(400);
    exit('Invalid module id.');
}

try {
    $pdo = db();
    $statement = $pdo->prepare(
        'SELECT moduleId, course, subject, title, description, resourceUrl,
                fileName, originalName, mimeType, fileSize, blockId, blockName, dateAndTimeAdded
         FROM moduleSources
         WHERE moduleId = ?
         LIMIT 1'
    );
    $statement->execute([(int) $moduleId]);
    $module = $statement->fetch();
} catch (PDOException $exception) {
    error_log('Module viewer query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load this module.');
}

if (!$module) {
    http_response_code(404);
    exit('Module not found.');
}

if (!$isAdmin) {
    $userBlock = (int) ($userContext['blockID'] ?? 0);
    if ($userBlock < 1 || (int) $module['blockId'] !== $userBlock) {
        http_response_code(403);
        exit('You do not have access to this module.');
    }
}

$hasFile = !empty($module['fileName']);
$mime = (string) ($module['mimeType'] ?? '');
$isPdf = $hasFile && (stripos($mime, 'pdf') !== false
    || preg_match('/\.pdf$/i', (string) ($module['originalName'] ?? '')));

$viewUrl = $hasFile
    ? './api/module_sources/view.php?id=' . (int) $module['moduleId']
    : '';
$downloadUrl = $hasFile
    ? './api/module_sources/view.php?id=' . (int) $module['moduleId'] . '&download=1'
    : '';
$externalUrl = '';
if (!empty($module['resourceUrl'])) {
    $scheme = strtolower((string) parse_url((string) $module['resourceUrl'], PHP_URL_SCHEME));
    if (in_array($scheme, ['http', 'https'], true)) {
        $externalUrl = (string) $module['resourceUrl'];
    }
}

/**
 * Extract plain text from a stored PDF for AI context (best-effort).
 */
function moduleViewerExtractPdfText(string $storedName, int $maxChars = 14000): string
{
    if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/i', $storedName)) {
        return '';
    }
    $uploadDir = realpath(__DIR__ . '/uploads/module_sources');
    if ($uploadDir === false) {
        return '';
    }
    $path = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
    if (!is_file($path) || !is_readable($path)) {
        return '';
    }

    // Prefer pdftotext when available (poppler-utils).
    if (is_executable('/usr/bin/pdftotext')) {
        $cmd = sprintf(
            '/usr/bin/pdftotext -layout -enc UTF-8 %s - 2>/dev/null',
            escapeshellarg($path)
        );
        $text = (string) shell_exec($cmd);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        $text = trim($text);
        if ($text !== '') {
            if (function_exists('mb_substr')) {
                return mb_substr($text, 0, $maxChars, 'UTF-8');
            }
            return substr($text, 0, $maxChars);
        }
    }

    return '';
}

$pdfText = '';
if ($isPdf && !empty($module['fileName'])) {
    $pdfText = moduleViewerExtractPdfText((string) $module['fileName']);
}

function moduleViewerEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function moduleViewerFormatBytes(?int $bytes): string
{
    if ($bytes === null || $bytes < 0) {
        return '';
    }
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}

$fileSizeLabel = moduleViewerFormatBytes(
    isset($module['fileSize']) && $module['fileSize'] !== null ? (int) $module['fileSize'] : null
);
?>
<!DOCTYPE html>
<html lang="en" data-sidebar="closed">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= moduleViewerEscape((string) $module['title']) ?> · Module Viewer | ComfSci</title>
    <link rel="stylesheet" href="./assets/theme.css?v=1">
    <link rel="stylesheet" href="./assets/sidebar.css?v=1">
    <link rel="stylesheet" href="./assets/module_viewer.css?v=1">
</head>
<body class="comfsci-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="module-viewer-page" data-chat-context>
    <header class="module-viewer-header">
        <a class="module-viewer-back" href="./module_sources.php">← Back to Module Sources</a>
        <p class="module-viewer-eyebrow">MODULE RESOURCE</p>
        <h1><?= moduleViewerEscape((string) $module['title']) ?></h1>
        <p class="module-viewer-meta">
            <?php
            $metaParts = array_filter([
                (string) ($module['course'] ?? ''),
                (string) ($module['subject'] ?? ''),
                (string) ($module['blockName'] ?? ''),
            ]);
            echo moduleViewerEscape(implode(' · ', $metaParts));
            ?>
        </p>
        <?php if (!empty($module['description'])): ?>
            <p class="module-viewer-description"><?= moduleViewerEscape((string) $module['description']) ?></p>
        <?php endif; ?>
        <div class="module-viewer-actions">
            <?php if ($downloadUrl !== ''): ?>
                <a class="module-viewer-button" href="<?= moduleViewerEscape($downloadUrl) ?>">Download</a>
            <?php endif; ?>
            <?php if ($externalUrl !== ''): ?>
                <a class="module-viewer-button module-viewer-button-muted" href="<?= moduleViewerEscape($externalUrl) ?>" target="_blank" rel="noopener noreferrer">Open external link</a>
            <?php endif; ?>
            <?php if ($hasFile && $module['originalName']): ?>
                <span class="module-viewer-file-label">
                    <?= moduleViewerEscape((string) $module['originalName']) ?>
                    <?= $fileSizeLabel !== '' ? ' · ' . moduleViewerEscape($fileSizeLabel) : '' ?>
                </span>
            <?php endif; ?>
        </div>
    </header>

    <section class="module-viewer-stage" aria-label="Document viewer">
        <?php if ($isPdf && $viewUrl !== ''): ?>
            <iframe
                class="module-viewer-frame"
                title="PDF viewer"
                src="<?= moduleViewerEscape($viewUrl) ?>"
            ></iframe>
        <?php elseif ($hasFile && $viewUrl !== ''): ?>
            <div class="module-viewer-fallback">
                <p>This file type cannot be previewed inline.</p>
                <a class="module-viewer-button" href="<?= moduleViewerEscape($downloadUrl !== '' ? $downloadUrl : $viewUrl) ?>">Download file</a>
            </div>
        <?php elseif ($externalUrl !== ''): ?>
            <div class="module-viewer-fallback">
                <p>This module is an external link (no uploaded file).</p>
                <a class="module-viewer-button" href="<?= moduleViewerEscape($externalUrl) ?>" target="_blank" rel="noopener noreferrer">Open link</a>
            </div>
        <?php else: ?>
            <div class="module-viewer-fallback">
                <p>No file or link is available for this module.</p>
            </div>
        <?php endif; ?>
    </section>

    <?php /* Visible to ComfiBot via data-chat-context on <main>; keep compact for the model. */ ?>
    <section class="module-viewer-ai-context" aria-label="Resource summary for assistant">
        <h2 class="module-viewer-sr-only">Resource text for AI assistant</h2>
        <div class="module-viewer-ai-card">
            <p><strong>You are viewing a ComfSci module resource.</strong></p>
            <p>Title: <?= moduleViewerEscape((string) $module['title']) ?></p>
            <?php if (!empty($module['subject'])): ?>
                <p>Subject: <?= moduleViewerEscape((string) $module['subject']) ?></p>
            <?php endif; ?>
            <?php if (!empty($module['course'])): ?>
                <p>Course: <?= moduleViewerEscape((string) $module['course']) ?></p>
            <?php endif; ?>
            <?php if (!empty($module['description'])): ?>
                <p>Description: <?= moduleViewerEscape((string) $module['description']) ?></p>
            <?php endif; ?>
            <?php if ($module['originalName']): ?>
                <p>File name: <?= moduleViewerEscape((string) $module['originalName']) ?></p>
            <?php endif; ?>
            <?php if ($pdfText !== ''): ?>
                <p><strong>Extracted PDF text (for answering questions about this document):</strong></p>
                <pre class="module-viewer-pdf-text"><?= moduleViewerEscape($pdfText) ?></pre>
            <?php elseif ($isPdf): ?>
                <p>PDF text could not be extracted automatically. Answer from the title, subject, and description when possible.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php
$aiChatWidget = __DIR__ . '/includes/ai_chat_widget.php';
if (is_file($aiChatWidget)) {
    require $aiChatWidget;
}
?>
</body>
</html>
