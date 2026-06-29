<?php
/**
 * Inline SVG symbol definitions for the Monatsblatt iconography, referenced
 * via <use href="#i-ut"> / <use href="#i-talk">. Include once per page,
 * directly after <body> content starts.
 *
 *  #i-ut    subtitle rectangle (Originalfassung mit Untertiteln)
 *  #i-talk  speech bubble (Einführung/Filmgespräch)
 *  #i-free  ticket with stub (Freier Eintritt) — filter chip only
 */
?>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-ut" viewBox="0 0 26 18">
    <rect x="1.2" y="1.2" width="23.6" height="15.6" rx="2.4"
          fill="none" stroke="currentColor" stroke-width="1.9"/>
    <line x1="4.6" y1="10.4" x2="21.4" y2="10.4" stroke="currentColor" stroke-width="1.7"/>
    <line x1="4.6" y1="13.6" x2="15.5" y2="13.6" stroke="currentColor" stroke-width="1.7"/>
  </symbol>
  <symbol id="i-talk" viewBox="0 0 26 22">
    <path d="M3.4 1.2h19.2a2.2 2.2 0 0 1 2.2 2.2v9.4a2.2 2.2 0 0 1-2.2 2.2H11.8l-5.6 5.4v-5.4H3.4a2.2 2.2 0 0 1-2.2-2.2V3.4a2.2 2.2 0 0 1 2.2-2.2z"
          fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
    <line x1="5.4" y1="6.2" x2="20.6" y2="6.2" stroke="currentColor" stroke-width="1.7"/>
    <line x1="5.4" y1="9.8" x2="16.2" y2="9.8" stroke="currentColor" stroke-width="1.7"/>
  </symbol>
  <symbol id="i-free" viewBox="0 0 26 18">
    <rect x="1.2" y="2.6" width="23.6" height="12.8" rx="2.4"
          fill="none" stroke="currentColor" stroke-width="1.9"/>
    <line x1="17.4" y1="3.2" x2="17.4" y2="14.8"
          stroke="currentColor" stroke-width="1.6" stroke-dasharray="2 2.2"/>
  </symbol>
</svg>
