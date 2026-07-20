# Help Desk Service Levels, Escalation, and Response Governance — v6.4.0

v6.4.0 adds an internal service-management layer above the private case foundation, agent workspace, and customer portal.

## Operating model

A service policy maps each case priority to first-response, next-response, and resolution targets. Targets are calculated against a versioned support calendar rather than raw elapsed wall-clock time. The default calendar uses America/Chicago and Monday–Friday business hours.

The default values are internal operating targets. They do not create a contractual commitment. Contractual service levels require a separate institutional agreement and explicit human approval.

## Clocks

Each case may have first-response, next-response, and resolution clocks. Clock states are running, warning, paused, breached, completed, or cancelled. The system records the target, warning threshold, accumulated paused seconds, and each transition in append-only case evidence.

Waiting for requester information is the default pause state. Pausing outside that state requires a reason and review. Resuming shifts the due and warning times by the accumulated pause duration.

## Escalation

Warning and breach states create private escalation evidence. Escalation recommends review, assignment confirmation, breach-reason documentation, and acknowledgement. It does not automatically change case priority, assignment, customer communication, case resolution, or closure.

## Privacy

Service-level records contain operational timing and identifiers. They do not expose requester identity, private messages, attachment bytes, access tokens, or public case lists. Contact and Engagement remains authoritative for identity, notifications, and secure attachments.

## Interfaces

WordPress administration provides policy, calendar, clock, health, and case-panel views. Private REST and WP-CLI interfaces support governed evaluation. The FastAPI service mirrors policy, calendar, clock, transition, escalation, and report-integrity contracts.
