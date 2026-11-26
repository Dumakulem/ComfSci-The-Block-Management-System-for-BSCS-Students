<?php

// Provides authenticated resource browsing and admin-only resource and category management.
declare(strict_types=1);

require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/../auth/roles.php';
require_once __DIR__ . '/../config/database.php';

requireAnyRole(['student', 'block_rep', 'group_leader', 'admin']);
$userContext = currentUserContext();
$isAdmin = in_array('admin', $userContext['roles'], true);
$pdo = db();

if (empty($_SESSION['resourceCsrf'])) {
    $_SESSION['resourceCsrf'] = bin2hex(random_bytes(32));
}

function resourceEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function resourceTextLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function resourcePostValue(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function resourceRedirect(): void
{
    header('Location: ./resources.php');
    exit;
}

function resourceValidateExternalUrl(string $url): bool
{
    if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true)
        && (string) parse_url($url, PHP_URL_HOST) !== '';
}

$message = (string) ($_SESSION['resourceFlash'] ?? '');
$isError = !empty($_SESSION['resourceFlashError']);
unset($_SESSION['resourceFlash'], $_SESSION['resourceFlashError']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['resourceCsrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid form token. Reload the page and try again.');
    }

    if (!$isAdmin) {
        http_response_code(403);
        exit('Only administrators can manage resources.');
    }

    $action = resourcePostValue('action');
    $failureMessage = '';

    try {
        if ($action === 'saveResource') {
            $resourceId = (int) ($_POST['resourceID'] ?? 0);
            $categoryId = (int) ($_POST['categoryID'] ?? 0);
            $title = resourcePostValue('title');
            $description = resourcePostValue('description');
            $subject = resourcePostValue('subject');
            $resourceType = resourcePostValue('resourceType');
            $resourceUrl = resourcePostValue('resourceUrl');
            $allowedTypes = ['document', 'video', 'article', 'notes', 'other'];

            if ($resourceId < 0) {
                throw new DomainException('Choose a valid resource.');
            }
            if ($title === '' || resourceTextLength($title) > 150) {
                throw new DomainException('Enter a title of 1–150 characters.');
            }
            if ($description !== '' && resourceTextLength($description) > 2000) {
                throw new DomainException('Descriptions must be 2,000 characters or fewer.');
            }
            if ($subject === '' || resourceTextLength($subject) > 100) {
                throw new DomainException('Enter a subject of 1–100 characters.');
            }
            if (!in_array($resourceType, $allowedTypes, true)) {
                throw new DomainException('Choose a valid resource type.');
            }
            if (!resourceValidateExternalUrl($resourceUrl)) {
                throw new DomainException('Enter a valid external HTTP or HTTPS URL.');
            }

            $categoryStatement = $pdo->prepare('SELECT categoryID FROM tblresourcecategory WHERE categoryID = ?');
            $categoryStatement->execute([$categoryId]);
            if (!$categoryStatement->fetch()) {
                throw new DomainException('Choose an existing category.');
            }

            if ($resourceId > 0) {
                $existingResource = $pdo->prepare('SELECT resourceID FROM tblresource WHERE resourceID = ?');
                $existingResource->execute([$resourceId]);
                if (!$existingResource->fetch()) {
                    throw new DomainException('The resource is no longer available.');
                }
                $statement = $pdo->prepare(
                    'UPDATE tblresource
                     SET categoryID = ?, title = ?, description = ?, subject = ?,
                         resourceType = ?, resourceUrl = ?, updatedAt = CURRENT_TIMESTAMP
                     WHERE resourceID = ?'
                );
                $statement->execute([
                    $categoryId, $title, $description, $subject, $resourceType, $resourceUrl, $resourceId,
                ]);
                $_SESSION['resourceFlash'] = 'Resource updated.';
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO tblresource
                        (categoryID, title, description, subject, resourceType, resourceUrl, adminID)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $statement->execute([
                    $categoryId, $title, $description, $subject, $resourceType, $resourceUrl, $userContext['id'],
                ]);
                $_SESSION['resourceFlash'] = 'Resource created.';
            }
        } elseif ($action === 'deleteResource') {
            $resourceId = (int) ($_POST['resourceID'] ?? 0);
            if ($resourceId < 1) {
                throw new DomainException('Choose a valid resource.');
            }
            $statement = $pdo->prepare('DELETE FROM tblresource WHERE resourceID = ?');
            $statement->execute([$resourceId]);
            $_SESSION['resourceFlash'] = 'Resource deleted.';
        } elseif ($action === 'saveCategory') {
            $categoryId = (int) ($_POST['categoryID'] ?? 0);
            $categoryName = resourcePostValue('categoryName');
            $categoryDescription = resourcePostValue('categoryDescription');

            if ($categoryId < 0) {
                throw new DomainException('Choose a valid category.');
            }
            if ($categoryName === '' || resourceTextLength($categoryName) > 80) {
                throw new DomainException('Enter a category name of 1–80 characters.');
            }
            if ($categoryDescription !== '' && resourceTextLength($categoryDescription) > 255) {
                throw new DomainException('Category descriptions must be 255 characters or fewer.');
            }

            if ($categoryId > 0) {
                $existingCategory = $pdo->prepare(
                    'SELECT categoryID FROM tblresourcecategory WHERE categoryID = ?'
                );
                $existingCategory->execute([$categoryId]);
                if (!$existingCategory->fetch()) {
                    throw new DomainException('The category is no longer available.');
                }
                $statement = $pdo->prepare(
                    'UPDATE tblresourcecategory
                     SET categoryName = ?, categoryDescription = ?, updatedAt = CURRENT_TIMESTAMP
                     WHERE categoryID = ?'
                );
                $statement->execute([$categoryName, $categoryDescription, $categoryId]);
                $_SESSION['resourceFlash'] = 'Category updated.';
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO tblresourcecategory (categoryName, categoryDescription, adminID)
                     VALUES (?, ?, ?)'
                );
                $statement->execute([$categoryName, $categoryDescription, $userContext['id']]);
                $_SESSION['resourceFlash'] = 'Category created.';
            }
        } elseif ($action === 'deleteCategory') {
            $categoryId = (int) ($_POST['categoryID'] ?? 0);
            if ($categoryId < 1) {
                throw new DomainException('Choose a valid category.');
            }
            $statement = $pdo->prepare('DELETE FROM tblresourcecategory WHERE categoryID = ?');
            $statement->execute([$categoryId]);
            $_SESSION['resourceFlash'] = 'Category and its resources deleted.';
        } else {
            throw new DomainException('Unknown resource action.');
        }
    } catch (DomainException $exception) {
        $failureMessage = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log('Resource repository update failed: ' . $exception->getMessage());
        $failureMessage = $exception->getCode() === '23000'
            ? 'That category name is already in use, or the selected item is no longer available.'
            : 'The change could not be saved. Please try again.';
    }

    if ($failureMessage !== '') {
        $_SESSION['resourceFlash'] = $failureMessage;
        $_SESSION['resourceFlashError'] = true;
    }
    resourceRedirect();
}

