# Product Support and Feedback Platform v6.1.0

## Help Desk Case Foundation

v6.1.0 introduces the private case-record architecture needed to evolve the Product Support and Feedback Platform into a full help desk.

### Added

- Eight additive private help-desk tables.
- Human-readable `SC-YYYY-000001` case numbers.
- Case types, priorities, severities, statuses, privacy classifications, and consent states.
- Validated status transitions.
- Participants and requester references.
- Threaded requester messages, support replies, system notes, and internal notes.
- Current and historical assignments.
- Relationships to Support Articles, Known Issues, releases, suggestions, documentation gaps, and other cases.
- Contact and Engagement attachment metadata references.
- SLA event foundation.
- Append-only case audit events with SHA-256 integrity hashes.
- Help Desk Cases administration screen.
- Authenticated WordPress REST routes.
- Deterministic FastAPI case validation.
- WP-CLI case operations.
- Schema and package validation contracts.

### Preserved

- Existing plugin and repository slug.
- Existing `scfs_*` functions, options, hooks, shortcodes, and REST namespace.
- Existing public Support Center and Knowledge Base behavior.
- Existing Support Article, Known Issue, release, suggestion, and documentation-gap records.
- Existing Support Article URLs under `/support/guides/`.
- Contact and Engagement authority for identity, consent, and secure files.

### Privacy boundary

No public case API or public case shortcode is introduced. Private case records are accessible only through capability-protected administration and REST operations. Uploaded file bytes remain outside the WordPress Media Library.
