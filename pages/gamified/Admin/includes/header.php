<?php
require_once __DIR__ . '/icons.php';
?>
<!DOCTYPE html>
<html lang="en" data-sidebar="closed">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($PAGE_TITLE) ?> – Admin</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="../../assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<?php require __DIR__ . '/../../../includes/sidebar.php'; ?>
<div class="app comfsci-app">
  <main>
    <header class="top">
      <div class="crumb"><?= ic($PAGE_ICON) . h($PAGE_TITLE) ?></div>
      <div>
        <button class="ib" data-a="soon" data-n="Notifications" aria-label="Notifications"><?= ic('bell') ?></button><button class="ib" data-a="soon" data-n="Settings" aria-label="Settings"><?= ic('gear') ?></button>
      </div>
    </header>