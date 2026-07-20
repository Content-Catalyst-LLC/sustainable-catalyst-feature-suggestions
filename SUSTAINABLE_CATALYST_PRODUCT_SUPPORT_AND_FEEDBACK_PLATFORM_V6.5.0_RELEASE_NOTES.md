# Product Support and Feedback Platform v6.5.0

## Secure Evidence, Attachments, and Diagnostic Intake

v6.5.0 adds a private evidence-governance layer to the help desk. It creates evidence-intake records, delegated attachment registration, diagnostic bundle manifests, hash-only access grants, redaction state, retention review, and append-only evidence events.

Private file bytes remain outside the WordPress Media Library. Contact and Engagement remains the authority for secure file storage, delivery, consent, and deletion execution. The help desk stores only the metadata and governance evidence required to manage a case.

### Additive tables

- `scfs_help_desk_evidence_intakes`
- `scfs_help_desk_diagnostic_bundles`
- `scfs_help_desk_attachment_access`
- `scfs_help_desk_attachment_events`
- `scfs_help_desk_retention_actions`

### Governance

- Executable and active-content file extensions are blocked.
- MIME type, size, SHA-256, consent, scan state, classification, retention, and redaction state are validated.
- Diagnostic manifests reject credential-like fields and production-data declarations.
- Access grants store only SHA-256 hashes and never raw download URLs.
- Deletion, redaction, extension, and legal-hold actions require human review.
- Existing cases and public support records are unchanged.

## Integration contract

WordPress provides private REST and WP-CLI operations. FastAPI provides deterministic validation for intake, metadata, diagnostic manifests, access grants, retention actions, and report integrity. The `scfs_help_desk_evidence_intake_requested`, `scfs_help_desk_evidence_redaction_changed`, and `scfs_help_desk_evidence_retention_review_due` hooks allow Contact and Engagement to perform the corresponding secure file operation without duplicating private files.
