# Sustainable Catalyst Product Support and Feedback Platform v6.4.0 — Service Levels, Escalation, and Response Governance

v6.4.0 introduces the service-management foundation required for a dependable help desk. It connects the private case record to governed response targets without turning those targets into automatic promises or removing agent judgment.

## Added

- Private service policy registry
- Versioned support calendars and holiday exclusions
- First-response, next-response, and resolution clocks
- Business-time deadline calculation
- Warning thresholds and breach evaluation
- Waiting-requester pause accounting
- Append-only SLA and escalation evidence
- Agent case-panel timing view
- Optional requester timing panel, disabled by default
- Human acknowledgement of escalations
- Protected REST routes and WP-CLI commands
- FastAPI validation and report-integrity contracts

## Not automated

The platform does not automatically change priority, assign or reassign cases, send customer notifications, resolve or close cases, or create contractual commitments. Escalation creates a review obligation, not an autonomous decision.

## Storage

Five additive private tables store policies, calendars, clocks, escalation rules, and escalation events. Existing v6.1.0–v6.3.0 tables are preserved.
