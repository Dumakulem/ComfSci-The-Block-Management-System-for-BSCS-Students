<section class="card card-fill meeting">
  <div class="card-head">
    <h3><?= icon('video', 16) ?>Online meetings</h3>
    <div class="head-r">
      <a href="<?= e(link_to(['edit' => 'addschedule'])) ?>" class="link-orange icon-link" aria-label="Add class schedule" title="Add class schedule"><?= icon('plus', 16) ?></a>
      <a href="<?= e(link_to(['edit' => 'schedules'])) ?>" class="link-orange icon-link" aria-label="Edit class schedules" title="Edit class schedules"><?= icon('edit', 16) ?></a>
    </div>
  </div>
  <?php if (!$upcoming): ?>
    <p class="empty">No online classes in the next 2 weeks.</p>
    <a href="<?= e(link_to(['edit' => 'addschedule'])) ?>" class="add-btn"><?= icon('plus', 15) ?>Add class schedule</a>
  <?php else: ?>
    <div class="scroll meet-list" tabindex="0" aria-label="Upcoming online classes">
      <?php foreach ($upcoming as $m) { include __DIR__ . '/meet-item.php'; } ?>
    </div>
  <?php endif; ?>
</section>
