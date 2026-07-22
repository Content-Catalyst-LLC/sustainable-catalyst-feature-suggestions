# Canonical Product Registry v7.4.0

The v7.4.0 registry is the authoritative source for public product identity and Release Console placement.

Each product record now governs:

- immutable `canonical_id`
- public `name` and `short_name`
- private `internal_name` and `repository_slug`
- `family` and independent `console_screen`
- `version_source` and `version_precedence`
- `release_channel`, operational `status`, and `lifecycle_state`
- optional `superseded_by` relationships
- public and homepage visibility
- verification provenance and timestamps

Private repository identity and plugin-path fields are excluded from public Release Console projections. Administrators can validate integrity, run the additive schema migration, export governed records, and inspect migration history from WordPress, REST, or WP-CLI.
