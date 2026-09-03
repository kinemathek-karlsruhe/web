<?php
/**
 * Unterseiten als Karteikarten — Bildband oben (falls die Seite ein
 * Kartenbild hat, siehe die Seiten-Methode `cardImage()`), darunter Titel
 * und Kurzvorschau. Seiten ohne Bild bleiben reine Textkarten und stehen im
 * selben Raster: der Bildbestand ist lückenhaft (viele Unterseiten haben gar
 * kein Foto), eine Cover-Karte mit grauer Ersatzfläche würde daneben
 * kaputt aussehen.
 *
 * @var \Kirby\Cms\Pages  $pages   die anzuzeigenden Unterseiten
 * @var string            $label   aria-label der Liste
 */
$pages ??= null;
if ($pages === null || $pages->count() === 0) {
    return;
}
?>
<nav class="subpage-list" aria-label="<?= html($label ?? '') ?>">
  <?php foreach ($pages as $child): ?>
    <?php
    $bild = $child->cardImage();
    // SVG-Logos (und alles, was keine echte Bildfläche hat) nicht beschneiden:
    // crop() kann sie nicht rastern, und ein zugeschnittenes Logo ist Müll.
    $contain = $bild && $bild->extension() === 'svg';
    ?>
    <a class="subpage-link<?= $bild ? ' has-image' : '' ?>" href="<?= $child->url() ?>">
      <?php if ($bild): ?>
        <span class="sp-media<?= $contain ? ' is-contain' : '' ?>">
          <img
            src="<?= $contain ? $bild->url() : $bild->crop(760, 428)->url() ?>"
            alt=""
            loading="lazy"
          >
        </span>
      <?php endif ?>
      <span class="sp-body">
        <span class="sp-title"><?= html($child->title()) ?></span>
        <?php if ($child->intro()->isNotEmpty()): ?>
          <span class="sp-intro"><?= $child->intro()->excerpt(120) ?></span>
        <?php endif ?>
      </span>
    </a>
  <?php endforeach ?>
</nav>
