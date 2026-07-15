# Feature Suggestions v4.1.1 — Content Operations Reliability Patch

Feature Suggestions v4.1.1 stabilizes the v4.1.0 product-onboarding and support-content workflow. It focuses on safe recovery, import validation, duplicate control, scheduled-task visibility, export integrity, accessibility, and administrator permissions.

## Import recovery

- Adds time-limited rollback records for completed imports.
- Adds a **Roll back batch** action to move all records from an import to WordPress Trash.
- Adds automatic whole-batch rollback when strict validation detects a failed record.
- Records completed, rolled-back, partial-rollback, and expired batch states.
- Adds REST and WP-CLI rollback operations.

## Malformed-source handling

- Rejects empty and oversized files.
- Rejects invalid UTF-8 and null bytes.
- Removes UTF-8 byte-order marks before parsing.
- Returns detailed JSON parse errors.
- Enforces object-shaped JSON records and a 500-record hard limit.
- Produces a SHA-256 checksum for each imported source.

## Duplicate and starter reliability

- Retains exact fingerprint duplicate detection.
- Adds same-source and normalized-title matching.
- Adds equivalent-title detection within product context.
- Restores matching starter records from Trash.
- Adopts equivalent existing starter drafts rather than duplicating them.

## Product and record integrity

- Validates product-version and component relationships against assigned products.
- Detects unreadable taxonomy assignments.
- Detects fingerprint drift and import-integrity mismatches.
- Retains stale-content, lifecycle, supersession, and missing-context validation.

## Operations and scheduled tasks

- Adds operation job records and visible progress state.
- Adds a daily content-integrity sweep with an execution lock.
- Exposes next-run, last-run, duration, issue totals, and overdue state.
- Expires rollback availability according to the configured retention window.

## Export integrity

- Sorts exported records deterministically.
- Adds record count and SHA-256 checksum metadata.
- Validates the export before download.
- Adds an export checksum response header.
- Continues to exclude private Contact and Engagement case content.

## Permissions and accessibility

- Changes Content Operations to an administrator capability boundary by default.
- Supports `manage_network_options` in network administration.
- Adds a capability filter for controlled delegation.
- Adds focus-visible styles, reduced-motion support, live announcements, accessible progress, keyboard-scrollable tables, and rollback confirmation.

## Backend additions

- `POST /v1/support-content/import/inspect`
- `POST /v1/support-content/import/recovery`
- `POST /v1/support-content/export/verify`

All recovery and readiness outputs remain advisory and require human review. Imported records are never published automatically.
