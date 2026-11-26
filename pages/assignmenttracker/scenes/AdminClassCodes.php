<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../../config/database.php';
$pdo = db();
$moduleBaseUrl = preg_replace('/\/scenes$/', '', rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/'));
$pagesBaseUrl = preg_replace('/\/assignmenttracker$/', '', $moduleBaseUrl);
$pagesBaseUrl = rtrim($pagesBaseUrl, '/');
if (empty($_SESSION['authenticated']) || strtolower((string) ($_SESSION['user_type'] ?? '')) !== 'admin') {
    header('Location: ' . $pagesBaseUrl . '/../auth/login.php');
    exit;
}
if (isset($_POST['selectBlock'])) {
    $_SESSION['at_blockID'] = (int) $_POST['blockID'];
    header('Location: AdminAssignments.php');
    exit;
}
$blocks = $pdo->query('SELECT blockID, classBlock FROM tblblock WHERE archivedAt IS NULL ORDER BY classBlock')->fetchAll(PDO::FETCH_ASSOC);
$name = htmlspecialchars((string) ($_SESSION['user_name'] ?? 'Administrator'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Assignment Tracker | Choose Block</title>
  <link rel="stylesheet" href="../css/assignments.css">
  <link rel="stylesheet" href="../css/classcode.css">
  <?php require __DIR__ . '/../includes/portal-head.php'; ?>
</head>
<body class="comfsci-shell">
<?php require __DIR__ . '/../includes/portal-shell.php'; ?>
<div class="shell">
  <aside class="sidebar">
    <div class="profile"><div class="profile-avatar" aria-hidden="true">A</div><p class="profile-name"><?= $name ?></p><p class="profile-role">Administrator</p></div>
    <div class="nav-panel"><nav class="nav"><a class="nav-link is-active" href="AdminClassCodes.php">Blocks</a></nav><a class="nav-link logout" href="../../../auth/logout.php">Log out</a></div>
  </aside>
  <main class="main">
    <header class="topbar"><div class="page-label"><span>Select a Block</span></div><div class="user-chip"><span class="user-name"><?= $name ?></span></div></header>
    <section class="banner"><div><h1>Assignment Tracker</h1><p>Choose a ComfSci block to manage its assignments.</p></div></section>
    <section class="content">
      <?php if (!$blocks): ?><p>No blocks are set up in ComfSci yet.</p><?php endif; ?>
      <?php foreach ($blocks as $block): ?>
        <form method="POST" class="class-card">
          <input type="hidden" name="blockID" value="<?= (int) $block['blockID'] ?>">
          <button type="submit" name="selectBlock" class="btn-enter"><?= htmlspecialchars($block['classBlock'], ENT_QUOTES, 'UTF-8') ?> <span>Manage assignments</span></button>
        </form>
      <?php endforeach; ?>
    </section>
    <a href="../../dashboard.php" class="back-dashboard">Back to ComfSci Dashboard</a>
  </main>
</div>
</body>
</html>
