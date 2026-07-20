(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('.scfs-agent-workspace');
    if (!root) return;

    var selectAll = root.querySelector('[data-scfs-select-all]');
    var checkboxes = Array.prototype.slice.call(root.querySelectorAll('[data-scfs-case-checkbox]'));
    if (selectAll) {
      selectAll.addEventListener('change', function () {
        checkboxes.forEach(function (checkbox) {
          checkbox.checked = selectAll.checked;
        });
      });
    }

    root.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        var operation = form.querySelector('[name="bulk_operation"]');
        if (!operation || !operation.value) return;
        var selected = form.querySelectorAll('[data-scfs-case-checkbox]:checked');
        if (!selected.length) {
          event.preventDefault();
          window.alert('Select at least one case.');
        }
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) return;
      var target = event.target;
      if (target && /input|textarea|select/i.test(target.tagName)) return;
      var search = root.querySelector('input[type="search"]');
      if (search) {
        event.preventDefault();
        search.focus();
      }
    });
  });
}());
