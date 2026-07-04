<?php
/**
 * Base document head + opening body.
 * Primitive for now — no design work yet. Loads the built Tailwind stylesheet
 * and the Fancybox stylesheet. Everything is first-party (privacy requirement:
 * no cookies, no third-party requests).
 *
 * Multi-language: the lang attribute and hreflang alternates reflect the
 * current content language; the language switcher is a pair of plain
 * first-party links (no detection, no cookie — SPEC §7). $page->url($code)
 * resolves the SAME page in the other language.
 *
 * Social metadata (description + Open Graph) is derived generically: plain
 * meta tags, zero extra requests, so SPEC §7 stays intact.
 */
$language = $kirby->language();

// Showings/Events fall back to the slug via $page->title() — displayTitle()
// is the safe accessor where a model defines it (CLAUDE.md gotcha).
$metaTitle = method_exists($page, 'displayTitle')
    ? $page->displayTitle()
    : $page->title()->value();

// Description: the page's first prose field — a Showing borrows its Film's
// synopsis; container pages (program/films/events) fall back to the eyebrow.
$film = $page instanceof \Kinemathek\ShowingPage ? $page->film() : null;
$metaDescription = null;
foreach ([
    $page->content()->get('synopsis'),   // film
    $film?->content()->get('synopsis'),  // showing → its film
    $page->content()->get('intro'),      // text/collection
    $page->content()->get('text'),       // event (its description field), text pages
    $page->content()->get('sonderinfo'), // showing without film synopsis
] as $field) {
    if ($field !== null && $field->isNotEmpty()) {
        $metaDescription = $field->excerpt(160)->value();
        break;
    }
}
$metaDescription = $metaDescription ?: t('kinemathek.mb.eyebrow', 'Barrierearm, klimatisiert und stillfreundlich');

// Image: a film's first still (landscape previews better than the portrait
// poster), then the poster; Events/static pages use their own image field.
$imagePage = $film ?? $page;
$metaImage = $imagePage->content()->get('stills')->toFiles()->first()
    ?? $imagePage->content()->get('poster')->toFile()
    ?? $page->content()->get('image')->toFile()
    ?? $page->content()->get('mainimage')->toFile();
$metaImage = $metaImage?->resize(1200);

$ogLocale = fn ($lang) => explode('.', (string)($lang->locale(LC_ALL) ?? 'de_DE'))[0];
?>
<!doctype html>
<html lang="<?= $language ? $language->code() : 'de' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="#fbfbfa" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#1a1a18" media="(prefers-color-scheme: dark)">
  <title><?= $page->isHomePage() ? $site->title()->esc() : esc($metaTitle) . ' – ' . $site->title()->esc() ?></title>
  <meta name="description" content="<?= esc($metaDescription) ?>">
  <link rel="canonical" href="<?= $page->url() ?>">
  <?php foreach ($kirby->languages() as $lang): ?>
  <link rel="alternate" hreflang="<?= $lang->code() ?>" href="<?= $page->url($lang->code()) ?>">
  <?php endforeach ?>
  <link rel="alternate" hreflang="x-default" href="<?= $page->url($kirby->defaultLanguage()->code()) ?>">
  <meta property="og:site_name" content="<?= $site->title()->esc() ?>">
  <meta property="og:type" content="<?= $page->intendedTemplate()->name() === 'film' ? 'video.movie' : 'website' ?>">
  <meta property="og:title" content="<?= $page->isHomePage() ? $site->title()->esc() : esc($metaTitle) ?>">
  <meta property="og:description" content="<?= esc($metaDescription) ?>">
  <meta property="og:url" content="<?= $page->url() ?>">
  <?php if ($language): ?>
  <meta property="og:locale" content="<?= $ogLocale($language) ?>">
  <?php foreach ($kirby->languages()->not($language) as $lang): ?>
  <meta property="og:locale:alternate" content="<?= $ogLocale($lang) ?>">
  <?php endforeach ?>
  <?php endif ?>
  <?php if ($metaImage): ?>
  <meta property="og:image" content="<?= $metaImage->url() ?>">
  <meta property="og:image:width" content="<?= $metaImage->width() ?>">
  <meta property="og:image:height" content="<?= $metaImage->height() ?>">
  <meta property="og:image:alt" content="<?= $metaImage->alt()->or($metaTitle)->esc() ?>">
  <?php endif ?>
  <meta name="twitter:card" content="<?= $metaImage ? 'summary_large_image' : 'summary' ?>">
  <link rel="icon" href="<?= url('assets/svg/favicon.svg') ?>" type="image/svg+xml">
  <?= css('assets/css/styles.css') ?>
  <?= css('assets/vendor/fancybox/fancybox.css') ?>
</head>
<body>
  <?php if ($languageNav ?? true): // designed templates render their own switcher ?>
  <nav class="mx-auto flex max-w-4xl justify-end gap-3 p-2 text-sm" aria-label="Sprache / Language">
    <?php foreach ($kirby->languages() as $lang): ?>
      <?php if ($language && $lang->code() === $language->code()): ?>
        <span class="font-semibold" aria-current="true"><?= html($lang->name()) ?></span>
      <?php else: ?>
        <a class="underline" href="<?= $page->url($lang->code()) ?>"
           hreflang="<?= $lang->code() ?>" lang="<?= $lang->code() ?>"><?= html($lang->name()) ?></a>
      <?php endif ?>
    <?php endforeach ?>
  </nav>
  <?php endif ?>
  <main>
