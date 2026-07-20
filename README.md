# Sustainable Catalyst Product Support and Feedback Platform

## Current release: v6.0.0

**Connected Product Support and Feedback Platform**

Version 6.0.0 connects the public Support Center, publication library, Known Issues, releases, feedback intelligence, documentation-effectiveness analytics, public integrations, and cross-product handoffs through one governed platform contract.

The canonical public destination remains `/support/`, and Support Articles retain their existing `/support/guides/` URLs. Existing shortcodes, REST routes, CPTs, taxonomies, options, metadata, and records remain compatible.

See `docs/connected-product-support-feedback-platform-v6.0.0.md` for the architecture and governance boundaries.

## Primary public shortcodes

- `[scfs_product_support_center]`
- `[scfs_connected_product_support_platform]`
- `[scfs_unified_support_search]`
- `[scfs_support_knowledge_base]`
- `[scfs_issue_release_intelligence]`
- `[scfs_cross_product_support_graph]`
- `[scfs_support_embed product="decision-studio"]`

## Repository layout

- `wordpress/` — WordPress plugin source
- `backend/` — deterministic FastAPI support intelligence service
- `schemas/` — versioned contracts
- `examples/` — synthetic public examples
- `tests/` — WordPress contract tests
- `docs/` — implementation and governance guides
