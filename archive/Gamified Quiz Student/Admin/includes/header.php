<?php
require_once __DIR__ . '/icons.php';
$NAV = [['Dashboard','grid'],['Profile','user'],['Block Forum','cup'],['Attendance','chk'],['Gamified Quiz','bars'],['Assignments','doc'],['Announcement','bell'],['Academic Calendar','cal'],['Module Sources','cloud']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($PAGE_TITLE) ?> – Admin</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="app">
  <aside class="rail">
    <div class="profile">
      <svg viewBox="0 0 64 64"><rect x="8" y="4" width="48" height="56" rx="24" fill="#fff"/><circle cx="32" cy="26" r="9" fill="#1c1c1c"/><circle cx="32" cy="26" r="4" fill="#fff"/><path d="M20 50c3-8 21-8 24 0z" fill="#1c1c1c"/></svg>
      <b>Pogi</b><small>ADMINISTRATOR</small>
    </div>
    <nav class="nav">
      <?php foreach ($NAV as [$label, $icon]): ?>
        <?php if ($label === 'Gamified Quiz'): ?>
          <a href="quiz1.php?tab=create" class="on"><?= ic($icon) . h($label) ?></a>
        <?php else: ?>
          <button data-a="soon" data-n="<?= h($label) ?>"><?= ic($icon) . h($label) ?></button>
        <?php endif; ?>
      <?php endforeach; ?>
      <button class="out" data-a="soon" data-n="Logout"><?= ic('out') ?>Logout</button>
    </nav>
  </aside>
  <main>
    <header class="top">
      <div class="crumb"><?= ic($PAGE_ICON) . h($PAGE_TITLE) ?></div>
      <div>
        <button class="ib" data-a="soon" data-n="Notifications" aria-label="Notifications"><?= ic('bell') ?></button><button class="ib" data-a="soon" data-n="Settings" aria-label="Settings"><?= ic('gear') ?></button>
      </div>
    </header>