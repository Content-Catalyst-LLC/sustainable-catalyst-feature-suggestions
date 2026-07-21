(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.scfs-connected-help-desk, .scfs-connected-help-desk-admin').forEach(function (root) {
            root.setAttribute('data-scfs-connected-help-desk-ready', 'true');
        });
    });
}());
