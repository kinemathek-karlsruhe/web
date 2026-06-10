<?php
/**
 * Events — non-film programming, in the Monatsblatt design: masthead with
 * the Events pivot item active, then the shared day-grouped listing.
 *
 * @var \Kirby\Cms\Page $page
 * @var array  $days
 * @var array  $dayMeta
 * @var string $todayKey
 */
?>
<?php snippet('header', ['languageNav' => false]) ?>
<div class="sheet">

  <?php snippet('monatsblatt-masthead', ['active' => 'events']) ?>

  <div id="pivot-content">

  <?php snippet('monatsblatt-listing', [
      'days'     => $days,
      'dayMeta'  => $dayMeta,
      'todayKey' => $todayKey,
  ]) ?>

  <?php snippet('monatsblatt-colophon') ?>

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/monatsblatt.js', 'assets/js/program.js']]) ?>
