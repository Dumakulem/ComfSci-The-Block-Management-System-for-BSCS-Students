<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/../auth/roles.php';
require_once __DIR__ . '/../config/database.php';

$userContext = requireAnyRole(['student', 'block_rep', 'admin']);
$isAdmin = in_array('admin', $userContext['roles'], true);
$canManage = $isAdmin || in_array('block_rep', $userContext['roles'], true);
$blocks = [];
$currentBlockName = '';

if (empty($_SESSION['moduleSourcesCsrf'])) {
    $_SESSION['moduleSourcesCsrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['moduleSourcesCsrf'];

if ($isAdmin) {
    $blockStatement = db()->query('SELECT blockID, classBlock FROM tblblock ORDER BY classBlock');
    $blocks = $blockStatement->fetchAll();
} elseif (!empty($userContext['blockID'])) {
    $blockStatement = db()->prepare('SELECT classBlock FROM tblblock WHERE blockID = ? LIMIT 1');
    $blockStatement->execute([(int) $userContext['blockID']]);
    $currentBlockName = (string) ($blockStatement->fetchColumn() ?: '');
}

function moduleSourcesEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" data-sidebar="closed">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Module Sources | ComfSci</title>
    <link rel="stylesheet" href="./assets/module_sources.css">
    <link rel="stylesheet" href="./assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main
    class="module-sources-page"
    id="module-sources-app"
    data-csrf-token="<?= moduleSourcesEscape($csrfToken) ?>"
    data-can-manage="<?= $canManage ? '1' : '0' ?>"
    data-is-admin="<?= $isAdmin ? '1' : '0' ?>"
    data-block-id="<?= (int) ($userContext['blockID'] ?? 0) ?>"
>
    <header class="module-sources-header">
        <a class="module-sources-back" href="./dashboard.php">Back to dashboard</a>
        <p class="module-sources-eyebrow">COMFSCI LEARNING MATERIALS</p>
        <h1>Module Sources</h1>
        <p>Browse course links shared for your block. Administrators and block representatives can add and manage sources.</p>
    </header>

    <section class="module-sources-toolbar" aria-label="Search module sources">
        <label for="module-source-search">Search modules</label>
        <input id="module-source-search" type="search" maxlength="100" placeholder="Search title, subject, course, or link">
        <?php if (!$isAdmin && $currentBlockName !== ''): ?>
            <span class="module-sources-block">Your block: <?= moduleSourcesEscape($currentBlockName) ?></span>
        <?php elseif (!$isAdmin): ?>
            <span class="module-sources-block">No block is assigned to your account.</span>
        <?php endif; ?>
    </section>

    <?php if ($canManage): ?>
        <section class="module-sources-form-card" aria-labelledby="module-form-heading">
            <div class="module-sources-form-heading">
                <div>
                    <p class="module-sources-eyebrow">CONTRIBUTE</p>
                    <h2 id="module-form-heading">Add a module source</h2>
                </div>
                <button class="module-sources-button module-sources-button-muted" id="module-source-cancel" type="button" hidden>Cancel edit</button>
            </div>
            <p class="module-sources-status" id="module-source-form-status" role="status" aria-live="polite"></p>
            <form id="module-source-form">
                <input id="module-source-id" type="hidden">
                <div class="module-sources-fields">
                    <?php if ($isAdmin): ?>
                        <label>
                            Block
                            <select id="module-source-block" required>
                                <option value="">Choose a block</option>
                                <?php foreach ($blocks as $block): ?>
                                    <option value="<?= (int) $block['blockID'] ?>"><?= moduleSourcesEscape((string) $block['classBlock']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php else: ?>
                        <label>
                            Block
                            <input type="text" value="<?= moduleSourcesEscape($currentBlockName) ?>" readonly>
                        </label>
                    <?php endif; ?>
                    <label>
                        Course <span class="module-sources-optional">optional</span>
                        <input id="module-source-course" type="text" maxlength="125">
                    </label>
                    <label>
                        Subject
                        <input id="module-source-subject" type="text" maxlength="100" required>
                    </label>
                    <label class="module-sources-field-wide">
                        Title
                        <input id="module-source-title" type="text" maxlength="180" required>
                    </label>
                    <label class="module-sources-field-wide">
                        Resource URL
                        <input id="module-source-url" type="url" maxlength="500" placeholder="https://..." required>
                    </label>
                    <label class="module-sources-field-wide">
                        Description <span class="module-sources-optional">optional</span>
                        <textarea id="module-source-description" rows="3" maxlength="4000"></textarea>
                    </label>
                </div>
                <button class="module-sources-button" type="submit" id="module-source-submit">Add source</button>
            </form>
        </section>
    <?php endif; ?>

    <p class="module-sources-status" id="module-source-list-status" role="status" aria-live="polite"></p>
    <section class="module-sources-grid" id="module-source-list" aria-live="polite"></section>
</main>
<script src="./assets/module_sources.js" defer></script>
<?php require __DIR__ . '/includes/ai_chat_widget.php'; ?>
</body>
</html>
