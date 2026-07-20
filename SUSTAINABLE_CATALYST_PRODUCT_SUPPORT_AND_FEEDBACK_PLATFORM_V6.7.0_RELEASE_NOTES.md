# Sustainable Catalyst Product Support and Feedback Platform v6.7.0

**Release:** Workflow Automation and Operational Rules
**Compatibility:** Additive, migration-safe, and backward compatible

v6.7.0 turns repeated support procedures into governed operational rules. Each rule has a trigger, conditions, ordered actions, execution mode, and priority. Every evaluation receives a source fingerprint. Every proposed action is risk classified and retains an integrity hash. Actions that change authoritative case state or communicate externally require explicit authorization.

## Automation authority

The system may automatically schedule private reminders, create review tasks, or prepare internal notes when a rule explicitly uses the approved-low-risk mode. It never automatically sends a customer reply, closes or resolves a case, changes priority, assigns an agent, or calls an external webhook.

## Response templates and macros

Templates use variable allowlists and customer-safe designations. Customer content renders only as a draft. Macros expand into individually governed actions and cannot bypass approval policy.

## Follow-ups

Follow-ups retain due time, state, case, responsible agent or team, and audit context. The hourly processor marks work due and emits an internal action hook; it does not contact a customer directly.

## Compatibility

The release preserves all existing support records, private cases, assignments, conversations, service-level clocks, evidence records, recommendations, routes, shortcodes, and identifiers.