$categoryStatement = $pdo->query(
    'SELECT categoryID, categoryName, categoryDescription
     FROM tblresourcecategory
     ORDER BY categoryName'
);
$categories = $categoryStatement->fetchAll();

$keyword = trim((string) ($_GET['q'] ?? ''));
$selectedCategory = (int) ($_GET['category'] ?? 0);
$selectedSubject = trim((string) ($_GET['subject'] ?? ''));

$subjectStatement = $pdo->query(
    'SELECT DISTINCT subject FROM tblresource ORDER BY subject'
);
$subjects = $subjectStatement->fetchAll(PDO::FETCH_COLUMN);

$conditions = [];
$parameters = [];
if ($selectedCategory > 0) {
    $conditions[] = 'r.categoryID = ?';
    $parameters[] = $selectedCategory;
}
if ($selectedSubject !== '') {
    $conditions[] = 'r.subject = ?';
    $parameters[] = $selectedSubject;
}
if ($keyword !== '') {
    $conditions[] = '(r.title LIKE ? OR r.description LIKE ? OR r.subject LIKE ?)';
    $searchTerm = '%' . $keyword . '%';
    array_push($parameters, $searchTerm, $searchTerm, $searchTerm);
}

$resourceSql = 'SELECT r.resourceID, r.categoryID, r.title, r.description, r.subject,
                       r.resourceType, r.resourceUrl, r.createdAt, r.updatedAt,
                       c.categoryName
                FROM tblresource r
                INNER JOIN tblresourcecategory c ON c.categoryID = r.categoryID';
if ($conditions) {
    $resourceSql .= ' WHERE ' . implode(' AND ', $conditions);
}
$resourceSql .= ' ORDER BY r.updatedAt DESC, r.title';
$resourceStatement = $pdo->prepare($resourceSql);
$resourceStatement->execute($parameters);
$resources = $resourceStatement->fetchAll();

$editResource = null;
$editResourceId = (int) ($_GET['edit'] ?? 0);
if ($isAdmin && $editResourceId > 0) {
    $editStatement = $pdo->prepare(
        'SELECT resourceID, categoryID, title, description, subject, resourceType, resourceUrl
         FROM tblresource WHERE resourceID = ?'
    );
    $editStatement->execute([$editResourceId]);
    $editResource = $editStatement->fetch() ?: null;
}

