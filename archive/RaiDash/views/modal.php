<?php
$monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$thisMonth  = date('Y-m');
?>
<?php if ($edit || $day !== '' || $picker): ?>
<div class="modal" role="dialog" aria-modal="true" aria-labelledby="mt">
  <a class="backdrop" href="<?= e(link_to()) ?>" aria-label="Close"></a>
  <div class="dialog<?= $picker ? ($zoom ? ' dialog-md' : ' dialog-sm') : '' ?>">
  <a class="x-close" href="<?= e(link_to()) ?>" aria-label="Close" title="Close"><?= icon('x', 18) ?></a>
  <?php if ($picker && $zoom): ?>
    <div class="cp-zoom">
      <h3 id="mt"><?= icon('calendar', 18) ?><?= e($monthLabel) ?></h3>
      <div class="cp-year">
        <a href="<?= e('?' . http_build_query(['m' => $prevMonth, 'cp' => (int)substr($prevMonth, 0, 4), 'z' => 1])) ?>" aria-label="Previous month"><?= icon('prev', 16) ?></a>
        <a class="cp-back" href="<?= e('?' . http_build_query(['m' => $ym, 'cp' => $year])) ?>">Pick a year and month</a>
        <a href="<?= e('?' . http_build_query(['m' => $nextMonth, 'cp' => (int)substr($nextMonth, 0, 4), 'z' => 1])) ?>" aria-label="Next month"><?= icon('next', 16) ?></a>
      </div>
      <div class="cp-days">
        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $i => $d): ?><div class="cp-dw<?= ($i === 0 || $i === 6) ? ' wk' : '' ?>"><?= $d ?></div><?php endforeach; ?>
        <?php foreach ($calendar as $week): foreach ($week as $i => $n):
            $iso = $n ? sprintf('%s-%02d', $ym, $n) : '';
            $cls = ($i === 0 || $i === 6 ? ' wk' : '') . ($iso === $today ? ' today' : '') . (isset($byDay[$iso]) ? ' has-event' : ''); ?>
          <?php if ($n): ?><a class="cp-d<?= $cls ?>" href="<?= e(link_to(['day' => $iso])) ?>"><?= $n ?></a><?php else: ?><span></span><?php endif; ?>
        <?php endforeach; endforeach; ?>
      </div>
      <p class="hint"><span class="ring"></span> Weekends are tinted. Select a date to see its classes and events.</p>
    </div>

  <?php elseif ($picker): ?>
    <h3 id="mt"><?= icon('calendar', 18) ?>Pick a year and month</h3>
    <div class="cp-year">
      <a href="<?= e('?' . http_build_query(['m' => $ym, 'cp' => max(1970, $cpYear - 1)])) ?>" aria-label="Previous year"><?= icon('prev', 16) ?></a>
      <strong><?= $cpYear ?></strong>
      <a href="<?= e('?' . http_build_query(['m' => $ym, 'cp' => min(2100, $cpYear + 1)])) ?>" aria-label="Next year"><?= icon('next', 16) ?></a>
    </div>
    <div class="cp-months">
      <?php foreach ($monthNames as $i => $name):
          $mm  = sprintf('%04d-%02d', $cpYear, $i + 1);
          $cls = ($mm === $ym ? ' sel' : '') . ($mm === $thisMonth ? ' now' : ''); ?>
        <a class="cp-m<?= $cls ?>" href="<?= e('?' . http_build_query(['m' => $mm, 'cp' => $cpYear, 'z' => 1])) ?>"<?= $mm === $ym ? ' aria-current="date"' : '' ?>><?= $name ?></a>
      <?php endforeach; ?>
    </div>

  <?php elseif ($edit === 'addtask'): ?>
    <h3 id="mt">Add a to-do</h3>
      <form method="post" action="actions.php" class="add-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_task">
        <label class="field">Task<input name="title" maxlength="60" required placeholder="e.g. Finish OS Activity 4"></label>
        <div class="two">
          <label class="field">Deadline date<input type="date" name="date" required></label>
          <label class="field">Deadline time<input type="time" name="time" required></label>
        </div>
        <button class="btn btn-solid full">Add to list</button>
      </form>

  <?php elseif ($edit === 'tasks'): ?>
    <h3 id="mt">To-do list</h3>
    <?php if ($pick >= 0 && isset($tasks[$pick])): $r = $tasks[$pick]; ?>
      <form method="post" action="actions.php">
        <?= csrf_field() ?><input type="hidden" name="action" value="update_task"><input type="hidden" name="id" value="<?= $pick ?>">
        <label class="field">Task<input name="title" maxlength="60" required value="<?= e($r['title']) ?>"></label>
        <div class="two">
          <label class="field">Deadline date<input type="date" name="date" required value="<?= e($r['date']) ?>"></label>
          <label class="field">Deadline time<input type="time" name="time" required value="<?= e($r['time']) ?>"></label>
        </div>
        <div class="btn-row"><a class="btn btn-outline" href="<?= e(link_to(['edit' => 'tasks'])) ?>">Back</a><button class="btn btn-solid">Save changes</button></div>
      </form>
    <?php elseif ($del >= 0 && isset($tasks[$del])): $r = $tasks[$del]; [$dueDel] = task_due($r); ?>
      <h3>Delete this to-do?</h3>
      <div class="pick">
        <span class="pick-text"><strong><?= e($r['title']) ?></strong><small>Due <?= e($dueDel) ?></small></span>
      </div>
      <p class="hint">This removes the to-do from your list. You can't undo it.</p>
      <form method="post" action="actions.php">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete_tasks"><input type="hidden" name="ids[]" value="<?= $del ?>">
        <div class="btn-row"><a class="btn btn-outline" href="<?= e(link_to(['edit' => 'tasks'])) ?>">Cancel</a><button class="btn btn-danger">Yes, delete</button></div>
      </form>
    <?php else: ?>
      <?php if (!$tasks): ?><p class="empty">Your list is empty.</p><?php endif; ?>
      <div class="pick-list">
        <?php foreach ($tasks as $i => $t): [$due] = task_due($t); ?>
          <div class="pick">
            <span class="pick-text"><strong><?= e($t['title']) ?></strong><small>Due <?= e($due) ?></small></span>
            <span class="pick-actions">
              <a class="pick-edit" href="<?= e(link_to(['edit' => 'tasks', 'pick' => $i])) ?>">Edit</a>
              <a class="pick-del" href="<?= e(link_to(['edit' => 'tasks', 'del' => $i])) ?>" aria-label="Delete <?= e($t['title']) ?>">Delete</a>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($edit === 'addschedule'): ?>
    <h3 id="mt"><?= icon('video', 18) ?>Add a class schedule</h3>
    <form method="post" action="actions.php">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_schedule">
      <?php $r = []; include __DIR__ . '/schedule.php'; ?>
      <button class="btn btn-solid full">Add to schedule</button>
    </form>

  <?php elseif ($edit === 'schedules'): ?>
    <h3 id="mt"><?= icon('video', 18) ?>Online class schedule</h3>
    <?php if ($pick >= 0 && isset($schedules[$pick])): $r = $schedules[$pick]; ?>
      <form method="post" action="actions.php">
        <?= csrf_field() ?><input type="hidden" name="action" value="update_schedule"><input type="hidden" name="id" value="<?= $pick ?>">
        <?php include __DIR__ . '/schedule.php'; ?>
        <div class="btn-row"><a class="btn btn-outline" href="<?= e(link_to(['edit' => 'schedules'])) ?>">Back</a><button class="btn btn-solid">Save changes</button></div>
      </form>
    <?php elseif ($del >= 0 && isset($schedules[$del])): $r = $schedules[$del]; ?>
      <h3>Delete this schedule?</h3>
      <div class="pick">
        <span class="pick-text"><strong><?= e($r['subject']) ?></strong><small><?= e(days_label($r['days'])) ?> · <?= e(time_range($r['start'], $r['end'])) ?> · Prof. <?= e($r['professor']) ?></small></span>
      </div>
      <p class="hint">This removes the class from every week. You can't undo it.</p>
      <form method="post" action="actions.php">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete_schedules"><input type="hidden" name="ids[]" value="<?= $del ?>">
        <div class="btn-row"><a class="btn btn-outline" href="<?= e(link_to(['edit' => 'schedules'])) ?>">Cancel</a><button class="btn btn-danger">Yes, delete</button></div>
      </form>
    <?php else: ?>
      <h4 class="sub">Edit Schedules</h4>
      <?php if (!$schedules): ?><p class="empty">No class schedules yet.</p><?php endif; ?>
      <div class="pick-list">
        <?php foreach ($schedules as $i => $s): ?>
          <div class="pick">
            <span class="pick-text"><strong><?= e($s['subject']) ?></strong><small><?= e(days_label($s['days'])) ?> · <?= e(time_range($s['start'], $s['end'])) ?> · Prof. <?= e($s['professor']) ?></small></span>
            <span class="pick-actions">
              <a class="pick-edit" href="<?= e(link_to(['edit' => 'schedules', 'pick' => $i])) ?>">Edit</a>
              <a class="pick-del" href="<?= e(link_to(['edit' => 'schedules', 'del' => $i])) ?>" aria-label="Delete <?= e($s['subject']) ?>">Delete</a>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($edit === 'assignments'): ?>
    <?php if ($pick >= 0 && isset($assignments[$pick])): $r = $assignments[$pick]; ?>
      <h3 id="mt">Assignment priority</h3>
      <div class="pick">
        <span class="pick-text"><strong><?= e($r['title']) ?></strong><small><?= e($r['subject']) ?> · <?= $r['due'] ? e(date('M j, g:i A', strtotime($r['due']))) : 'No due date' ?></small></span>
      </div>
      <form method="post" action="actions.php">
        <?= csrf_field() ?><input type="hidden" name="action" value="update_assignment"><input type="hidden" name="id" value="<?= $pick ?>">
        <label class="field field-priority">Priority
          <select name="priority">
            <?php foreach (['High priority', 'Medium priority', 'Low priority'] as $p): ?><option value="<?= e($p) ?>"<?= $r['priority'] === $p ? ' selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
          </select>
        </label>
        <div class="btn-row"><a class="btn btn-outline" href="<?= e(link_to(['edit' => 'assignments'])) ?>">Back</a><button class="btn btn-solid">Save changes</button></div>
      </form>
    <?php else: ?>
      <h3 id="mt">Set an assignment's priority</h3>
      <?php if (!$assignments): ?><p class="empty">No assignments yet.</p><?php endif; ?>
      <div class="pick-list">
        <?php foreach ($assignments as $i => $a): ?>
          <a class="pick" href="<?= e(link_to(['edit' => 'assignments', 'pick' => $i])) ?>">
            <span class="pick-text"><strong><?= e($a['title']) ?></strong><small><?= $a['due'] ? e(date('M j, g:i A', strtotime($a['due']))) : 'No due date' ?> · <?= e($a['priority']) ?></small></span>
            <span class="pick-edit">Edit</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <h3 id="mt"><?= icon('calendar', 18) ?><?= e(date('D, M j', strtotime($day))) ?></h3>
    <?php if (!$dayMeetings && !$dayTasks && !$dayAssign): ?><p class="empty">Nothing scheduled on this date.</p><?php endif; ?>
    <?php if ($dayMeetings): ?>
      <h4 class="sub">Online classes</h4>
      <div class="meet-stack"><?php foreach ($dayMeetings as $m) { include __DIR__ . '/meet-item.php'; } ?></div>
    <?php endif; ?>
    <?php if ($dayAssign): ?>
      <h4 class="sub day-sub">Assignment deadlines</h4>
      <div class="meet-stack">
        <?php foreach ($dayAssign as $a): ?>
          <div class="pick"><span class="pick-text"><strong><?= e($a['title']) ?></strong><small><?= e(date('g:i A', strtotime($a['due']))) ?> · <?= e($a['priority']) ?></small></span></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($dayTasks): ?>
      <h4 class="sub day-sub">To-dos</h4>
      <div class="meet-stack">
        <?php foreach ($dayTasks as $t): ?>
          <div class="pick"><span class="pick-text"><strong><?= e($t['title']) ?></strong><small><?= e(fmt_time($t['time'])) ?><?= $t['done'] ? ' · Done' : '' ?></small></span></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="btn-row"><a class="btn btn-outline" href="<?= e(agenda_link($day)) ?>"><?= icon('calendar', 14) ?>Open in agenda</a><a class="btn btn-solid" href="<?= e(link_to(['edit' => 'schedules'])) ?>">Edit schedule</a></div>
  <?php endif; ?>
  </div>
</div>
<?php endif; ?>
