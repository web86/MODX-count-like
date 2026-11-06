/* MODX Count & Like. Plain JavaScript, no dependencies. */
(function () {
  'use strict';
  function fetchJson(url, options) {
    return fetch(url, Object.assign({ credentials: 'same-origin', cache: 'no-store' }, options || {}))
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      }).then(function (data) {
        if (!data.ok) throw new Error(data.error || 'API error');
        return data;
      });
  }
  function mount(el) {
    if (el.dataset.mclMounted) return;
    el.dataset.mclMounted = '1';
    var id = el.dataset.mclId;
    var api = el.dataset.mclApi;
    if (!id || !api) return;
    var viewsNode = el.querySelector('[data-mcl-views]');
    var likesNode = el.querySelector('[data-mcl-likes]');
    var btn = el.querySelector('[data-mcl-like]');
    var url = api + (api.indexOf('?') === -1 ? '?' : '&') + 'id=' + encodeURIComponent(id);
    function render(data) {
      if (viewsNode) viewsNode.textContent = data.views;
      if (likesNode) likesNode.textContent = data.likes;
      if (btn) btn.setAttribute('aria-pressed', data.liked ? 'true' : 'false');
    }
    function post(action) {
      return fetchJson(api, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: 'id=' + encodeURIComponent(id) + '&action=' + encodeURIComponent(action)
      }).then(render);
    }
    fetchJson(url).then(render).catch(function () { /* keep server-rendered counts */ });
    if (btn) {
      btn.addEventListener('click', function () {
        btn.disabled = true;
        post('toggle_like').catch(function (err) { console.warn('[MODX Count & Like]', err); })
          .finally(function () { btn.disabled = false; });
      });
    }
    if (el.dataset.mclTrack !== '1') return;
    var key = 'mcl-view-' + id;
    try {
      var last = Number(localStorage.getItem(key)) || 0;
      if (Date.now() - last < 12 * 60 * 60 * 1000 && Date.now() >= last) return;
    } catch (e) { /* storage is optional */ }
    var fired = false, visibleMillis = 0, lastTick = Date.now();
    var timer = setInterval(function () {
      var now = Date.now();
      if (!document.hidden) visibleMillis += Math.min(now - lastTick, 1000);
      lastTick = now;
      if (visibleMillis < 5000 || fired) return;
      fired = true;
      clearInterval(timer);
      post('view').then(function () {
        try { localStorage.setItem(key, String(Date.now())); } catch (e) { /* optional */ }
      }).catch(function (err) { console.warn('[MODX Count & Like]', err); });
    }, 250);
  }
  function start() {
    document.querySelectorAll('.mcl[data-mcl-id]').forEach(mount);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
