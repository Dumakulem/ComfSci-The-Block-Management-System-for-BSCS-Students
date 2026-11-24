<?php
// Bell items = saved notifications + online classes happening today / tomorrow.
$soon  = array_values(array_filter($upcoming ?? [], fn($m) => when_chip($m['date'], $today) !== ''));
$count = count($notifications) + count($soon);
$panel = $panel ?? false;
$to    = $theme === 'dark' ? 'light' : 'dark';
?>
<header class="topbar">
  <div class="top-l">
    <form method="post" action="actions.php" class="sb-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="toggle_sidebar">
      <button class="icon-btn sb-toggle" type="submit" aria-controls="sidebar" aria-expanded="<?= ($sb ?? 'open') === 'open' ? 'true' : 'false' ?>"
              aria-label="Toggle sidebar" title="Toggle sidebar"><?= icon('menu', 18) ?></button>
    </form>
    <span class="tab"><?= icon('layout', 15) ?>Dashboard</span>
  </div>
  <div class="actions">
    <form method="post" action="actions.php">
      <?= csrf_field() ?><input type="hidden" name="action" value="toggle_theme">
      <button class="icon-btn" aria-label="Switch to <?= $to ?> mode" title="Switch to <?= $to ?> mode"><?= icon($theme === 'dark' ? 'sun' : 'moon', 18) ?></button>
    </form>

    <div class="bell-wrap">
      <a href="<?= e($panel ? link_to() : link_to(['panel' => 'notif'])) ?>" class="icon-btn<?= $panel ? ' on' : '' ?>"
         aria-label="Notifications<?= $count ? ' (' . $count . ' new)' : '' ?>" aria-haspopup="true" aria-expanded="<?= $panel ? 'true' : 'false' ?>">
        <?= icon('bell', 18) ?>
        <?php if ($count): ?><span class="badge"><?= $count > 9 ? '9+' : $count ?></span><?php endif; ?>
      </a>

      <?php if ($panel): ?>
        <a class="notif-backdrop" href="<?= e(link_to()) ?>" aria-label="Close notifications" tabindex="-1"></a>
        <section class="notif-pop" role="dialog" aria-label="Notifications">
          <div class="card-head">
            <h3><?= icon('bell', 16) ?>Notifications</h3>
            <form method="post" action="actions.php">
              <?= csrf_field() ?><input type="hidden" name="action" value="clear_notifications">
              <button class="linkbtn link-orange"<?= $notifications ? '' : ' disabled' ?>>Clear</button>
            </form>
          </div>
          <div class="scroll" tabindex="0" aria-label="Notification list">
            <?php if (!$count): ?><p class="empty">You're all caught up.</p><?php endif; ?>

            <?php foreach ($soon as $m): ?>
              <article class="note note-event">
                <h4><span class="dot"></span>Online class <?= e(strtolower(when_chip($m['date'], $today))) ?></h4>
                <p class="muted"><?= e($m['subject']) ?> · Prof. <?= e($m['professor']) ?></p>
                <p class="meta"><span><?= icon('calendar', 13) ?><?= e(date('D, M j', strtotime($m['date']))) ?></span><span><?= icon('clock', 13) ?><?= e(date('g:i A', strtotime($m['time']))) ?></span></p>
              </article>
            <?php endforeach; ?>

            <?php foreach ($notifications as $n): ?>
              <?php if ($n['kind'] === 'event'): ?>
                <article class="note note-event">
                  <h4><span class="dot"></span><?= e($n['title']) ?></h4>
                  <p class="muted"><?= e($n['subtitle']) ?></p>
                  <p class="meta"><span><?= icon('calendar', 13) ?><?= e($n['date']) ?></span><span><?= icon('clock', 13) ?><?= e($n['time']) ?></span></p>
                </article>
              <?php else: ?>
                <article class="note note-msg">
                  <h4><?= e($n['title']) ?><time><?= e($n['time']) ?></time></h4>
                  <p class="muted"><?= e($n['subtitle']) ?></p>
                </article>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </div>
</header>
