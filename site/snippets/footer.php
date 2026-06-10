  </main>
<?php
/**
 * Closing body — loads jQuery 4, Fancybox 6 (+ German l10n on the German
 * site only; app.js falls back to Fancybox's English defaults without it)
 * and the app bootstrap, in dependency order. All vendored locally under
 * assets/vendor.
 */
?>
  <?= js('assets/vendor/jquery/jquery.min.js') ?>
  <?= js('assets/vendor/fancybox/fancybox.umd.js') ?>
  <?php if ($kirby->language()?->code() === 'de'): ?>
  <?= js('assets/vendor/fancybox/de_DE.umd.js') ?>
  <?php endif ?>
  <?= js('assets/js/app.js') ?>
  <?php foreach ($scripts ?? [] as $script): ?>
  <?= js($script) ?>
  <?php endforeach ?>
</body>
</html>
