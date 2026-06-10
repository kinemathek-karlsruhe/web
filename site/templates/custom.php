<?php
/**
 * Freie Seite — editor-authored HTML/CSS/JS, either inside the Monatsblatt
 * shell or as a blank canvas. Content is first-party by policy (the
 * blueprint warns against third-party embeds, SPEC §7); output is the
 * editor's verbatim code.
 *
 * @var \Kirby\Cms\Page $page
 */
$shell = $page->shell()->or('true')->toBool();
?>
<?php if ($shell): ?>
  <?php snippet('header', ['languageNav' => false]) ?>
  <div class="sheet">
    <?php snippet('monatsblatt-masthead', ['active' => $page->slug()]) ?>
    <div id="pivot-content">
      <div class="custom-page"><?= $page->html()->value() ?></div>
      <?php snippet('monatsblatt-colophon') ?>
    </div>
  </div>
<?php else: ?>
  <?php snippet('header', ['languageNav' => true]) ?>
  <div class="custom-page custom-bare"><?= $page->html()->value() ?></div>
<?php endif ?>
<?php if ($page->css()->isNotEmpty()): ?>
  <style><?= $page->css()->value() ?></style>
<?php endif ?>
<?php if ($page->js()->isNotEmpty()): ?>
  <script><?= $page->js()->value() ?></script>
<?php endif ?>
<?php snippet('footer', ['scripts' => $shell ? ['assets/js/monatsblatt.js', 'assets/js/program.js'] : []]) ?>
