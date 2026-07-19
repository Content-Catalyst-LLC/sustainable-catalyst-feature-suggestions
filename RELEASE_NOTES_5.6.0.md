# Sustainable Catalyst Product Support and Feedback Platform v5.6.0

## Feedback Intelligence and Product Signals

Version 5.6.0 introduces an administrator-only product-intelligence layer that combines privacy-minimized support and feedback evidence without changing existing public records or workflows.

### Added

- Product Signals administration dashboard
- Deterministic product signal scoring
- Feature request and public vote demand summaries
- Support Article helpfulness and negative-feedback signals
- Unresolved and low-confidence Guided Resolution signals
- Failed public resolution-path counts
- Documentation Gap demand and priority signals
- Active and critical Known Issue demand
- Privacy-safe support relationship counts
- Evidence cluster prioritization
- Product-level recommended human review actions
- Cached and scheduled daily snapshots
- CSV export
- Administrator-authorized WordPress REST routes
- FastAPI scoring, portfolio, and cluster-priority parity
- WP-CLI refresh, summary, and product commands
- Versioned `scfs-feedback-product-signals/1.0` contract

### Privacy and governance

The release does not expose contact details, requester identity, private case text, uploaded documents, or raw search text. Signals cannot automatically change roadmap, issue, release, or publication state.

### Compatibility

No database migration is required. Existing `scfs_*` identifiers, options, post types, taxonomies, shortcodes, REST namespace, Support Article URLs, Known Issues, releases, suggestions, votes, surveys, and relationships are preserved.
