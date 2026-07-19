# Sustainable Catalyst Product Support and Feedback Platform

## Current release: v5.6.0

**Support Content Operations and Editorial Governance**

Version 5.6.0 coordinates content ownership, technical verification, review cadence, editorial workflow, publication integrity, supersession, and operational queues across Support Articles, Known Issues, and Release Records.

The canonical public destination remains `/support/`, and publication-grade Support Articles retain their existing `/support/guides/` URLs. Existing shortcodes, REST routes, CPTs, taxonomies, options, metadata, and records remain compatible.

See `docs/support-content-operations-editorial-governance-v5.6.0.md` for the operating model and governance boundaries.

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

## Feedback Intelligence and Product Signals

Version 5.6.0 adds an administrator-only Product Signals dashboard that combines privacy-minimized feature demand, article feedback, unresolved searches, Documentation Gaps, Known Issues, and support relationships. Signals are advisory and require human review.
