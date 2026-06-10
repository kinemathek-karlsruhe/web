<?php
/**
 * Monatsblatt colophon — the print sheet's footer line: address, phones,
 * funding credits. Shared by all designed templates. Optional extra content
 * (e.g. the TMDB attribution on film pages) via the 'extra' parameter.
 *
 * @var ?string $extra  pre-rendered HTML, appended as its own line
 */
?>
<footer class="colophon">
  <p>
    <strong>Kinemathek Karlsruhe</strong>, Kaiserpassage 6, 76133 Karlsruhe<span class="sep">|</span>
    Kasse: <a href="tel:+4972183189585">Tel. 0721&#8201;-&#8201;83189585</a>,
    Büro: <a href="tel:+4972183189580">0721&#8201;-&#8201;83189580</a>
  </p>
  <p class="support"><?= html(t('kinemathek.mb.support')) ?></p>
  <?php if (($extra ?? '') !== ''): ?>
    <p class="support"><?= $extra ?></p>
  <?php endif ?>
</footer>
