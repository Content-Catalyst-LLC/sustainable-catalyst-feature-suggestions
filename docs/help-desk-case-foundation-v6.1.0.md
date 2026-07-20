# Help Desk Case Foundation v6.1.0

## Purpose

v6.1.0 introduces the private record architecture required to evolve the Sustainable Catalyst Product Support and Feedback Platform into a full help desk. It does not turn public Support Articles, Known Issues, releases, documentation gaps, or feature suggestions into tickets.

The public Support Center remains the public guidance layer. Private cases are stored in dedicated help-desk tables and are visible only to authorized WordPress users.

## Authority boundaries

The Help Desk Case Foundation stores private case operations while preserving two existing authorities:

- **Contact and Engagement Platform:** requester identity, consent, private correspondence origin, and secure uploaded-file authority.
- **Product Support and Feedback Platform:** public Support Articles, Known Issues, releases, feature suggestions, documentation gaps, product metadata, and public support context.

Cases reference Contact and Engagement records and public support records by controlled identifiers. They do not copy contact records into public taxonomies, publish private messages, or move uploaded files into the WordPress Media Library.

## Private tables

Activation creates eight additive tables:

1. `wp_scfs_cases`
2. `wp_scfs_case_participants`
3. `wp_scfs_case_messages`
4. `wp_scfs_case_events`
5. `wp_scfs_case_assignments`
6. `wp_scfs_case_relationships`
7. `wp_scfs_case_attachments`
8. `wp_scfs_case_sla_events`

The prefix follows the active WordPress database prefix.

## Case identity

Cases use a human-readable case number:

```text
SC-YYYY-000001
```

The internal numeric ID remains the database key. The human-readable number is unique and can be used in agent communication, email threading, and future customer-portal links.

## Workflow

Statuses:

```text
New
Open
Waiting for Support
Waiting for Requester
Escalated
Resolved
Closed
Duplicate
Cancelled
```

The transition map prevents unsupported state changes. Every transition creates an audit event. Resolving, closing, reopening, duplicate designation, and cancellation remain human-controlled actions.

## Messages and notes

Messages are stored in a dedicated case-message table.

- `participants` visibility is intended for requester-visible correspondence.
- `internal` visibility is reserved for staff notes.
- Internal notes are never returned by a future requester-facing portal without an explicit separate permission layer.

## Assignments

The foundation records current and historical assignments to:

- WordPress users
- Named support teams
- Assignment reasons
- Assignment start and end timestamps

v6.1.0 does not yet implement workload balancing or automatic assignment. Those belong to the Agent Workspace release.

## Relationships

Cases may reference:

- Support Articles
- Known Issues
- Release records
- Feature suggestions
- Documentation gaps
- Parent cases
- Duplicate cases
- Related cases
- Product handoffs

A relationship stores only the public record identifier and public context needed for support. Private case narrative must not be copied into public records.

## Attachments

The Help Desk stores attachment **metadata only**:

- Contact and Engagement attachment reference
- Filename
- MIME type
- Size
- SHA-256 digest
- Privacy classification
- Retention date
- Redaction state

Private file bytes remain under the Contact and Engagement Platform’s secure storage and download controls.

## Permissions

The release adds:

- `scfs_view_help_desk`
- `scfs_reply_help_desk`
- `scfs_manage_help_desk`

Administrators receive all three capabilities. Editors receive view and reply capabilities by default. Sites may change this through normal WordPress role management.

## WordPress REST API

All routes require an authenticated capability check:

```text
GET  /wp-json/scfs/v1/help-desk/schema
GET  /wp-json/scfs/v1/help-desk/cases
POST /wp-json/scfs/v1/help-desk/cases
GET  /wp-json/scfs/v1/help-desk/cases/{id}
POST /wp-json/scfs/v1/help-desk/cases/{id}/transition
POST /wp-json/scfs/v1/help-desk/cases/{id}/messages
POST /wp-json/scfs/v1/help-desk/cases/{id}/assignments
POST /wp-json/scfs/v1/help-desk/cases/{id}/relationships
POST /wp-json/scfs/v1/help-desk/cases/{id}/attachments
GET  /wp-json/scfs/v1/help-desk/integrity
```

There is no public case route and no public case shortcode.

## FastAPI deterministic contracts

```text
GET  /v1/help-desk/capabilities
POST /v1/help-desk/cases/validate
POST /v1/help-desk/case-numbers/generate
POST /v1/help-desk/transitions/evaluate
POST /v1/help-desk/relationships/evaluate
POST /v1/help-desk/privacy/evaluate
POST /v1/help-desk/reports/verify
```

These endpoints validate contracts. WordPress remains the source of truth for stored private cases.

## WP-CLI

```bash
wp scfs help-desk schema

wp scfs help-desk create \
  --subject="Decision brief export is unavailable" \
  --description="PDF export returns an error." \
  --requester-ref="contact-engagement:inquiry-184" \
  --product=decision-studio \
  --version=2.0.1 \
  --component=briefing \
  --type=unexpected_behavior \
  --priority=p2_high \
  --severity=major \
  --consent=recorded

wp scfs help-desk case SC-2026-000184

wp scfs help-desk transition SC-2026-000184 resolved \
  --reason="Verified recovery steps completed."

wp scfs help-desk integrity
```

## Governance

v6.1.0 does not automatically create cases from searches, feature suggestions, emails, Contact and Engagement inquiries, or public feedback. It also does not automatically resolve cases, declare incidents, publish documentation, or change roadmap status.

Those operations require authenticated human action or a future separately governed workflow.
