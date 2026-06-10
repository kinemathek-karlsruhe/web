/* Kinemathek Karlsruhe — Monatsblatt listing behaviour.
 *
 * Drives any page with a #program listing (Spielplan, Events): quick filters,
 * slide-down detail panels, deterministic column layout. The shell (pivot
 * masthead) lives in monatsblatt.js, which dispatches 'pivot:content' after
 * swapping page content — initProgram() re-runs then and binds fresh nodes
 * (an element-level data flag keeps live, cached nodes from double-binding).
 */
(function () {
  'use strict';

  function initProgram() {
    var program = document.getElementById('program');
    if (!program || program.dataset.mbInit) return;
    program.dataset.mbInit = '1';

    var scope = program.closest('#pivot-content') || document;
    var events = Array.prototype.slice.call(program.querySelectorAll('.event'));
    var days = Array.prototype.slice.call(program.querySelectorAll('.day'));
    var emptyEl = program.querySelector('.program-empty');
    var countEl = scope.querySelector('.filters .count');
    var segButtons = Array.prototype.slice.call(scope.querySelectorAll('.filters .seg button'));
    var chips = Array.prototype.slice.call(scope.querySelectorAll('.filters .chip'));
    var seriesSelect = scope.querySelector('.filters select[data-filter="series"]');
    var resetBtn = scope.querySelector('.filters .reset');

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
      if (!program.isConnected) return;
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
      var detail = program.querySelector(ev.dataset.detail);
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

    /* Heute-Strip anchors: if the target day is filtered away, clear the
       filters first so the jump lands somewhere */
    scope.querySelectorAll('.heute-strip .hs-event').forEach(function (a) {
      a.addEventListener('click', function () {
        var target = program.querySelector(a.getAttribute('href'));
        if (target && target.classList.contains('hidden')) resetFilters();
      });
    });

    /* ----- column layout: we balance once; the browser never re-balances,
       so an open panel only ever pushes its own column down ----- */
    var pcols = [];

    function layoutColumns() {
      if (days.length === 0 || !program.isConnected) return;
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
    /* re-measure when the pivot puts this (cached, live) listing back */
    document.addEventListener('pivot:content', function () {
      if (program.isConnected) layoutColumns();
    });

    readHash();
    syncUI();
    apply();
  }

  initProgram();
  document.addEventListener('pivot:content', initProgram);
})();
