/** Sustainable Catalyst Feature Suggestions v5.0.0 content operations reliability. */
(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var target = event.target.closest('[data-scfs-confirm]');
    if (!target) return;
    var message = target.getAttribute('data-scfs-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  document.addEventListener('submit', function (event) {
    if (!event.target.closest('.scfs-content-operations')) return;
    var status = document.querySelector('.scfs-content-ops-status');
    if (status) status.textContent = 'Operation started. Keep this page open until WordPress reports completion.';
    var button = event.submitter;
    if (button) button.setAttribute('aria-disabled', 'true');
  });
}());
