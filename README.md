# Sustainable Catalyst Product Support and Feedback Platform

## Current release: v6.1.0

**Help Desk Case Foundation**

Version 6.1.0 adds the private case-record foundation required for a full help desk: dedicated case tables, human-readable case numbers, validated status transitions, participants, messages, internal notes, assignments, relationships, attachment metadata, SLA events, audit history, authenticated REST routes, and WP-CLI operations.

The public Support Center remains at `/support/`, and Support Articles retain their existing `/support/guides/` URLs. Contact and Engagement remains authoritative for requester identity, consent, and secure uploaded files.

See `docs/help-desk-case-foundation-v6.1.0.md` for the private-record architecture and governance boundaries.

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
