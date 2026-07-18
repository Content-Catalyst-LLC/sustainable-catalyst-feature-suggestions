(function () {
  'use strict';

  function setAll(directory, open) {
    directory.querySelectorAll('details[data-scfs-kb-product], details.scfs-kb-section-folder').forEach(function (detail) {
      detail.open = open;
    });
  }

  document.addEventListener('click', function (event) {
    var expand = event.target.closest('[data-scfs-kb-expand]');
    if (expand) {
      var directory = expand.closest('.scfs-kb-directory');
      if (directory) setAll(directory, true);
      return;
    }
    var collapse = event.target.closest('[data-scfs-kb-collapse]');
    if (collapse) {
      var directoryToCollapse = collapse.closest('.scfs-kb-directory');
      if (directoryToCollapse) setAll(directoryToCollapse, false);
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
