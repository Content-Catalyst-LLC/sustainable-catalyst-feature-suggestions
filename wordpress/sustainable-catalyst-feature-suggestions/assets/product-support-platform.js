/*
 * Sustainable Catalyst Feature Suggestions v4.5.0
 * Navigation and Embedded Pathway Reliability
 */
(function () {
  'use strict';

  var ROOT_SELECTOR = '.scfs-support-platform[data-scfs-interactive="1"]';

  function supportParams(url) {
    var parsed = new URL(url, window.location.href);
    return {
      view: parsed.searchParams.get('scfs_support_view') || 'overview',
      product: parsed.searchParams.get('scfs_support_product') || '',
      survey: parsed.searchParams.get('scfs_support_survey') || ''
    };
  }

  function directUrl(root, view, product, survey) {
    var url = new URL(root.dataset.scfsBaseUrl || window.location.href, window.location.href);
    url.searchParams.delete('scfs_support_view');
    url.searchParams.delete('scfs_support_product');
    url.searchParams.delete('scfs_support_survey');
    url.searchParams.set('scfs_support_view', view || 'overview');
    if (product) {
      url.searchParams.set('scfs_support_product', product);
    }
    if (survey) {
      url.searchParams.set('scfs_support_survey', survey);
    }
    url.hash = root.dataset.scfsAnchor || 'support-center';
    return url.toString();
  }

  function updateNavigation(root, view, product) {
    root.dataset.scfsCurrentView = view;
    root.querySelectorAll('.scfs-support-platform__nav [data-scfs-support-view]').forEach(function (link) {
      var active = link.dataset.scfsSupportView === view;
      link.classList.toggle('is-active', active);
      if (active) {
        link.setAttribute('aria-current', 'page');
      } else {
        link.removeAttribute('aria-current');
      }
      link.dataset.scfsSupportProduct = product || '';
      link.href = directUrl(root, link.dataset.scfsSupportView, product || '', '');
    });
    var hiddenView = root.querySelector('.scfs-support-product-filter input[name="scfs_support_view"]');
    if (hiddenView) {
      hiddenView.value = view;
    }
    var filter = root.querySelector('.scfs-support-product-filter');
    if (filter) {
      filter.dataset.scfsCurrentView = view;
    }
    var select = root.querySelector('.scfs-support-product-filter select[name="scfs_support_product"]');
    if (select) {
      select.value = product || '';
    }
  }

  function announce(root, message) {
    var live = root.querySelector('.scfs-support-platform__navigation-status');
    if (!live) {
      live = document.createElement('p');
      live.className = 'scfs-support-platform__sr-only scfs-support-platform__navigation-status';
      live.setAttribute('aria-live', 'polite');
      root.appendChild(live);
    }
    live.textContent = '';
    window.setTimeout(function () {
      live.textContent = message;
    }, 20);
  }

  function emitViewChanged(root, detail) {
    document.dispatchEvent(new CustomEvent('scfs:support-view-changed', {
      detail: Object.assign({ root: root }, detail || {})
    }));
  }

  async function loadView(root, options) {
    var workspace = root.querySelector('.scfs-support-platform__workspace');
    var endpoint = root.dataset.scfsEndpoint;
    var view = options.view || 'overview';
    var product = options.product || '';
    var survey = options.survey || '';
    var targetUrl = options.url || directUrl(root, view, product, survey);

    if (!workspace || !endpoint) {
      window.location.assign(targetUrl);
      return;
    }

    var request = new URL(endpoint, window.location.href);
    request.searchParams.set('view', view);
    request.searchParams.set('product', product);
    request.searchParams.set('survey', survey);
    request.searchParams.set('base_url', root.dataset.scfsBaseUrl || window.location.href);
    request.searchParams.set('anchor', root.dataset.scfsAnchor || 'support-center');
    request.searchParams.set('show_overview_pathways', root.dataset.scfsShowOverviewPathways || '0');

    root.classList.add('is-loading');
    workspace.setAttribute('aria-busy', 'true');

    try {
      var response = await fetch(request.toString(), {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      var payload = await response.json();
      if (!response.ok || !payload || typeof payload.html !== 'string') {
        throw new Error(payload && payload.message ? payload.message : 'Unable to load support view.');
      }

      workspace.innerHTML = payload.html;
      workspace.dataset.scfsView = payload.view || view;
      updateNavigation(root, payload.view || view, payload.product || product);

      if (options.history === 'push') {
        window.history.pushState({
          scfsSupportView: payload.view || view,
          scfsSupportProduct: payload.product || product,
          scfsSupportSurvey: payload.survey || survey,
          scfsSupportId: root.id
        }, '', targetUrl);
      } else if (options.history === 'replace') {
        window.history.replaceState({
          scfsSupportView: payload.view || view,
          scfsSupportProduct: payload.product || product,
          scfsSupportSurvey: payload.survey || survey,
          scfsSupportId: root.id
        }, '', targetUrl);
      }

      announce(root, 'Support section loaded: ' + (payload.view || view).replace(/-/g, ' ') + '.');
      emitViewChanged(root, {
        view: payload.view || view,
        product: payload.product || product,
        survey: payload.survey || survey,
        workspace: workspace
      });
    } catch (error) {
      window.location.assign(targetUrl);
    } finally {
      root.classList.remove('is-loading');
      workspace.setAttribute('aria-busy', 'false');
    }
  }

  function onClick(root, event) {
    var link = event.target.closest('a[data-scfs-support-view]');
    if (!link || !root.contains(link)) {
      return;
    }
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank') {
      return;
    }
    event.preventDefault();
    loadView(root, {
      view: link.dataset.scfsSupportView || supportParams(link.href).view,
      product: link.dataset.scfsSupportProduct || supportParams(link.href).product,
      survey: link.dataset.scfsSupportSurvey || supportParams(link.href).survey,
      url: link.href,
      history: 'push'
    });
  }

  function onSubmit(root, event) {
    var form = event.target.closest('form[data-scfs-support-filter]');
    if (!form || !root.contains(form)) {
      return;
    }
    event.preventDefault();
    var data = new FormData(form);
    var view = data.get('scfs_support_view') || root.dataset.scfsCurrentView || 'overview';
    var product = data.get('scfs_support_product') || '';
    var url = directUrl(root, view, product, '');
    loadView(root, { view: view, product: product, survey: '', url: url, history: 'push' });
  }

  function init(root) {
    if (!root || root.dataset.scfsNavigationReady === '1') {
      return;
    }
    root.dataset.scfsNavigationReady = '1';
    root.addEventListener('click', function (event) { onClick(root, event); });
    root.addEventListener('submit', function (event) { onSubmit(root, event); });
    updateNavigation(root, root.dataset.scfsCurrentView || 'overview', supportParams(window.location.href).product);
  }

  function initAll(context) {
    (context || document).querySelectorAll(ROOT_SELECTOR).forEach(init);
  }

  document.addEventListener('DOMContentLoaded', function () {
    initAll(document);
  });

  window.addEventListener('popstate', function () {
    var state = supportParams(window.location.href);
    document.querySelectorAll(ROOT_SELECTOR).forEach(function (root) {
      loadView(root, {
        view: state.view,
        product: state.product,
        survey: state.survey,
        url: window.location.href,
        history: 'none'
      });
    });
  });

  window.SCFSProductSupportPlatform = {
    init: init,
    initAll: initAll,
    loadView: loadView,
    directUrl: directUrl
  };
}());
