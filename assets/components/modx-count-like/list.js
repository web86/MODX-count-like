/* Lightweight list viewer: one read-only GET for every 100 unique article IDs. */
(function () {
  'use strict';

  function init() {
    var elements = Array.prototype.slice.call(document.querySelectorAll('[data-mcl-list-id][data-mcl-list-api]'));
    var groups = {};
    elements.forEach(function (el) {
      var id = el.getAttribute('data-mcl-list-id');
      var api = el.getAttribute('data-mcl-list-api');
      if (!/^[1-9][0-9]*$/.test(id) || !api) return;
      if (!groups[api]) groups[api] = { ids: {}, elements: {} };
      groups[api].ids[id] = true;
      if (!groups[api].elements[id]) groups[api].elements[id] = [];
      groups[api].elements[id].push(el);
    });

    Object.keys(groups).forEach(function (api) {
      var group = groups[api];
      var ids = Object.keys(group.ids);
      for (var i = 0; i < ids.length; i += 100) {
        (function (chunk) {
          var url = api + (api.indexOf('?') === -1 ? '?' : '&') + 'ids=' + encodeURIComponent(chunk.join(','));
          fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) {
              if (!response.ok) throw new Error('HTTP ' + response.status);
              return response.json();
            })
            .then(function (data) {
              if (!data.ok || !data.stats) throw new Error('Invalid batch response');
              chunk.forEach(function (id) {
                if (!Object.prototype.hasOwnProperty.call(data.stats, id)) return;
                var count = data.stats[id].views;
                if (typeof count !== 'number' || !Number.isFinite(count) || count < 0) return;
                group.elements[id].forEach(function (el) {
                  var node = el.querySelector('[data-mcl-list-count]');
                  if (node) node.textContent = String(count);
                });
              });
            })
            .catch(function (error) {
              console.warn('[MODX Count & Like list]', error);
            });
        })(ids.slice(i, i + 100));
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
