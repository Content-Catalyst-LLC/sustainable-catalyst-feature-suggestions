(function () {
  'use strict';

  function initSelectAll(root) {
    var master = root.querySelector('[data-scfs-cg-select-all]');
    if (!master) {
      return;
    }
    master.addEventListener('change', function () {
      root.querySelectorAll('input[name="record_ids[]"]').forEach(function (checkbox) {
        checkbox.checked = master.checked;
      });
    });
  }

  function confirmBulk(root) {
    var form = root.querySelector('.scfs-cg-bulk');
    if (!form) {
      return;
    }
    form.addEventListener('submit', function (event) {
      var action = form.querySelector('[name="bulk_action"]');
      var selected = form.querySelectorAll('input[name="record_ids[]"]:checked');
      if (!action || !action.value || selected.length === 0) {
        event.preventDefault();
        window.alert('Select at least one record and a governance action.');
        return;
      }
      if (action.value === 'verify' && !window.confirm('Mark the selected records as verified? This records a governance history entry but does not publish them.')) {
        event.preventDefault();
      }
    });
  }

  function init() {
    document.querySelectorAll('.scfs-cg-admin').forEach(function (root) {
      initSelectAll(root);
      confirmBulk(root);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
