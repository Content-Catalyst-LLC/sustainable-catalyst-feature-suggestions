/** Sustainable Catalyst Feature Suggestions v5.0.0 connected operations. */
(function () {
  'use strict';
  document.addEventListener('click', function (event) {
    var target = event.target.closest('[data-scfs-confirm]');
    if (!target) return;
    if (!window.confirm(target.getAttribute('data-scfs-confirm'))) event.preventDefault();
  });
}());
