<?php
// Newer/older links for lists that fetch PER+1 rows. Needs $pgPath, $pgParams (current filters), $pg (page number), $pgMore (bool).
if ($pg > 1 || $pgMore): ?>
<p class="muted noprint" style="margin-top:1rem">Page <?= $pg ?>
  <?php if ($pg > 1): ?> · <a href="<?= base_url($pgPath . '?' . http_build_query($pgParams + ['p' => $pg - 1])) ?>">← Previous</a><?php endif ?>
  <?php if ($pgMore): ?> · <a href="<?= base_url($pgPath . '?' . http_build_query($pgParams + ['p' => $pg + 1])) ?>">Next →</a><?php endif ?></p>
<?php endif ?>
