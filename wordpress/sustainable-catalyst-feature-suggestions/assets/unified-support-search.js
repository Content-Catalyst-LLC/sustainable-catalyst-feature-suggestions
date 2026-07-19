(function () {
  'use strict';

  function init(root) {
    if (!root || root.dataset.scfsUnifiedReady === '1') return;
    root.dataset.scfsUnifiedReady = '1';

    var form = root.querySelector('[data-scfs-unified-search-form]');
    var query = form ? form.querySelector('input[type="search"]') : null;

    root.addEventListener('click', function (event) {
      var groupLink = event.target.closest('[data-scfs-jump-group]');
      if (!groupLink) return;
      var group = root.querySelector('[data-scfs-result-group="' + groupLink.getAttribute('data-scfs-jump-group') + '"]');
      if (group) {
        event.preventDefault();
        group.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        var heading = group.querySelector('h3');
        if (heading) {
          heading.setAttribute('tabindex', '-1');
          heading.focus({ preventScroll: true });
        }
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === '/' && query && !/input|textarea|select/i.test(document.activeElement.tagName)) {
        event.preventDefault();
        query.focus();
      }
      if (event.key === 'Escape' && query && document.activeElement === query && query.value) {
        query.value = '';
      }
    });
  }

  function boot(scope) {
    (scope || document).querySelectorAll('[data-scfs-unified-search]').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { boot(document); });
  } else {
    boot(document);
  }

  document.addEventListener('scfs:support-view-loaded', function (event) {
    boot(event.detail && event.detail.root ? event.detail.root : document);
  });
}());
