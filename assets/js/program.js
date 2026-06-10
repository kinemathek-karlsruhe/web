/* Kinemathek Karlsruhe — Monatsblatt (Spielplan) behaviour.
 *
 * Loaded only on the program template (footer scripts slot). Vanilla JS,
 * first-party only. Three jobs:
 *
 *  1. Quick filters (Saal/Box · OmU · Filmgespräch) — client-side over the
 *     server-rendered list; days that empty out disappear.
 *  2. Slide-down detail panels — server-rendered collapsed (0fr), toggled
 *     here; one open at a time.
 *  3. Column layout — day blocks are distributed into 1/2/3 fixed columns
 *     ONCE (and on resize/font-load/filtering). The browser never
 *     re-balances, so an open panel only pushes its own column down.
 */
(function () {
  'use strict';

  var program = document.getElementById('program');
  if (!program) return;

  var events = Array.prototype.slice.call(program.querySelectorAll('.event'));
  var days = Array.prototype.slice.call(program.querySelectorAll('.day'));
  var countEl = document.querySelector('.filters .count');
  var segButtons = Array.prototype.slice.call(document.querySelectorAll('.filters .seg button'));
  var chips = Array.prototype.slice.call(document.querySelectorAll('.filters .chip'));

  /* ----- filters (flags are generic: any .chip[data-flag=x] matches [data-x]) ----- */
  var state = { venue: 'alle', flags: {} };

  function apply() {
    var visible = 0;
    var activeFlags = Object.keys(state.flags).filter(function (f) { return state.flags[f]; });
    events.forEach(function (ev) {
      var ok = state.venue === 'alle' || ev.dataset.venue === state.venue;
      ok = ok && activeFlags.every(function (f) { return ev.hasAttribute('data-' + f); });
      ev.classList.toggle('hidden', !ok);
      if (ok) visible++;
    });
    days.forEach(function (day) {
      day.querySelectorAll('.venue-col').forEach(function (col) {
        col.classList.toggle('empty', !col.querySelector('.event:not(.hidden)'));
      });
      day.classList.toggle('hidden', !day.querySelector('.event:not(.hidden)'));
    });
    if (openDetail && openEv && openEv.classList.contains('hidden')) {
      openDetail.classList.remove('open');
      setExpanded(openEv, false);
      openEv = openDetail = null;
    }
    if (countEl) {
      countEl.textContent = visible + ' ' + (countEl.dataset.label || '');
    }
    layoutColumns(); /* day heights changed — rebalance now, not while browsing */
  }

  segButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.venue = btn.dataset.venue;
      segButtons.forEach(function (b) {
        b.setAttribute('aria-pressed', String(b === btn));
      });
      apply();
    });
  });

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      var flag = chip.dataset.flag;
      state.flags[flag] = !state.flags[flag];
      chip.setAttribute('aria-pressed', String(!!state.flags[flag]));
      apply();
    });
  });

  /* ----- detail panels: full-width slide-down inside the day block ----- */
  var openEv = null;
  var openDetail = null;

  function setExpanded(ev, expanded) {
    var btn = ev.querySelector('.t-btn');
    if (btn) btn.setAttribute('aria-expanded', String(expanded));
  }

  function toggle(ev) {
    var detail = document.querySelector(ev.dataset.detail);
    if (!detail) return;
    var opening = !detail.classList.contains('open');
    if (openDetail && openDetail !== detail) {
      openDetail.classList.remove('open');
      setExpanded(openEv, false);
    }
    detail.classList.toggle('open', opening);
    setExpanded(ev, opening);
    openEv = opening ? ev : null;
    openDetail = opening ? detail : null;
    if (opening) {
      /* stills are deferred (data-src) so collapsed panels cost no transfer */
      detail.querySelectorAll('img[data-src]').forEach(function (img) {
        img.src = img.dataset.src;
        img.removeAttribute('data-src');
      });
      setTimeout(function () {
        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        detail.scrollIntoView({ block: 'nearest', behavior: reduced ? 'auto' : 'smooth' });
      }, 320);
    }
  }

  /* whole card as convenience click target; keyboard comes free with the
     real <button> in the heading (its click bubbles here) */
  program.addEventListener('click', function (e) {
    if (e.target.closest('.event-detail') || e.target.closest('a')) return;
    var ev = e.target.closest('.event');
    if (ev) toggle(ev);
  });

  /* ----- column layout ----- */
  var blocks = days;
  var pcols = [];

  function layoutColumns() {
    if (blocks.length === 0) return;
    /* breakpoints must match the .program media queries in assets/css/index.css */
    var n = window.innerWidth >= 1080 ? 3 : window.innerWidth >= 720 ? 2 : 1;
    if (pcols.length !== n) {
      pcols.forEach(function (c) { c.remove(); });
      pcols = [];
      for (var i = 0; i < n; i++) {
        var c = document.createElement('div');
        c.className = 'pcol';
        program.appendChild(c);
        pcols.push(c);
      }
    }
    /* park everything in column 1 so heights are measured at real column width */
    blocks.forEach(function (b) { pcols[0].appendChild(b); });
    var mb = parseFloat(getComputedStyle(blocks[0]).marginBottom) || 0;
    var heights = blocks.map(function (b) { return b.offsetHeight ? b.offsetHeight + mb : 0; });
    var total = heights.reduce(function (a, h) { return a + h; }, 0);
    var share = total / n;

    var col = 0, colH = 0;
    blocks.forEach(function (b, i) {
      if (col < n - 1 && colH > 0 && colH + heights[i] / 2 > share) { col++; colH = 0; }
      pcols[col].appendChild(b);
      colH += heights[i];
    });
  }

  var resizeT;
  window.addEventListener('resize', function () {
    clearTimeout(resizeT);
    resizeT = setTimeout(layoutColumns, 150);
  });
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(layoutColumns); /* heights shift once Lipa loads */
  }

  apply();
})();
