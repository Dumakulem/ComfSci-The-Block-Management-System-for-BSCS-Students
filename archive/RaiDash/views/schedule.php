<?php
// Shared fields for adding / editing an online class schedule. Expects $r (array, may be empty).
$r += ['subject' => '', 'professor' => '', 'days' => [], 'start' => '00:00', 'end' => '00:00', 'link' => ''];
?>
<label class="field">Subject<input name="subject" maxlength="60" required value="<?= e($r['subject']) ?>" placeholder="e.g. Subject"></label>
<label class="field">Professor<input name="professor" maxlength="60" required value="<?= e($r['professor']) ?>" placeholder="e.g. Professor"></label>
<div class="field-days">
  <span class="lbl">Days</span>
  <div class="days">
    <?php foreach (DAY_NAMES as $i => $n): ?>
      <label class="daypick"><input type="checkbox" name="days[]" value="<?= $i ?>"<?= in_array($i, $r['days'], true) ? ' checked' : '' ?>><span><?= $n ?></span></label>
    <?php endforeach; ?>
  </div>
</div>
<div class="two">
  <label class="field">Start time<input type="time" name="start" required value="<?= e($r['start']) ?>"></label>
  <label class="field">End time<input type="time" name="end" required value="<?= e($r['end']) ?>"></label>
</div>
<label class="field">Meeting link<input type="url" name="link" maxlength="300" value="<?= e($r['link']) ?>" placeholder="https://meet.google.com/abc-defg-hij"></label>
<p class="hint">Paste the Google Meet, Zoom or Teams link your teacher posted.</p>
