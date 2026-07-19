# Sustainable Catalyst Product Support and Feedback Platform

## Current release: v5.5.0

**Support Content Operations and Editorial Governance**

Version 5.5.0 coordinates content ownership, technical verification, review cadence, editorial workflow, publication integrity, supersession, and operational queues across Support Articles, Known Issues, and Release Records.

The canonical public destination remains `/support/`, and publication-grade Support Articles retain their existing `/support/guides/` URLs. Existing shortcodes, REST routes, CPTs, taxonomies, options, metadata, and records remain compatible.

See `docs/support-content-operations-editorial-governance-v5.5.0.md` for the operating model and governance boundaries.

## Primary public shortcodes

- `[scfs_product_support_center]`
- `[scfs_unified_support_search]`
- `[scfs_support_knowledge_base]`
- `[scfs_issue_release_intelligence]`

## Repository layout

- `wordpress/` — WordPress plugin source
- `backend/` — deterministic FastAPI support intelligence service
- `schemas/` — versioned contracts
- `examples/` — synthetic public examples
- `tests/` — WordPress contract tests
- `docs/` — implementation and governance guides
