# Help Desk Workflow Automation and Operational Rules v6.7.0

v6.7.0 adds a governed automation layer above the private case foundation. Rules react to case events, evaluate bounded conditions, and create auditable action plans. Low-risk actions may schedule reminders or prepare internal review material. Mutating and customer-facing actions require explicit authorization.

## Architecture

The implementation adds seven private tables for rules, runs, actions, templates, macros, approvals, and follow-ups. `scfs_help_desk_case_event_recorded` provides the event boundary. Each run receives a SHA-256 source fingerprint, each action receives an integrity hash, and each approval is append-only evidence.

## Safety model

Automation does not send customer replies, close or resolve cases, change priority, assign agents, or call external webhooks. It may prepare a customer-safe draft, but the existing agent conversation workflow remains authoritative for sending it. Closing and resolving remain authoritative case transitions.

## Default rules

- New case routing review recommends a product queue and schedules initial review.
- Requester reply reactivation review recommends restoring an active support state.
- SLA warning review creates a private follow-up task.

## Templates and macros

Templates use an explicit variable allowlist. Customer-facing templates require a `customer_safe` designation and always render as drafts. Macros expand into individually governed actions rather than bypassing approvals.

## Integration

The Agent Workspace displays recent workflow runs, proposed actions, and follow-ups. REST and WP-CLI operations support health inspection, manual evaluation, approvals, and scheduled processing. FastAPI endpoints provide deterministic planning and validation parity.
