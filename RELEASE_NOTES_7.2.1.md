# Sustainable Catalyst Product Support and Feedback Platform v7.2.1

## Discovery and Compatibility Patch

v7.2.1 hardens installed-plugin discovery before the homepage release blackboard is introduced.

### Added

- Deterministic duplicate-match selection independent of WordPress scan order.
- Legacy plugin file, folder-slug, and text-domain compatibility identifiers.
- Stable and prerelease version normalization.
- Missing and malformed plugin-header quarantine.
- Site, network, both, and inactive activation scopes for multisite.
- Discovery diagnostics in WordPress administration, REST, and WP-CLI.
- Canonical registry deduplication using `canonical_id` as the authority.
- Tests for inactive, duplicate, renamed, missing-version, development-version, and multisite cases.

### Corrected

- Manual statuses such as maintenance, preview, and deprecated are no longer overwritten by routine discovery.
- Duplicate candidates now identify the selected canonical plugin.
- Renamed plugin display names no longer affect the canonical public product name.
- The duplicate `permission_callback` declaration in the discovery REST route was removed.

### Compatibility

- No destructive database migration.
- Existing product records are merged and retained.
- Manual Catalyst Intelligence version management is preserved.
- The WordPress plugin directory remains `sustainable-catalyst-feature-suggestions`.
- The WordPress text domain, shortcodes, post types, REST namespace, and public routes remain unchanged.
