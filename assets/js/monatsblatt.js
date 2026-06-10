/* Kinemathek Karlsruhe — Monatsblatt shell behaviour (all designed pages).
 *
 * Owns the masthead: the WP7-style pivot navigation (headline strip slides,
 * #pivot-content swaps via same-origin fetch + pushState) and the program
 * label's measured widths. Page-specific behaviour (program filters, panels,
 * columns) lives in program.js, which (re)initialises itself on the
 * 'pivot:content' event this file dispatches after every swap.
 */
(function () {
  'use strict';

  var strip = document.querySelector('.pivot-strip');
  var pivotContent = document.getElementById('pivot-content');
  if (!strip || !pivotContent) return;

  var pivotItems = Array.prototype.slice.call(strip.querySelectorAll('.pivot-item'));
  var activeItem = strip.querySelector('.pivot-item.is-active') || pivotItems[0];
  var pivotCurrent = activeItem.dataset.pivot;
  var pivotCache = {};

  /* the section we arrived on keeps its LIVE nodes (listeners, open panels
     and filter state survive a round trip) */
  pivotCache[pivotCurrent] = {
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

  /* the program label's slot follows the visible text — measure both
     variants so CSS can size it (months at the front, name otherwise) */
  var pivotLabels = Array.prototype.slice.call(strip.querySelectorAll('.pivot-label'));
  var measureLabels = function () {
    pivotLabels.forEach(function (label) {
      var months = label.querySelector('.lbl-months');
      var name = label.querySelector('.lbl-name');
      if (!months || !name) return;
      label.style.setProperty('--w-months', months.offsetWidth + 'px');
      label.style.setProperty('--w-name', name.offsetWidth + 'px');
    });
  };

  /* on a full page load the server marks the active item but the strip
     sits at 0 — snap (not slide) the front item to the front position;
     also re-snaps after resize/font-load when offsets shift */
  var reposition = function () {
    measureLabels();
    var front = strip.querySelector('.pivot-item.is-front') || pivotItems[0];
    snapStrip(front.offsetLeft);
  };
  reposition();

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
    /* moving the front marker resizes the program label, which shifts
       offsets — re-measure the target AFTER the state change */
    setFront(next);
    stripX = next.offsetLeft;
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
      /* page-specific scripts (program.js) (re)initialise on this */
      document.dispatchEvent(new CustomEvent('pivot:content', { detail: { key: key } }));
    }).catch(function () {
      location.href = item.href; /* graceful fallback: plain navigation */
    });
  };

  strip.addEventListener('click', function (e) {
    var item = e.target.closest('.pivot-item');
    if (!item || !item.href) return; /* the self-title item is a plain span */
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

  var resizeT;
  window.addEventListener('resize', function () {
    clearTimeout(resizeT);
    resizeT = setTimeout(reposition, 150);
  });
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(reposition); /* metrics shift once Lipa loads */
  }
})();
