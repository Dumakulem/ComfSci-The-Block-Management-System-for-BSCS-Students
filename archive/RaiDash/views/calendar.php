<?php /* $byDay (date => titles of classes, to-dos and deadlines) is built in index.php */ ?>
<section class="card cal-card">
  <div class="card-head">
    <h3 class="cal-title">
      <?= icon('calendar', 20) ?>
      <a class="cal-pick-btn" href="<?= e('?' . http_build_query(['m' => $ym, 'cp' => $year])) ?>" aria-haspopup="dialog" title="Pick a year and month">
        <?= e($monthLabel) ?>
        <?= icon('chevron', 16, 'chev') ?>
      </a>
    </h3>
    <div class="head-r">
      <a href="<?= e(agenda_link()) ?>" class="link-orange" title="Agenda"><?= icon('calendar', 13) ?><span class="lbl-a">Agenda</span></a>
      <div class="cal-nav">
        <a href="?m=<?= e($prevMonth) ?>" aria-label="Previous month"><?= icon('prev', 16) ?></a>
        <a href="?m=<?= e($nextMonth) ?>" aria-label="Next month"><?= icon('next', 16) ?></a>
      </div>
    </div>
  </div>
  <div class="cal-grid">
    <?php foreach (['Su','Mo','Tu','We','Th','Fr','Sa'] as $i => $d): ?><div class="cw<?= ($i === 0 || $i === 6) ? ' wk' : '' ?>"><?= $d ?></div><?php endforeach; ?>
    <?php foreach ($calendar as $week): foreach ($week as $i => $n):
        $iso = $n ? sprintf('%s-%02d', $ym, $n) : '';
        $cls = trim(($iso === $today ? 'today ' : '') . (isset($byDay[$iso]) ? 'has-event' : '')); ?>
      <div class="cd<?= ($i === 0 || $i === 6) ? ' sun' : '' ?>">
        <?php if ($n): ?>
          <a href="<?= e(link_to(['day' => $iso])) ?>" class="<?= $cls ?>"<?= isset($byDay[$iso]) ? ' title="' . e(implode(', ', $byDay[$iso])) . '"' : '' ?>><?= $n ?></a>
        <?php endif; ?>
      </div>
    <?php endforeach; endforeach; ?>
  </div>
  <p class="legend"></span>Has a class or event. Select any date to see the details.</p>
</section>
