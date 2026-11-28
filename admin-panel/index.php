<?php
declare(strict_types=1);
$navItems = require __DIR__ . '/includes/nav.php';
$svg = static fn (string $inner): string =>
    '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
  <div class="app-shell" id="app">

    <aside class="sidebar" id="sidebar">
      <div class="identity">
        <span class="avatar"><?= $svg('<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>') ?></span>
        <p class="admin-name">Admin Name</p>
        <p class="admin-role">Administrator</p>
      </div>
      <nav class="navigation" aria-label="Primary navigation">
        <div class="nav-list">
          <?php foreach ($navItems as $item): ?>
            <button class="nav-item<?= $item['label'] === 'Admin Panel' ? ' nav-item-active' : '' ?>" type="button"
                    data-nav="<?= htmlspecialchars($item['label']) ?>"<?= $item['label'] === 'Admin Panel' ? ' aria-current="page"' : '' ?>>
              <?= $svg($item['icon']) ?><span><?= htmlspecialchars($item['label']) ?></span>
            </button>
          <?php endforeach; ?>
        </div>
        <button class="nav-item logout-button" type="button" id="logoutBtn">
          <?= $svg('<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>') ?><span>Logout</span>
        </button>
      </nav>
    </aside>

    <div class="workspace">
      <header class="mobile-bar">
        <button class="mobile-menu" type="button" id="menuBtn" aria-label="Toggle navigation" aria-expanded="false">
          <span></span><span></span><span></span>
        </button>
        <strong id="mobileTitle">Admin Panel</strong>
      </header>
      <main class="content" id="content"></main>
    </div>
    <button class="scrim" type="button" id="scrim" aria-label="Close navigation" hidden></button>
    <div id="modalRoot"></div>
  </div>

  <script src="assets/js/script.js?v=<?= @filemtime(__DIR__ . '/assets/js/script.js') ?>"></script>
</body>
</html>
