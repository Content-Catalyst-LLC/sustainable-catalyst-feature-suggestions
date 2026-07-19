(function () {
  'use strict';
  function init(root) {
    if (!root || root.dataset.scfsPublicSupportReady === '1') return;
    root.dataset.scfsPublicSupportReady = '1';
    root.querySelectorAll('a[href]').forEach(function (link) {
      if (link.hostname && link.hostname !== window.location.hostname) {
        link.rel = 'noopener noreferrer';
      }
    });
  }
  function boot(scope) {
    (scope || document).querySelectorAll('[data-scfs-support-embed], [data-scfs-public-integrations]').forEach(init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { boot(document); });
  else boot(document);
  document.addEventListener('scfs:content-rendered', function (event) { boot(event.target || document); });
}());
