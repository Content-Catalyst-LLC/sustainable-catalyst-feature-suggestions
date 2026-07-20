# Sustainable Catalyst Product Support and Feedback Platform

## Current release: v6.3.0

**Customer Support Portal and Conversations**

Version 6.3.0 adds the secure requester-facing portal above the v6.1.0 private case foundation and v6.2.0 Agent Workspace. Expiring access links exchange hash-only tokens for short-lived HttpOnly sessions, customers can view participant-visible conversations, reply, confirm resolution, reopen recent cases, and submit private satisfaction feedback.

The public Support Center remains at `/support/`, Support Articles retain `/support/guides/`, and the customer portal is designed for `/support/cases/`. Contact and Engagement remains authoritative for requester identity, consent, secure attachments, and notification delivery.

See `docs/help-desk-customer-portal-v6.3.0.md` for the portal architecture and privacy boundaries.

## Primary public shortcodes

- `[scfs_product_support_center]`
- `[scfs_connected_product_support_platform]`
- `[scfs_unified_support_search]`
- `[scfs_support_knowledge_base]`
- `[scfs_issue_release_intelligence]`
- `[scfs_cross_product_support_graph]`
- `[scfs_support_embed product="decision-studio"]`
- `[scfs_help_desk_customer_portal]`

## Repository layout

- `wordpress/` — WordPress plugin source
- `backend/` — deterministic FastAPI support intelligence service
- `schemas/` — versioned contracts
- `examples/` — synthetic public examples
- `tests/` — WordPress contract tests
- `docs/` — implementation and governance guides


## v6.3.0

Adds secure requester access links, token-to-session exchange, participant-visible conversations, requester replies, bounded resolution and reopening actions, private satisfaction feedback, portal REST contracts, and Contact and Engagement notification handoffs.

## v6.2.0

Adds the private Agent Workspace, built-in and team queues, explicit assignment, workload summaries, saved views, bulk operations, and a complete private case workspace.
