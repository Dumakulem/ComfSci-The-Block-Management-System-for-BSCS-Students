<aside class="sidebar" id="sidebar" aria-label="Sidebar">
  <div class="avatar" aria-hidden="true">
    <svg viewBox="0 0 96 96"><circle cx="48" cy="34" r="18" fill="#1c1c1c"/><path d="M14 92c0-20 15-32 34-32s34 12 34 32z" fill="#1c1c1c"/></svg>
  </div>
  <h2 class="student-name"><?= e($student['name']) ?></h2>
  <p class="student-no">Student Number:<br><?= e($student['number']) ?></p>

  <nav class="nav-panel" aria-label="Main">
    <ul>
      <?php foreach ($nav as [$label, $ic, $href, $active]): ?>
        <li><a href="<?= e($href) ?>" class="<?= $active ? 'active' : '' ?>" title="<?= e($label) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span class="nav-label"><?= e($label) ?></span></a></li>
      <?php endforeach; ?>
    </ul>
    <a href="logout.php" class="logout" title="Logout"><?= icon('logout') ?><span class="nav-label">Logout</span></a>
  </nav>
</aside>
<a class="sb-backdrop" href="#" data-sb-close aria-hidden="true" tabindex="-1"></a>
