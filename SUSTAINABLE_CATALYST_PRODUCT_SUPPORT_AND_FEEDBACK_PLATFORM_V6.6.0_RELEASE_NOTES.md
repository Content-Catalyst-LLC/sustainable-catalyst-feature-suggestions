# Product Support and Feedback Platform v6.6.0

## Knowledge-Assisted Case Resolution

v6.6.0 adds deterministic, privacy-minimized case guidance across Support Articles, Known Issues, release records, similar resolved cases, duplicate review, guided plans, and documentation promotion.

### Added

- Private recommendation runs and recommendation records
- Privacy-safe case signatures and similar-case matching
- Article, Known Issue, and release ranking
- Duplicate-case review suggestions without automatic merging
- Agent approval, rejection, requester-send, and internal-apply decisions
- Customer Portal exposure limited to approved public guidance
- Documentation Gap and editorial promotion requests
- Append-only recommendation action history
- WordPress REST, WP-CLI, FastAPI parity, schema, examples, and validation

### Governance

No recommendation is sent, merged, published, or applied automatically. Requester identity, private messages, and attachment contents are excluded from recommendation records.

## Compatibility

The release is additive. Existing cases, assignments, portal sessions, service clocks, evidence records, Support Articles, Known Issues, releases, suggestions, analytics, routes, shortcodes, and identifiers remain unchanged.

## Additive tables

- `scfs_help_desk_resolution_runs`
- `scfs_help_desk_resolution_recommendations`
- `scfs_help_desk_resolution_actions`
- `scfs_help_desk_resolution_promotions`
- `scfs_help_desk_resolution_signatures`
