<?php
$chip    = when_chip($m['date'], $today);
$hasLink = preg_match('#^https?://#i', (string)($m['link'] ?? '')) === 1;   // only real web links become buttons
?>
<article class="meet">
  <div class="meet-info">
    <h4></span>You have Online Class!<?php if ($chip): ?> <span class="chip chip-hot"><?= $chip ?></span><?php endif; ?></h4>
    <p class="meta"><span><?= icon('calendar', 13) ?><?= e(date('D, M j', strtotime($m['date']))) ?></span><span><?= icon('clock', 13) ?><?= e(time_range($m['time'], $m['end'] ?? '')) ?></span></p>
    <p class="meet-sub"><?= e($m['subject']) ?></p>
    <p class="muted">Prof. <?= e($m['professor']) ?></p>
  </div>
  <?php if ($hasLink): ?>
    <a class="join" href="<?= e($m['link']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('video', 14) ?>Join</a>
  <?php else: ?>
    <span class="join join-off" title="No meeting link yet">No link</span>
  <?php endif; ?>
</article>
