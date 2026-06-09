/* Kinemathek Karlsruhe — front-end bootstrap.
 *
 * Primitive for now (no design work yet). Loaded after jQuery and Fancybox in
 * the footer snippet. Privacy: everything here is first-party, no cookies, no
 * third-party calls.
 */
(function () {
  'use strict'

  // Fancybox v6 — bind the lightbox to any element marked up with
  // data-fancybox (e.g. poster/still thumbnails). German UI strings come from
  // the de_DE l10n bundle loaded just before this file.
  if (window.Fancybox) {
    window.Fancybox.bind('[data-fancybox]', {
      l10n: window.Fancybox.l10n && window.Fancybox.l10n.de_DE,
    })
  }

  // jQuery 4 is available as window.jQuery / window.$ for progressive
  // enhancement. Nothing to wire up yet.
  if (window.jQuery) {
    window.jQuery(function () {
      // DOM ready
    })
  }
})()
