(function () {
  'use strict';

  function initializePortal(root) {
    if (!root || root.dataset.scfsCustomerPortalReady === '1') {
      return;
    }
    root.dataset.scfsCustomerPortalReady = '1';

    var reply = root.querySelector('.scfs-customer-portal__reply textarea');
    if (reply) {
      reply.addEventListener('input', function () {
        var remaining = 20000 - reply.value.length;
        reply.setAttribute('aria-description', remaining + ' characters remaining');
      });
    }

    root.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function () {
        var button = form.querySelector('button[type="submit"], button:not([type])');
        if (!button) {
          return;
        }
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        window.setTimeout(function () {
          button.disabled = false;
          button.removeAttribute('aria-busy');
        }, 12000);
      });
    });
  }

  function initializeAll() {
    document.querySelectorAll('[data-scfs-customer-portal]').forEach(initializePortal);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAll);
  } else {
    initializeAll();
  }

  document.addEventListener('scfs:support-view-loaded', initializeAll);
}());
