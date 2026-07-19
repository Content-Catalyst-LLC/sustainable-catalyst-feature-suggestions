(() => {
  "use strict";
  const roots = document.querySelectorAll("[data-scfs-support-graph]");
  roots.forEach((root) => {
    const select = root.querySelector('select[name="scfs_graph_product"]');
    const search = root.querySelector('input[name="scfs_graph_intent"]');
    if (select) {
      select.addEventListener("change", () => {
        root.dataset.selectedProduct = select.value;
      });
    }
    if (search) {
      search.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
          search.value = "";
        }
      });
    }
  });
})();
