(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var target = event.target.closest('.scfs-sync-confirm');
    if (!target) {
      return;
    }
    var message = target.getAttribute('data-confirm') || 'Continue?';
    if (!window.confirm(message)) {
      event.preventDefault();
    }
  });
})();
