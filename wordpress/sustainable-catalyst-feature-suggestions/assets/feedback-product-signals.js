(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var table = document.querySelector('.scfs-fps-table');
    if (!table) {
      return;
    }

    table.querySelectorAll('tbody tr[data-scfs-fps-state]').forEach(function (row) {
      row.setAttribute('tabindex', '0');
    });
  });
}());
