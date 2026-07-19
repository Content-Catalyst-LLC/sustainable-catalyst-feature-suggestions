# Release Notes — v5.8.0

## Cross-Product Support Graph and Platform Handoffs

Sustainable Catalyst Product Support and Feedback Platform v5.8.0 adds a governed product-support graph across the Sustainable Catalyst ecosystem.

### Added

- Canonical product nodes for the current Sustainable Catalyst product ecosystem.
- Product, version, component, capability, documentation, Known Issue, release, example, and troubleshooting context.
- Default platform handoff relationships and compatibility with existing cross-product dependency records.
- Deterministic product-support coverage scoring.
- Related-product handoff planning with transparent ranking reasons.
- Shortest support-path calculation.
- Graph integrity checks for duplicate nodes, self-edges, unknown products, missing routes, and missing capabilities.
- Public shortcode `[scfs_cross_product_support_graph]`.
- Compatibility alias `[scfs_platform_support_graph]`.
- Support & Feedback → Support Graph administration screen.
- WordPress REST, FastAPI, and WP-CLI interfaces.
- Daily cached graph snapshots and exportable JSON reports.

### Compatibility

The release preserves all existing `scfs_*` functions, settings, shortcodes, REST routes, database structures, CPTs, taxonomies, Support Article URLs, Known Issues, release records, suggestions, feedback, surveys, and analytics. No database migration is required.

### Privacy and governance

The graph uses public support records only. It does not expose requester identities, raw search text, private correspondence, uploaded documents, or private case content. It cannot redirect users automatically, create private cases, resolve issues, change releases, or alter the roadmap.
