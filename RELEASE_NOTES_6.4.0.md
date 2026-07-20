# Product Support and Feedback Platform v6.4.0

## Service Levels, Escalation, and Response Governance

This release adds configurable service policies, business-hour support calendars, first-response and resolution clocks, pause accounting, warning and breach evaluation, append-only escalation records, private agent visibility, optional customer target visibility, REST contracts, WP-CLI operations, and FastAPI parity.

### Governance boundaries

- Default targets are internal operating targets, not contractual promises.
- Waiting for requester is the default clock pause state.
- Priority, assignment, customer notification, resolution, and closure remain human-controlled.
- No public case-list API is introduced.
- Contact and Engagement remains authoritative for identity, notification delivery, and secure attachments.

### Compatibility

The release is additive. Existing public support content, private cases, agent queues, portal sessions, messages, assignments, and customer feedback remain unchanged. Activation creates five private service-management tables and seeds one internal policy, one support calendar, and review-only escalation rules.
