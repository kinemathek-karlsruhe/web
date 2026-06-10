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
 */
$language = $kirby->language();
?>
<!doctype html>
<html lang="<?= $language ? $language->code() : 'de' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $page->isHomePage() ? $site->title()->esc() : $page->title()->esc() . ' – ' . $site->title()->esc() ?></title>
  <?php foreach ($kirby->languages() as $lang): ?>
  <link rel="alternate" hreflang="<?= $lang->code() ?>" href="<?= $page->url($lang->code()) ?>">
  <?php endforeach ?>
  <link rel="alternate" hreflang="x-default" href="<?= $page->url($kirby->defaultLanguage()->code()) ?>">
  <?= css('assets/css/styles.css') ?>
  <?= css('assets/vendor/fancybox/fancybox.css') ?>
</head>
<body>
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
  <main>
