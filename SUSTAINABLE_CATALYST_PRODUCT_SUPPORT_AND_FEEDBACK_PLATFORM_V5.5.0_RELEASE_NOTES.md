# Sustainable Catalyst Product Support and Feedback Platform

## v5.5.0 — Support Content Operations and Editorial Governance

This release turns the existing documentation, issue, release, content-integrity, and editorial-workflow capabilities into a coordinated operating system for maintaining public support content over time.

### Content ownership

Every Support Article, Known Issue, and Release Record can have a content owner and technical owner. Ownership is visible in the editor, the operational queue, the administrative REST API, CSV exports, and WP-CLI output.

### Verification and review cadence

Records can be marked not verified, review required, verified, verified with limitations, or superseded. Verification records the date, next review deadline, note, actor, and content hash. Review cadence can be configured per record.

### Operational queue

The Content Operations screen brings together editorial workflow state, article-integrity results, verification status, ownership, review dates, priority, and supersession. It sorts records into actionable queues without changing publication status automatically.

### Editorial safeguards

Verification is blocked when required ownership is missing, an editorial workflow is in a blocked state, or a Support Article does not meet the configured integrity threshold. Bulk verification requires a human with the configured publication capability and a verification note.

### Supersession and historical continuity

Content can be superseded without deleting the old record or breaking its URL. Reverse relationships and lifecycle metadata are maintained so readers, editors, and integrations can trace the current replacement.

### APIs and automation

The release adds protected WordPress REST endpoints, WP-CLI commands, a daily scan, optional overdue summaries, and deterministic FastAPI parity. WordPress remains the authoritative system of record.

### Compatibility

The plugin slug, text domain, PHP class, all `scfs_*` identifiers, CPTs, taxonomies, public URLs, settings, existing metadata, and historical records are preserved. No database migration is required.