$editCategory = null;
$editCategoryId = (int) ($_GET['editCategory'] ?? 0);
if ($isAdmin && $editCategoryId > 0) {
    $editCategoryStatement = $pdo->prepare(
        'SELECT categoryID, categoryName, categoryDescription
         FROM tblresourcecategory WHERE categoryID = ?'
    );
    $editCategoryStatement->execute([$editCategoryId]);
    $editCategory = $editCategoryStatement->fetch() ?: null;
}

$resourceForm = $editResource ?: [
    'resourceID' => 0,
    'categoryID' => 0,
    'title' => '',
    'description' => '',
    'subject' => '',
    'resourceType' => 'document',
    'resourceUrl' => '',
];
$categoryForm = $editCategory ?: [
    'categoryID' => 0,
    'categoryName' => '',
    'categoryDescription' => '',
];
$resourceTypes = [
    'document' => 'Document',
    'video' => 'Video',
    'article' => 'Article',
    'notes' => 'Notes',
    'other' => 'Other',
];
?>
<!DOCTYPE html>
<html lang="en" data-sidebar="closed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning resources</title>
    <link rel="stylesheet" href="./assets/resources.css">
    <link rel="stylesheet" href="./assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="resource-page">
    <header class="resource-header">
        <a class="back-link" href="./dashboard.php">← Back to dashboard</a>
        <p class="eyebrow">COMFSCI RESOURCE REPOSITORY</p>
        <h1>Learning resources</h1>
        <p class="intro">Find helpful materials by subject, category, or keyword.</p>
    </header>

    <?php if ($message !== ''): ?>
        <p class="notice<?= $isError ? ' notice-error' : '' ?>" role="status"><?= resourceEscape($message) ?></p>
    <?php endif; ?>

    <section class="filter-panel" aria-label="Search resources">
        <form class="filter-form" method="get">
            <label class="keyword-field">Keyword
                <input type="search" name="q" value="<?= resourceEscape($keyword) ?>" placeholder="Search title, subject, or description">
            </label>
            <label>Category
                <select name="category">
                    <option value="0">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['categoryID'] ?>"<?= $selectedCategory === (int) $category['categoryID'] ? ' selected' : '' ?>>
                            <?= resourceEscape($category['categoryName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Subject
                <select name="subject">
                    <option value="">All subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= resourceEscape((string) $subject) ?>"<?= $selectedSubject === $subject ? ' selected' : '' ?>>
                            <?= resourceEscape((string) $subject) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="filter-actions">
                <button class="button button-primary" type="submit">Search</button>
                <a class="button button-quiet" href="./resources.php">Clear</a>
            </div>
        </form>
    </section>

    <?php if ($isAdmin): ?>
        <section class="admin-tools" aria-label="Resource administration">
            <div class="admin-heading">
                <div>
                    <p class="eyebrow">ADMINISTRATION</p>
                    <h2><?= $resourceForm['resourceID'] ? 'Update resource' : 'Add a resource' ?></h2>
                </div>
                <?php if ($editResource): ?>
                    <a class="button button-quiet" href="./resources.php">Cancel edit</a>
                <?php endif; ?>
            </div>
            <?php if (!$categories): ?>
                <p class="hint">Create a category before adding resources.</p>
            <?php else: ?>
                <form class="resource-form" method="post">
                    <input type="hidden" name="csrf" value="<?= resourceEscape((string) $_SESSION['resourceCsrf']) ?>">
                    <input type="hidden" name="action" value="saveResource">
                    <input type="hidden" name="resourceID" value="<?= (int) $resourceForm['resourceID'] ?>">
                    <label>Title
                        <input type="text" name="title" maxlength="150" required value="<?= resourceEscape($resourceForm['title']) ?>">
                    </label>
                    <label>Category
                        <select name="categoryID" required>
                            <option value="">Choose category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['categoryID'] ?>"<?= (int) $resourceForm['categoryID'] === (int) $category['categoryID'] ? ' selected' : '' ?>>
                                    <?= resourceEscape($category['categoryName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Subject
                        <input type="text" name="subject" maxlength="100" required value="<?= resourceEscape($resourceForm['subject']) ?>" placeholder="e.g. Data Structures">
                    </label>
                    <label>Resource type
                        <select name="resourceType" required>
                            <?php foreach ($resourceTypes as $typeValue => $typeLabel): ?>
                                <option value="<?= resourceEscape($typeValue) ?>"<?= $resourceForm['resourceType'] === $typeValue ? ' selected' : '' ?>>
                                    <?= resourceEscape($typeLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="wide-field">Resource URL
                        <input type="url" name="resourceUrl" maxlength="2048" required value="<?= resourceEscape($resourceForm['resourceUrl']) ?>" placeholder="https://…">
                        <span class="field-help">Use a trusted HTTP or HTTPS link. File uploads are not enabled.</span>
                    </label>
                    <label class="wide-field">Description
                        <textarea name="description" rows="3" maxlength="2000"><?= resourceEscape($resourceForm['description']) ?></textarea>
                    </label>
                    <div class="wide-field">
                        <button class="button button-primary" type="submit"><?= $resourceForm['resourceID'] ? 'Save changes' : 'Add resource' ?></button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="category-admin">
                <h3><?= $categoryForm['categoryID'] ? 'Update category' : 'Manage categories' ?></h3>
                <form class="category-form" method="post">
                    <input type="hidden" name="csrf" value="<?= resourceEscape((string) $_SESSION['resourceCsrf']) ?>">
                    <input type="hidden" name="action" value="saveCategory">
                    <input type="hidden" name="categoryID" value="<?= (int) $categoryForm['categoryID'] ?>">
                    <label>Category name
                        <input type="text" name="categoryName" maxlength="80" required value="<?= resourceEscape($categoryForm['categoryName']) ?>">
                    </label>
                    <label>Short description
                        <input type="text" name="categoryDescription" maxlength="255" value="<?= resourceEscape($categoryForm['categoryDescription']) ?>">
                    </label>
                    <button class="button button-primary" type="submit"><?= $categoryForm['categoryID'] ? 'Save category' : 'Add category' ?></button>
                    <?php if ($editCategory): ?><a class="button button-quiet" href="./resources.php">Cancel</a><?php endif; ?>
                </form>
                <?php if ($categories): ?>
                    <ul class="category-list">
                        <?php foreach ($categories as $category): ?>
                            <li>
                                <span><strong><?= resourceEscape($category['categoryName']) ?></strong>
                                    <?php if ($category['categoryDescription'] !== ''): ?>
                                        <small><?= resourceEscape($category['categoryDescription']) ?></small>
                                    <?php endif; ?>
                                </span>
                                <span class="category-actions">
                                    <a href="./resources.php?editCategory=<?= (int) $category['categoryID'] ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Delete this category and all of its resources?');">
                                        <input type="hidden" name="csrf" value="<?= resourceEscape((string) $_SESSION['resourceCsrf']) ?>">
                                        <input type="hidden" name="action" value="deleteCategory">
                                        <input type="hidden" name="categoryID" value="<?= (int) $category['categoryID'] ?>">
                                        <button class="text-button text-danger" type="submit">Delete</button>
                                    </form>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="results" aria-labelledby="results-heading">
        <div class="results-heading">
            <div>
                <p class="eyebrow">BROWSE LIBRARY</p>
                <h2 id="results-heading">Resources</h2>
            </div>
            <span class="result-count"><?= count($resources) ?> found</span>
        </div>
        <?php if (!$resources): ?>
            <div class="empty-state">
                <h3>No resources found</h3>
                <p>Try a different search or filter. New materials will appear here when available.</p>
            </div>
        <?php else: ?>
            <div class="resource-grid">
                <?php foreach ($resources as $resource): ?>
                    <article class="resource-card">
                        <div class="card-meta">
                            <span class="category-tag"><?= resourceEscape($resource['categoryName']) ?></span>
                            <span class="type-tag"><?= resourceEscape($resourceTypes[$resource['resourceType']] ?? 'Other') ?></span>
                        </div>
                        <h3><?= resourceEscape($resource['title']) ?></h3>
                        <p class="subject-label"><?= resourceEscape($resource['subject']) ?></p>
                        <?php if ($resource['description'] !== ''): ?>
                            <p class="resource-description"><?= nl2br(resourceEscape($resource['description'])) ?></p>
                        <?php endif; ?>
                        <div class="card-footer">
                            <?php if (resourceValidateExternalUrl((string) $resource['resourceUrl'])): ?>
                                <a class="button button-primary" href="<?= resourceEscape($resource['resourceUrl']) ?>" target="_blank" rel="noopener noreferrer">
                                    Open resource <span aria-hidden="true">↗</span>
                                </a>
                            <?php else: ?>
                                <span class="button button-quiet" aria-label="Resource link unavailable">Link unavailable</span>
                            <?php endif; ?>
                            <?php if ($isAdmin): ?>
                                <span class="manage-links">
                                    <a href="./resources.php?edit=<?= (int) $resource['resourceID'] ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Delete this resource?');">
                                        <input type="hidden" name="csrf" value="<?= resourceEscape((string) $_SESSION['resourceCsrf']) ?>">
                                        <input type="hidden" name="action" value="deleteResource">
                                        <input type="hidden" name="resourceID" value="<?= (int) $resource['resourceID'] ?>">
                                        <button class="text-button text-danger" type="submit">Delete</button>
                                    </form>
                                </span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/ai_chat_widget.php'; ?>
</body>
</html>
