/* Kinemathek Karlsruhe — Monatsblatt (Spielplan) behaviour.
 *
 * Loaded only on the program template (footer scripts slot). Vanilla JS,
 * first-party only. Three jobs:
 *
 *  1. Quick filters (Saal/Box · OmU · Filmgespräch · Reihe) — client-side over
 *     the server-rendered list; days that empty out disappear; state is
 *     mirrored into location.hash so filtered views survive reload/sharing
 *     (no request, no storage — privacy-clean).
 *  2. Slide-down detail panels — server-rendered collapsed (0fr), toggled
 *     here; one open at a time; Esc or the panel's ✕ closes; deferred stills
 *     (data-src) load on first open.
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
  var emptyEl = program.querySelector('.program-empty');
  var countEl = document.querySelector('.filters .count');
  var segButtons = Array.prototype.slice.call(document.querySelectorAll('.filters .seg button'));
  var chips = Array.prototype.slice.call(document.querySelectorAll('.filters .chip'));
  var seriesSelect = document.querySelector('.filters select[data-filter="series"]');
  var resetBtn = document.querySelector('.filters .reset');

  /* ----- filter state (flags are generic: any .chip[data-flag=x] matches [data-x]) ----- */
  var state = { venue: 'alle', flags: {}, series: '' };

  function activeFlags() {
    return Object.keys(state.flags).filter(function (f) { return state.flags[f]; });
  }

  function isFiltering() {
    return state.venue !== 'alle' || state.series !== '' || activeFlags().length > 0;
  }

  /* mirror state into the hash (#v=box&f=omu,talk&s=Kinobar) — shareable,
     reload-safe, no request */
  function writeHash() {
    var parts = [];
    if (state.venue !== 'alle') parts.push('v=' + state.venue);
    var flags = activeFlags();
    if (flags.length) parts.push('f=' + flags.join(','));
    if (state.series !== '') parts.push('s=' + encodeURIComponent(state.series));
    history.replaceState(null, '',
      parts.length ? '#' + parts.join('&') : location.pathname + location.search);
  }

  function readHash() {
    location.hash.slice(1).split('&').forEach(function (pair) {
      var i = pair.indexOf('=');
      if (i < 0) return;
      var key = pair.slice(0, i);
      var value = decodeURIComponent(pair.slice(i + 1));
      if (key === 'v' && (value === 'saal' || value === 'box')) state.venue = value;
      if (key === 'f') value.split(',').forEach(function (f) { if (f) state.flags[f] = true; });
      if (key === 's') state.series = value;
    });
    /* only accept a series that actually exists in the select */
    if (state.series !== '' && (!seriesSelect ||
        !Array.prototype.some.call(seriesSelect.options, function (o) { return o.value === state.series; }))) {
      state.series = '';
    }
  }

  function syncUI() {
    segButtons.forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.dataset.venue === state.venue));
    });
    chips.forEach(function (c) {
      c.setAttribute('aria-pressed', String(!!state.flags[c.dataset.flag]));
    });
    if (seriesSelect) seriesSelect.value = state.series;
  }

  function apply() {
    var visible = 0;
    var flags = activeFlags();
    events.forEach(function (ev) {
      var ok = state.venue === 'alle' || ev.dataset.venue === state.venue;
      ok = ok && (state.series === '' || ev.dataset.series === state.series);
      ok = ok && flags.every(function (f) { return ev.hasAttribute('data-' + f); });
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
      closeOpen();
    }
    if (countEl) {
      countEl.textContent = visible + ' ' +
        (visible === 1 ? countEl.dataset.labelOne : countEl.dataset.labelMany);
    }
    if (emptyEl) emptyEl.hidden = visible !== 0;
    if (resetBtn) resetBtn.hidden = !isFiltering();
    writeHash();
    layoutColumns(); /* day heights changed — rebalance now, not while browsing */
  }

  function resetFilters() {
    state = { venue: 'alle', flags: {}, series: '' };
    syncUI();
    apply();
  }

  segButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.venue = btn.dataset.venue;
      syncUI();
      apply();
    });
  });

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      var flag = chip.dataset.flag;
      state.flags[flag] = !state.flags[flag];
      syncUI();
      apply();
    });
  });

  if (seriesSelect) {
    seriesSelect.addEventListener('change', function () {
      state.series = seriesSelect.value;
      apply();
    });
  }

  if (resetBtn) resetBtn.addEventListener('click', resetFilters);

  /* ----- detail panels: full-width slide-down inside the day block ----- */
  var openEv = null;
  var openDetail = null;

  function setExpanded(ev, expanded) {
    var btn = ev.querySelector('.t-btn');
    if (btn) btn.setAttribute('aria-expanded', String(expanded));
  }

  function closeOpen(refocus) {
    if (!openDetail) return;
    openDetail.classList.remove('open');
    setExpanded(openEv, false);
    if (refocus) {
      var btn = openEv.querySelector('.t-btn');
      if (btn) btn.focus();
    }
    openEv = openDetail = null;
  }

  function toggle(ev) {
    var detail = document.querySelector(ev.dataset.detail);
    if (!detail) return;
    var opening = !detail.classList.contains('open');
    if (openDetail && openDetail !== detail) closeOpen();
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
    if (e.target.closest('.d-close')) { closeOpen(true); return; }
    if (e.target.closest('.event-detail') || e.target.closest('a')) return;
    var ev = e.target.closest('.event');
    if (ev) toggle(ev);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeOpen(true);
  });

  /* ----- WP7-style pivot navigation: the headline strip slides, the
     content region below the masthead swaps in place (same-origin fetch +
     pushState; items are real links, so no JS = normal navigation) ----- */
  var strip = document.querySelector('.pivot-strip');
  var pivotContent = document.getElementById('pivot-content');

  if (strip && pivotContent) {
    var pivotItems = Array.prototype.slice.call(strip.querySelectorAll('.pivot-item'));
    var pivotCache = {};
    var pivotCurrent = 'program';

    /* the program section keeps its LIVE nodes (listeners, open panels and
       filter state survive a round trip) */
    pivotCache.program = {
      nodes: Array.prototype.slice.call(pivotContent.childNodes),
      title: document.title
    };

    /* infinite loop, WP7-style: a cloned set follows the originals, the strip
       only ever slides FORWARD, and once it has travelled a full set width it
       snaps back silently (identical pixels, nobody sees it) */
    var pivotClones = pivotItems.map(function (original) {
      var clone = original.cloneNode(true);
      clone.classList.add('pivot-clone');
      clone.classList.remove('is-front');
      clone.setAttribute('aria-hidden', 'true');
      clone.setAttribute('tabindex', '-1');
      clone.removeAttribute('aria-current');
      strip.appendChild(clone);
      return clone;
    });
    var allPivotItems = pivotItems.concat(pivotClones);
    var stripX = 0;

    var setWidth = function () {
      return pivotClones[0].offsetLeft - pivotItems[0].offsetLeft;
    };

    var snapStrip = function (x) {
      stripX = Math.max(0, x);
      strip.style.transition = 'none';
      strip.style.transform = 'translateX(' + (-stripX) + 'px)';
      void strip.offsetHeight; /* flush so the next slide transitions again */
      strip.style.transition = '';
    };

    var setFront = function (el) {
      allPivotItems.forEach(function (i) { i.classList.toggle('is-front', i === el); });
    };

    var slideStrip = function (key) {
      allPivotItems.forEach(function (i) {
        var on = i.dataset.pivot === key;
        i.classList.toggle('is-active', on);
        if (i.classList.contains('pivot-clone')) return;
        if (on) i.setAttribute('aria-current', 'page');
        else i.removeAttribute('aria-current');
      });
      var candidates = allPivotItems.filter(function (i) { return i.dataset.pivot === key; });
      var next = candidates.filter(function (i) { return i.offsetLeft >= stripX - 1; })[0];
      if (!next) { /* ran past the clone set (e.g. reduced motion): wrap first */
        snapStrip(stripX - setWidth());
        next = candidates.filter(function (i) { return i.offsetLeft >= stripX - 1; })[0] || candidates[0];
      }
      stripX = next.offsetLeft;
      setFront(next);
      strip.style.transform = 'translateX(' + (-stripX) + 'px)';
    };

    strip.addEventListener('transitionend', function (e) {
      if (e.target !== strip || e.propertyName !== 'transform') return;
      var w = setWidth();
      if (w > 0 && stripX >= w) {
        snapStrip(stripX - w);
        /* the front role moves from the clone to its original counterpart */
        setFront(pivotItems.filter(function (i) { return i.dataset.pivot === pivotCurrent; })[0]);
      }
    });

    var loadSection = function (key, url) {
      if (pivotCache[key]) return Promise.resolve(pivotCache[key]);
      return fetch(url).then(function (r) {
        if (!r.ok) throw new Error(r.status);
        return r.text();
      }).then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var region = doc.getElementById('pivot-content') || doc.querySelector('main');
        var nodes = Array.prototype.slice.call(region ? region.childNodes : [])
          .map(function (n) { return document.adoptNode(n); });
        pivotCache[key] = { nodes: nodes, title: doc.title };
        return pivotCache[key];
      });
    };

    var goPivot = function (item, push) {
      var key = item.dataset.pivot;
      if (key === pivotCurrent) return;
      pivotCurrent = key;
      slideStrip(key);
      pivotContent.classList.add('pivot-out');
      var outDone = new Promise(function (res) { setTimeout(res, 230); });
      Promise.all([loadSection(key, item.href), outDone]).then(function (loaded) {
        var section = loaded[0];
        pivotContent.replaceChildren.apply(pivotContent, section.nodes);
        document.title = section.title;
        pivotContent.classList.remove('pivot-out');
        pivotContent.classList.add('pivot-in');
        setTimeout(function () { pivotContent.classList.remove('pivot-in'); }, 450);
        if (push) history.pushState({ pivot: key }, '', item.href);
        if (key === 'program') layoutColumns(); /* re-measure after reattach */
      }).catch(function () {
        location.href = item.href; /* graceful fallback: plain navigation */
      });
    };

    strip.addEventListener('click', function (e) {
      var item = e.target.closest('.pivot-item');
      if (!item) return;
      e.preventDefault();
      goPivot(item, true);
    });

    window.addEventListener('popstate', function () {
      var path = location.pathname.replace(/\/$/, '');
      var item = pivotItems.filter(function (i) {
        return new URL(i.href).pathname.replace(/\/$/, '') === path;
      })[0] || pivotItems[0];
      goPivot(item, false);
    });
  }

  /* Heute-Strip anchors: if the target day is filtered away, clear the
     filters first so the jump lands somewhere */
  document.querySelectorAll('.heute-strip .hs-event').forEach(function (a) {
    a.addEventListener('click', function () {
      var target = document.querySelector(a.getAttribute('href'));
      if (target && target.classList.contains('hidden')) resetFilters();
    });
  });

  /* ----- column layout ----- */
  var pcols = [];

  function layoutColumns() {
    if (days.length === 0) return;
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
    days.forEach(function (b) { pcols[0].appendChild(b); });
    var mb = parseFloat(getComputedStyle(days[0]).marginBottom) || 0;
    var heights = days.map(function (b) { return b.offsetHeight ? b.offsetHeight + mb : 0; });
    var total = heights.reduce(function (a, h) { return a + h; }, 0);
    var share = total / n;

    var col = 0, colH = 0;
    days.forEach(function (b, i) {
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

  readHash();
  syncUI();
  apply();
})();
