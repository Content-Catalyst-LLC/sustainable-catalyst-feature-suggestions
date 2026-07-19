# Sustainable Catalyst Product Support and Feedback Platform

## Current release: v5.9.0

**Public API, Embeds, and Institutional Support Integration**

Version 5.9.0 exposes privacy-safe public support contracts, responsive product embeds, deterministic version verification, institutional integration contracts, access governance, and cross-platform handoffs.

The canonical public destination remains `/support/`, and Support Articles retain their existing `/support/guides/` URLs. Existing shortcodes, REST routes, CPTs, taxonomies, options, metadata, and records remain compatible.

See `docs/public-api-embeds-institutional-support-v5.9.0.md` for the operating model and governance boundaries.

## Primary public shortcodes

- `[scfs_product_support_center]`
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
