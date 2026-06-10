<?php
/**
 * One Monatsblatt program entry (Showing or Event). Receives the precomputed
 * entry-data array from templates/program.php ($entryData). The matching
 * slide-down panel is rendered separately (monatsblatt-detail) as a
 * full-width row of the day grid; this entry references it via data-detail.
 *
 * @var string $detailId
 * @var string $venueKey  'saal'|'box'
 * @var string $timeH     hour, no leading zero
 * @var string $timeM     minutes, two digits
 * @var string $series
 * @var string $title
 * @var string $credits   print-style credits, plain text ("P. Cortellesi, IT 2024; 118′")
 * @var array  $marks     subtitle markers: [icon(bool), note(string), label(string)]
 * @var bool   $omu
 * @var bool   $talk
 * @var string $note
 */
?>
<?php /* The whole card is a click target (JS delegation), but the accessible
         control is the real <button> in the heading — children of a
         role=button would lose their semantics (h3s vanish from AT). */ ?>
<article class="event"
         data-venue="<?= $venueKey ?>"
         <?php if ($series !== ''): ?>data-series="<?= html($series) ?>"<?php endif ?>
         <?= $omu ? 'data-omu' : '' ?>
         <?= $talk ? 'data-talk' : '' ?>
         data-detail="#<?= $detailId ?>">
  <span class="time"><?= $timeH ?><sup><?= $timeM ?></sup></span>
  <span class="head">
    <?php if ($series !== ''): ?><span class="series"><?= html($series) ?></span><?php endif ?>
    <span class="vtag <?= $venueKey ?>"><?= $venueKey === 'box' ? 'Box' : 'Saal' ?></span>
  </span>
  <div class="what">
    <h3 class="film"><button type="button" class="t-btn"
        aria-controls="<?= $detailId ?>" aria-expanded="false"><span class="t-text"><?= html($title) ?></span></button>
      <span class="credits"><?= html($credits) ?>
        <?php foreach ($marks as $mark): ?>
          <?php if ($mark['icon']): ?>
            <svg class="icon" role="img" aria-label="<?= html($mark['label']) ?>"><use href="#i-ut"/></svg><?php if ($mark['note'] !== ''): ?><span class="ut-note"><?= html($mark['note']) ?></span><?php endif ?>
          <?php else: ?>
            <span class="ut-note" title="<?= html($mark['label']) ?>"><?= html($mark['note']) ?></span>
          <?php endif ?>
        <?php endforeach ?>
        <?php if ($talk): ?>
          <svg class="icon" role="img" aria-label="<?= html(t('kinemathek.mb.legend.talk')) ?>"><use href="#i-talk"/></svg>
        <?php endif ?>
      </span>
    </h3>
    <?php if ($note !== ''): ?><p class="note"><?= html($note) ?></p><?php endif ?>
  </div>
</article>
