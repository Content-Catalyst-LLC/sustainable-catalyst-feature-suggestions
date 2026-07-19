/* Sustainable Catalyst integrated Knowledge Base v5.2.9 */
(function () {
  'use strict';

  function detailsIn(scope) {
    return scope.querySelectorAll('details[data-scfs-kb-category], details[data-scfs-kb-product], details.scfs-kb-section-folder');
  }

  function setAll(scope, open) {
    detailsIn(scope).forEach(function (detail) { detail.open = open; });
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
    try { window.sessionStorage.setItem('scfs-support-library-view', view); } catch (error) {}
  }

  function initDiscovery(browser) {
    if (!browser || browser.dataset.scfsDiscoveryEnhanced === '1') return;
    browser.dataset.scfsDiscoveryEnhanced = '1';
    var form = browser.querySelector('[data-scfs-discovery-form]');
    var search = browser.querySelector('[data-scfs-discovery-search]');
    if (form && search) {
      search.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && search.value) {
          search.value = '';
          search.focus();
          event.preventDefault();
        }
      });
    }
    browser.querySelectorAll('.scfs-kb-library-navigation a, .scfs-kb-active-filters a, .scfs-kb-query-suggestions a').forEach(function (link) {
      link.addEventListener('click', function () { browser.classList.add('is-navigating'); });
    });
  }

  function initLibrary(library) {
    if (!library || library.dataset.scfsKbEnhanced === '1') return;
    library.dataset.scfsKbEnhanced = '1';
    var stored = 'products';
    try { stored = window.sessionStorage.getItem('scfs-support-library-view') || 'products'; } catch (error) {}
    if (!library.querySelector('[data-scfs-library-panel="' + stored + '"]')) stored = 'products';
    if (library.querySelector('[data-scfs-library-panel]')) setView(library, stored);
  }

  function initAll(context) {
    var scope = context || document;
    scope.querySelectorAll('.scfs-support-library').forEach(initLibrary);
    scope.querySelectorAll('.scfs-kb--library-browser').forEach(function (browser) {
      browser.dataset.scfsKbEnhanced = '1';
      initDiscovery(browser);
    });
  }

  document.addEventListener('DOMContentLoaded', function () { initAll(document); });
  document.addEventListener('scfs:support-view-changed', function (event) {
    initAll(event.detail && event.detail.workspace ? event.detail.workspace : document);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) return;
    var tag = event.target && event.target.tagName ? event.target.tagName.toLowerCase() : '';
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
    var search = document.querySelector('.scfs-kb--library-browser [data-scfs-discovery-search]');
    if (search) { event.preventDefault(); search.focus(); }
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
    if (event.target.closest('[data-scfs-kb-print]')) window.print();
  });

  document.addEventListener('change', function (event) {
    if (!event.target.matches('[data-scfs-feedback-form] input[name="helpful"]')) return;
    var form = event.target.closest('[data-scfs-feedback-form]');
    if (!form) return;
    form.classList.add('has-rating');
    form.dataset.rating = event.target.value === '1' ? 'helpful' : 'not-helpful';
  });

  window.SCFSIntegratedKnowledgeBase = { initAll: initAll, setView: setView, setAll: setAll };
}());
