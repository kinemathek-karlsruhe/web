  </main>
<?php
/**
 * Closing body — loads jQuery 4, Fancybox 6 (+ German l10n) and the app
 * bootstrap, in dependency order. All vendored locally under assets/vendor.
 */
?>
  <?= js('assets/vendor/jquery/jquery.min.js') ?>
  <?= js('assets/vendor/fancybox/fancybox.umd.js') ?>
  <?= js('assets/vendor/fancybox/de_DE.umd.js') ?>
  <?= js('assets/js/app.js') ?>
</body>
</html>
