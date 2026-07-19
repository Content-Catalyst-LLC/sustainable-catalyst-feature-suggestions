# Sustainable Catalyst Product Support and Feedback Platform

## Current release: v5.8.0

**Cross-Product Support Graph and Platform Handoffs**

Version 5.8.0 connects products, versions, components, capabilities, Support Articles, Known Issues, releases, examples, troubleshooting guidance, and governed platform handoffs without exposing private support records or raw searches.

The canonical public destination remains `/support/`, and Support Articles retain their existing `/support/guides/` URLs. Existing shortcodes, REST routes, CPTs, taxonomies, options, metadata, and records remain compatible.

See `docs/cross-product-support-graph-platform-handoffs-v5.8.0.md` for the operating model and governance boundaries.

## Primary public shortcodes

- `[scfs_product_support_center]`
- `[scfs_unified_support_search]`
- `[scfs_support_knowledge_base]`
- `[scfs_issue_release_intelligence]`
- `[scfs_cross_product_support_graph]`

## Repository layout

- `wordpress/` — WordPress plugin source
- `backend/` — deterministic FastAPI support intelligence service
- `schemas/` — versioned contracts
- `examples/` — synthetic public examples
- `tests/` — WordPress contract tests
- `docs/` — implementation and governance guides
