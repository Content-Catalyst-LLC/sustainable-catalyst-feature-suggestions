# Product Support and Feedback Platform v6.10.0

## Institutional Workspaces and Access Governance

v6.10.0 introduces private organizational workspaces, department boundaries, hashed external member references, support entitlements, product and version coverage, explicit case access, private knowledge collections, append-only audit evidence, retention controls, and privacy-safe institutional reporting.

### Added

- Ten additive private institutional tables.
- Workspace, department, membership, entitlement, product-coverage, case-access, collection, audit, and report operations.
- Scoped institutional capabilities and roles.
- Agent Workspace institutional-access panel.
- Authenticated WordPress REST operations and WP-CLI commands.
- Deterministic FastAPI parity and SHA-256 report verification.
- Minimum-cohort suppression and strict private-data boundaries.

### Governance

Contact and Engagement remains authoritative for identity, consent, contact details, secure files, and delivery. No requester identity, private correspondence, or attachment content is copied into institutional workspace records. Case sharing, access grants, entitlement changes, publication, institutional branding, and sponsorship influence are never automatic.

### Compatibility

The release is additive. Existing Support Center content, cases, portal sessions, service clocks, evidence, knowledge-resolution records, workflows, channels, analytics, and integrations remain intact.


# Help Desk Institutional Workspaces and Access Governance v6.10.0

## Purpose

v6.10.0 adds private institutional support workspaces without converting Sustainable Catalyst into a sponsor-driven or publicly branded customer portal. The release separates organizational access governance from public Support Center content and from private identity and storage systems.

## Authority boundaries

The Product Support and Feedback Platform owns workspace policy, entitlements, case-access grants, collections, audit evidence, and privacy-safe reports. Contact and Engagement remains authoritative for requester identity, contact details, consent, email delivery, secure uploads, and secure downloads.

## Private data model

Ten additive tables represent institutions, departments, hashed member references, entitlements, product coverage, explicit case access, private knowledge collections, collection items, append-only audit events, and cohort-governed reports. Existing public and private records are not migrated.

## Roles

- `workspace_admin`: governed workspace administration.
- `support_manager`: operational support coordination.
- `requester`: own-case access and case creation within entitlement.
- `auditor`: approved read-only audit and reporting access.
- `observer`: narrowly scoped visibility without operational authority.

Capabilities are explicit and least-privilege. A role name alone never grants a case.

## Case visibility

Institutional case visibility requires an explicit, auditable grant. Security and privacy cases receive stricter review. Automatic cross-department sharing and automatic institution-wide visibility are disabled.

## Entitlements

Entitlements connect a workspace to covered products, versions, components, service policies, and optional case-volume limits. The platform evaluates entitlement state but does not modify an entitlement automatically.

## Private knowledge collections

Collections may reference public Support Articles, Known Issues, releases, and approved institutional guidance. They cannot copy private case messages, requester identity, or attachment content.

## Reporting

Reports use minimum-cohort suppression, exclude requester identity and correspondence, and carry a SHA-256 integrity hash. Reports are private by default and do not create public institutional branding.

## Independence boundary

Workspace access, service relationships, and entitlements do not influence editorial conclusions, product roadmaps, public research, or support recommendations. Sponsor influence and automatic publication are disabled by contract.
