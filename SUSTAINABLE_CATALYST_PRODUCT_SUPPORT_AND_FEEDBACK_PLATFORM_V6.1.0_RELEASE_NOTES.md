# Sustainable Catalyst Product Support and Feedback Platform v6.1.0

**Release:** Help Desk Case Foundation
**Version:** 6.1.0
**Schema:** `scfs-help-desk-case/1.0`
**Database schema:** `1.0.0`

v6.1.0 is the first help-desk operations release. It creates the private record, workflow, permission, audit, and integration foundation for later Agent Workspace, Customer Portal, SLA, secure evidence, automation, email, analytics, and institutional help-desk releases.

## Architectural decision

Public content remains public content. Private cases use dedicated tables and do not reuse WordPress posts or public taxonomies as ticket records.

The Contact and Engagement Platform remains authoritative for requester identity, consent, private intake context, and secure uploaded files. The Help Desk stores controlled references to those records.

## Release scope

- Private case storage
- Case numbers and workflow
- Participants and messages
- Internal notes
- Assignments
- Relationships
- External attachment metadata
- SLA events
- Audit history
- Admin operations
- Authenticated APIs
- Deterministic backend contracts
- CLI operations
- Additive schema activation
- Backward compatibility validation

## Out of scope

- Customer portal
- Email ingestion
- Automatic assignment
- Service-level policies
- File-byte storage
- Agent macros
- Workflow automation
- Institutional tenancy
- Public ticket tracking

These are intentionally deferred to later builds.
