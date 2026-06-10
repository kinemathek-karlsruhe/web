<?php

use Kinemathek\Kinemathek;

/**
 * Film archive — Monatsblatt design: poster grid, upcoming-first, with the
 * server-side facets (deep-linkable query params) restyled as quiet chips
 * behind a "Filtern nach" disclosure.
 *
 * @var \Kirby\Cms\Pages $films
 * @var array $availableFacets
 * @var array $activeFacets
 * @var int   $totalCount
 */

// merge/override the current query string (null removes a param)
$qs = function (array $overrides) use ($page): string {
    $params = array_merge(get(), $overrides);
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    return $page->url() . ($params === [] ? '' : '?' . http_build_query($params));
};
?>
<?php snippet('header', ['languageNav' => false]) ?>
<div class="sheet">

  <?php snippet('monatsblatt-masthead', ['active' => 'films']) ?>

  <div id="pivot-content">

  <nav class="filters" aria-label="<?= html(t('kinemathek.filters.by', 'Filtern nach')) ?>">
    <span class="label"><?= html(t('kinemathek.mb.filter')) ?></span>
    <a class="chip<?= get('discussion') ? ' on' : '' ?>"
       href="<?= $qs(['discussion' => get('discussion') ? null : '1']) ?>">
      <svg class="icon" aria-hidden="true"><use href="#i-talk"/></svg>
      <?= html(t('kinemathek.filters.discussion', 'nur mit Filmgespräch')) ?>
    </a>
    <a class="chip<?= get('hasSubtitles') ? ' on' : '' ?>"
       href="<?= $qs(['hasSubtitles' => get('hasSubtitles') ? null : '1']) ?>">
      <svg class="icon" aria-hidden="true"><use href="#i-ut"/></svg>
      <?= html(t('kinemathek.filters.subtitled', 'nur mit Untertiteln')) ?>
    </a>
    <?php if ($activeFacets !== []): ?>
      <a class="reset" href="<?= $page->url() ?>"><?= html(t('kinemathek.filters.reset', 'Filter zurücksetzen')) ?></a>
    <?php endif ?>
    <span class="count"><?= $totalCount ?> <?= html($totalCount === 1 ? t('kinemathek.mb.film') : t('kinemathek.films')) ?></span>
  </nav>

  <details class="facets"<?= array_diff_key($activeFacets, ['discussion' => 1, 'hasSubtitles' => 1]) !== [] ? ' open' : '' ?>>
    <summary><?= html(t('kinemathek.filters.by', 'Filtern nach')) ?></summary>
    <?php foreach ($availableFacets as $facet => $values): ?>
      <?php if (empty($values)) continue ?>
      <div class="facet-group">
        <span class="fname"><?= html($facet) ?></span>
        <?php foreach ($values as $value => $count): ?>
          <?php $on = strcasecmp((string)($activeFacets[$facet] ?? ''), (string)$value) === 0; ?>
          <a class="facet-link<?= $on ? ' on' : '' ?>"
             href="<?= $qs([$facet => $on ? null : $value]) ?>"><?= html($value) ?> <span class="fcount"><?= $count ?></span></a>
        <?php endforeach ?>
      </div>
    <?php endforeach ?>
  </details>

  <?php if ($films->count() === 0): ?>
    <p class="program-empty" style="display:block"><?= html(t('kinemathek.program.noMatches', 'Keine passenden Termine.')) ?></p>
  <?php else: ?>
    <ul class="film-grid">
      <?php foreach ($films as $film): ?>
        <?php
        $meta = trim(implode('/', array_map('strtoupper', Kinemathek::splitField($film->country())))
            . ' ' . $film->year()->value());
        if ($film->runtime()->isNotEmpty()) {
            $meta .= ($meta !== '' ? '; ' : '') . $film->runtime()->value() . '′';
        }
        ?>
        <li class="film-card">
          <a href="<?= $film->url() ?>">
            <span class="poster">
              <?php if ($poster = $film->posterFile()): ?>
                <img src="<?= $poster->resize(480)->url() ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="poster-fallback"><?= html($film->title()) ?></span>
              <?php endif ?>
              <?php if ($film->hasUpcoming()): ?>
                <span class="upcoming-tag"><?= html(t('kinemathek.mb.soon')) ?></span>
              <?php endif ?>
            </span>
            <h2 class="fc-title"><?= html($film->title()) ?></h2>
            <?php if ($meta !== ''): ?><p class="fc-meta"><?= html($meta) ?></p><?php endif ?>
          </a>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>

  <?php $attr = $site->tmdbAttribution(); ?>
  <?php snippet('monatsblatt-colophon', [
      'extra' => html($attr['text']) . ' <a href="' . $attr['url'] . '" rel="noopener noreferrer">themoviedb.org</a>',
  ]) ?>

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/monatsblatt.js', 'assets/js/program.js']]) ?>
