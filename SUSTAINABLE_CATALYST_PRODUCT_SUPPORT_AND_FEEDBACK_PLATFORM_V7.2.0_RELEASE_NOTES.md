# Sustainable Catalyst Product Support and Feedback Platform v7.2.0

## Installed Plugin Discovery

v7.2.0 adds a safe, maintainable discovery layer between installed WordPress plugins and the canonical product registry.

### Added

- Approved-product discovery from WordPress plugin metadata.
- Exact file, `SC Product ID`, plugin slug, text domain, and approved-name matching.
- Active and inactive plugin-state detection.
- Installed-version reconciliation with public-version override protection.
- Product-level discovery enable and lock controls.
- Cached snapshots and automatic refresh after plugin lifecycle changes.
- Administrator rescan and private candidate-review screens.
- Authenticated REST and WP-CLI discovery operations.
- Discovery schema, synthetic example, backend validator, and release contracts.

### Governance

Unknown plugins are not registered or published automatically. Private plugin candidates never appear in the public product registry or future homepage board. Manual products such as Catalyst Intelligence remain outside WordPress discovery.

### Compatibility

This is an additive release. It preserves the existing WordPress plugin folder, text domain, shortcodes, REST namespace, post types, options, product taxonomies, public routes, and canonical GitHub repository. No destructive database migration is required.

### Packaging repair R1

The repaired release package corrects twelve inherited PHP contract assertions that still expected the v7.1.0 release name, `Canonical Product Registry`, after the v7.2.0 canonical manifest was updated to `Installed Plugin Discovery`. Product code, plugin runtime behavior, database compatibility, and public interfaces are unchanged.
