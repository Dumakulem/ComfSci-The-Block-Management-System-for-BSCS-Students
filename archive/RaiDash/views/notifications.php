<section class="card card-fill">
  <div class="card-head">
    <h3><?= icon('bell', 16) ?>Notifications</h3>
    <form method="post" action="actions.php">
      <?= csrf_field() ?><input type="hidden" name="action" value="clear_notifications">
      <button class="linkbtn link-orange"<?= $notifications ? '' : ' disabled' ?>>Clear</button>
    </form>
  </div>
  <div class="scroll" tabindex="0" aria-label="Notifications">
  <?php if (!$notifications): ?><p class="empty">You're all caught up.</p><?php endif; ?>
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
