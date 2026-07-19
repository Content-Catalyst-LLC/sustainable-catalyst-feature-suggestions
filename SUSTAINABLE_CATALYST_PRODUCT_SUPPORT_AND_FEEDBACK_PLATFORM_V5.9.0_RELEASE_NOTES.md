# Sustainable Catalyst Product Support and Feedback Platform v5.9.0

## Public API, Embeds, and Institutional Support Integration

v5.9.0 exposes the public support system through stable, privacy-safe integration contracts while preserving the existing WordPress plugin, data model, URLs, shortcodes, and REST namespace.

### Added

- Versioned public product-support API contracts.
- Product catalog, product record, search, version-verification, and embed-plan routes.
- Responsive `[scfs_support_embed]` shortcode with legacy alias support.
- Product, version, component, and view-scoped embeds.
- Institutional contract registry and health reporting.
- Optional public API-key enforcement and origin allowlists.
- Read-only access governance, cache metadata, ETags, and contract headers.
- FastAPI version verification, embed planning, contract validation, and integration health parity.
- WP-CLI contract, version, embed, and health commands.

### Compatibility

No database migration is required. Existing `scfs_*` identifiers, CPTs, REST namespace, Support Article URLs, shortcodes, options, taxonomies, and stored records remain unchanged.

### Privacy boundary

Only public product and support records are exposed. Requester identity, private cases, correspondence, contact records, and uploaded private documents remain outside all public and institutional contracts.
