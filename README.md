# Sustainable Catalyst Product Support and Feedback Platform

## Current release: v5.4.0

**Known Issues and Release Intelligence Integration**

Version 5.4.0 connects Known Issues, affected versions, components, workarounds, Support Articles, target releases, fixed releases, Release Records, and changelog evidence into one human-reviewed operational support layer.

The canonical public destination remains `/support/`, and publication-grade Support Articles retain their existing `/support/guides/` URLs. Existing shortcodes, REST routes, CPTs, taxonomies, options, metadata, and records remain compatible.

See `docs/known-issues-release-intelligence-v5.4.0.md` for the architecture and governance boundaries.

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
