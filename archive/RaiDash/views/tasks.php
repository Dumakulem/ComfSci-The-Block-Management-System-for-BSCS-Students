<section class="card card-fill">
  <div class="card-head"><h3><?= icon('checklist', 18) ?>To-do list</h3><a href="<?= e(link_to(['edit' => 'tasks'])) ?>" class="link-orange"><?= icon('edit', 13) ?>Edit</a></div>
  <?php
  // Unfinished first, finished last. Keys (database ids) are kept so toggle/edit still hit the right task.
  $ordered = array_filter($tasks, fn($t) => !$t['done']) + array_filter($tasks, fn($t) => $t['done']);
if (!$tasks): ?><p class="empty">Nothing to do yet. Add your first to-do.</p><?php else: ?>
  <ul class="tasks">
    <?php foreach ($ordered as $i => $t): [$due, $late] = task_due($t); ?>
      <li>
        <form method="post" action="actions.php">
          <?= csrf_field() ?><input type="hidden" name="action" value="toggle_task"><input type="hidden" name="id" value="<?= $i ?>">
          <button class="task<?= $t['done'] ? ' done' : '' ?>" aria-pressed="<?= $t['done'] ? 'true' : 'false' ?>">
            <span class="task-text">
              <strong><?= e($t['title']) ?></strong>
              <small<?= $late ? ' class="late"' : '' ?>><?= icon('calendar', 12) ?><span><?= $late ? 'Overdue · ' : 'Due ' ?><?= e($due) ?></span></small>
            </span>
            <span class="box"><?= icon('check', 14) ?></span>
          </button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <a href="<?= e(link_to(['edit' => 'addtask'])) ?>" class="add-btn"><?= icon('plus', 15) ?>Add to-do</a>
</section>
