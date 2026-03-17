/**
 * Numen Tracking SDK — lightweight page analytics (<2KB).
 *
 * Usage:
 *   <script src="/js/numen-track.js"
 *           data-space="SPACE_ID"
 *           data-content="CONTENT_ID"
 *           data-endpoint="/api/v1"></script>
 *
 * Manual events:
 *   NumenTrack.event('conversion', {value: 10});
 */
(function () {
  'use strict';

  var script = document.currentScript;
  if (!script) return;

  var spaceId = script.getAttribute('data-space') || '';
  var contentId = script.getAttribute('data-content') || '';
  var base = (script.getAttribute('data-endpoint') || '/api/v1').replace(/\/$/, '');
  var endpoint = base + '/spaces/' + encodeURIComponent(spaceId) + '/tracking/events';

  // Session / visitor identifiers (simple, cookie-less)
  var sid = 's_' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36);
  var vid = (function () {
    try {
      var k = '_numen_vid';
      var v = localStorage.getItem(k);
      if (!v) { v = 'v_' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36); localStorage.setItem(k, v); }
      return v;
    } catch (e) { return null; }
  })();

  var queue = [];
  var timer = null;

  function flush() {
    if (!queue.length) return;
    var batch = queue.splice(0, 50);
    var body = JSON.stringify({ events: batch });
    if (navigator.sendBeacon) {
      navigator.sendBeacon(endpoint, new Blob([body], { type: 'application/json' }));
    } else {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', endpoint, true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.send(body);
    }
  }

  function enqueue(type, extra) {
    if (!spaceId || !contentId) return;
    var evt = {
      content_id: contentId,
      event_type: type,
      source: 'sdk',
      session_id: sid,
      visitor_id: vid,
      occurred_at: new Date().toISOString()
    };
    if (extra) {
      if (typeof extra.value !== 'undefined') evt.value = extra.value;
      evt.metadata = extra;
    }
    queue.push(evt);
    clearTimeout(timer);
    timer = setTimeout(flush, 1500);
  }

  // --- Auto-tracking ---

  // 1. page_view
  enqueue('page_view');

  // 2. scroll_depth (25/50/75/100 %)
  var maxScroll = 0;
  var scrollReported = {};
  function onScroll() {
    var h = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight) - window.innerHeight;
    if (h <= 0) return;
    var pct = Math.round((window.scrollY / h) * 100);
    if (pct > maxScroll) maxScroll = pct;
    [25, 50, 75, 100].forEach(function (t) {
      if (maxScroll >= t && !scrollReported[t]) {
        scrollReported[t] = true;
        enqueue('scroll_depth', { value: t, threshold: t });
      }
    });
  }
  window.addEventListener('scroll', onScroll, { passive: true });

  // 3. time_on_page (fire at 15s, 30s, 60s, 120s, 300s)
  var pageStart = Date.now();
  var timeThresholds = [15, 30, 60, 120, 300];
  var timeReported = {};
  var timeInterval = setInterval(function () {
    var elapsed = Math.round((Date.now() - pageStart) / 1000);
    timeThresholds.forEach(function (t) {
      if (elapsed >= t && !timeReported[t]) {
        timeReported[t] = true;
        enqueue('time_on_page', { value: t, seconds: t });
      }
    });
    if (elapsed >= 300) clearInterval(timeInterval);
  }, 5000);

  // Flush on page hide
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') flush();
  });

  // --- Public API ---
  window.NumenTrack = {
    event: function (type, data) { enqueue(type, data); },
    flush: flush
  };
})();
