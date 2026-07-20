(function () {
  'use strict';

  function initialize(root) {
    if (!root || root.dataset.scfsConnectedReady === '1') {
      return;
    }
    root.dataset.scfsConnectedReady = '1';
    root.querySelectorAll('a[href^="#"]').forEach(function (link) {
      link.addEventListener('click', function () {
        var target = document.querySelector(link.getAttribute('href'));
        if (target) {
          target.setAttribute('tabindex', '-1');
          window.setTimeout(function () {
            target.focus({ preventScroll: true });
          }, 50);
        }
      });
    });
  }

  function boot(scope) {
    (scope || document).querySelectorAll('[data-scfs-connected-platform]').forEach(initialize);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { boot(document); });
  } else {
    boot(document);
  }

  document.addEventListener('scfs:support-view-rendered', function (event) {
    boot(event.target || document);
  });
}());
