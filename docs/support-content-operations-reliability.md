# Support Content Operations Reliability — v4.1.1

Version 4.1.1 hardens the v4.1.0 product onboarding and content-import workflow without changing the public Support Center contract.

## Import inspection

Supported JSON, Markdown, and text sources are checked before records are created. Inspection rejects empty files, files larger than 2 MB, unsupported extensions, invalid UTF-8, null bytes, malformed JSON, non-object JSON records, and imports with more than 500 records. JSON parse errors include the native parser reason when available.

Each parsed source receives a SHA-256 checksum. Imported records retain the batch identifier, source fingerprint, record index, normalized title, and per-record integrity hash.

## Recoverable batches

Successful imports retain a time-limited rollback entry. Administrators can move every record created by a batch to WordPress Trash from **Feature Suggestions → Content Operations**. Rollback never permanently deletes content.

When strict validation is enabled, any failed record causes the records created earlier in that batch to be moved to Trash automatically. The operation log distinguishes completed, rolled-back, partial-rollback, and expired batches.

## Duplicate and starter reliability

Duplicate detection now checks:

- exact content fingerprints;
- the same source reference and normalized title;
- equivalent normalized titles within the selected product.

Starter generation still never overwrites an existing record. It can adopt an equivalent existing draft and restore a matching starter from Trash instead of creating a duplicate.

## Product-context integrity

Validation checks that product-version and component terms belong to at least one product assigned to the record. It also reports unreadable taxonomy assignments, fingerprint drift, import-integrity mismatches, stale records, lifecycle mismatches, invalid supersession, missing product context, and duplicate content.

## Scheduled reliability sweep

A daily WordPress cron task runs content validation and expires old rollback entries. The Content Operations screen and protected health endpoint expose the next run, last completion, duration, records scanned, issues found, expired rollback batches, lock state, and overdue state.

## Export integrity

Product exports use deterministic record ordering and include:

- record count;
- SHA-256 checksum of the canonical records array;
- checksum algorithm;
- stable-ordering declaration;
- explicit confirmation that private case content is excluded.

The download action validates the payload before sending it and adds a checksum response header.

## Permissions and accessibility

Content Operations uses an administrator capability boundary by default:

- `manage_options` on normal sites;
- `manage_network_options` in network administration;
- `scfs_support_content_operations_capability` filter for controlled overrides.

The administration screen adds focus-visible treatment, reduced-motion support, live operation announcements, labeled progress state, keyboard-scrollable tables, scoped fieldsets, and confirmation before rollback.

## Governance boundaries

- Imported content is never published automatically.
- Private Contact and Engagement case content is never imported.
- Rollback moves records to Trash rather than permanently deleting them.
- Readiness, validation, recovery, and duplicate signals require human review.
