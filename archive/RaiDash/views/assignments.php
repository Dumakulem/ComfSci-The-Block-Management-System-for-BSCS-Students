<section class="card card-fill">
  <div class="card-head"><h3><?= icon('file', 16) ?>Assignments</h3><a href="<?= e(link_to(['edit' => 'assignments'])) ?>" class="link-orange"><?= icon('edit', 13) ?>Edit</a></div>
  <div class="scroll assign-list" tabindex="0" aria-label="Assignments">
    <?php if (!$assignments): ?><p class="empty">No assignments yet.</p><?php endif; ?>
    <?php foreach ($assignments as $a): ?>
      <article class="assignment">
        <div class="chips">
          <span class="chip">Deadline</span>
          <span class="chip chip-grey"><?= $a['due'] ? e(date('M j, g:i A', strtotime($a['due']))) : 'No due date' ?></span>
          <span class="chip <?= ['High priority' => 'chip-hot', 'Medium priority' => 'chip-med'][$a['priority']] ?? '' ?>"><?= e($a['priority']) ?></span>
        </div>
        <h4><?= e($a['title']) ?></h4>
      </article>
    <?php endforeach; ?>
  </div>
</section>
