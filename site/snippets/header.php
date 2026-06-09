<?php
/**
 * Base document head + opening body.
 * Primitive for now — no design work yet. Loads the built Tailwind stylesheet
 * and the Fancybox stylesheet. Everything is first-party (privacy requirement:
 * no cookies, no third-party requests).
 */
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $page->isHomePage() ? $site->title()->esc() : $page->title()->esc() . ' – ' . $site->title()->esc() ?></title>
  <?= css('assets/css/styles.css') ?>
  <?= css('assets/vendor/fancybox/fancybox.css') ?>
</head>
<body>
  <main>
