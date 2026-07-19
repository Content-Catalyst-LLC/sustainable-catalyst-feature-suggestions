(function () {
  "use strict";
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-scfs-support-analytics]").forEach(function (root) {
      root.classList.add("scfs-analytics-ready");
    });
  });
}());
