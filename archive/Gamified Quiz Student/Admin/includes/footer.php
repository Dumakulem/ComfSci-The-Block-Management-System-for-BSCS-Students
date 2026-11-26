  </main>
</div>
<div class="toast" id="toast" role="status"></div>
<script src="js/app.js"></script>
<?= $PAGE_SCRIPTS ?? '' ?>
<?php if (($flashMsg = take_flash()) !== null): ?>
<script>toast(<?= json_encode($flashMsg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);</script>
<?php endif; ?>
</body>
</html>