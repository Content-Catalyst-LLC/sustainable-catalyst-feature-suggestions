(function () {
  'use strict';

  function detailsIn(scope) {
    return scope.querySelectorAll('details[data-scfs-kb-category], details[data-scfs-kb-product], details.scfs-kb-section-folder');
  }

  function setAll(scope, open) {
    detailsIn(scope).forEach(function (detail) {
      detail.open = open;
    });
  }

  function setView(library, view) {
    library.querySelectorAll('[data-scfs-library-view]').forEach(function (button) {
      var active = button.getAttribute('data-scfs-library-view') === view;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    library.querySelectorAll('[data-scfs-library-panel]').forEach(function (panel) {
      var active = panel.getAttribute('data-scfs-library-panel') === view;
      panel.hidden = !active;
      panel.classList.toggle('is-active', active);
    });
    try {
      window.sessionStorage.setItem('scfs-support-library-view', view);
    } catch (error) {}
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.scfs-support-library').forEach(function (library) {
      var stored = 'products';
      try {
        stored = window.sessionStorage.getItem('scfs-support-library-view') || 'products';
      } catch (error) {}
      if (!library.querySelector('[data-scfs-library-panel="' + stored + '"]')) stored = 'products';
      setView(library, stored);
    });
  });

  document.addEventListener('click', function (event) {
    var viewButton = event.target.closest('[data-scfs-library-view]');
    if (viewButton) {
      var library = viewButton.closest('.scfs-support-library');
      if (library) setView(library, viewButton.getAttribute('data-scfs-library-view'));
      return;
    }

    var expand = event.target.closest('[data-scfs-kb-expand]');
    if (expand) {
      var expandLibrary = expand.closest('.scfs-support-library, .scfs-kb-directory');
      if (expandLibrary) setAll(expandLibrary, true);
      return;
    }

    var collapse = event.target.closest('[data-scfs-kb-collapse]');
    if (collapse) {
      var collapseLibrary = collapse.closest('.scfs-support-library, .scfs-kb-directory');
      if (collapseLibrary) setAll(collapseLibrary, false);
      return;
    }

    if (event.target.closest('[data-scfs-kb-print]')) {
      window.print();
    }
  });

  document.addEventListener('change', function (event) {
    if (!event.target.matches('[data-scfs-feedback-form] input[name="helpful"]')) return;
    var form = event.target.closest('[data-scfs-feedback-form]');
    if (!form) return;
    form.classList.add('has-rating');
    form.dataset.rating = event.target.value === '1' ? 'helpful' : 'not-helpful';
  });
})();
