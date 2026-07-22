# Discovery and Compatibility Patch — v7.2.1

## Purpose

v7.2.1 hardens Installed Plugin Discovery before the public release-board shortcode is introduced. The patch keeps the canonical product registry aligned with installed WordPress plugins while preventing ambiguous, malformed, or legacy metadata from changing public release information unexpectedly.

## Deterministic duplicate handling

A product can be matched by more than one installed plugin when a folder was renamed, an old copy was left inactive, or two plugins declare the same product identifier. Discovery now collects every candidate and selects one winner in a stable order:

1. Higher match confidence.
2. Active before inactive.
3. Valid stable version before development, missing, or malformed versions.
4. Alphabetical plugin file as the final deterministic tie-breaker.

Every nonselected candidate is placed in the private review queue with the selected plugin file recorded. Scan order no longer determines the result.

## Legacy identifiers

Each canonical product can declare controlled compatibility identifiers:

- `legacy_plugin_files`
- `legacy_plugin_slugs`
- `legacy_text_domains`

Legacy identifiers can match an approved registry product, but the match is recorded explicitly and a diagnostic notice is generated. The canonical public product name remains unchanged when an installed plugin uses an older display name.

## Version normalization

Discovery normalizes standard WordPress plugin versions without inventing values. A leading `v` is removed and stable or prerelease versions such as `7.2.1`, `3.1.0-beta.1`, and `0.24.0-dev.2` are accepted.

Version states are:

- `valid`
- `development`
- `missing`
- `malformed`

Missing or malformed versions remain visible in private discovery evidence but do not overwrite installed or public release versions.

## Manual override preservation

Discovery continues to refresh evidence even when a product is locked. For unlocked products:

- `installed_version` updates only from valid or development versions.
- `public_version` updates only when empty or still equal to the previously discovered installed version.
- Status changes automatically only when the existing status is `unverified`, `current`, or `inactive`.

Manual statuses such as `maintenance`, `preview`, `private_beta`, `release_candidate`, `deprecated`, or `unavailable` are preserved.

## Multisite activation scope

Discovery distinguishes:

- `inactive`
- `site`
- `network`
- `both`

This prevents a network-active plugin from being reported as merely active without its multisite context.

## Diagnostics

The administrator Plugin Discovery screen now reports errors, warnings, and informational notices for:

- Duplicate product matches.
- Alias collisions.
- Missing plugin names.
- Missing or malformed versions.
- Development versions.
- Legacy identifier matches.
- Installed display names that differ from canonical product names.

Diagnostics are also available through:

- `GET /wp-json/scfs/v1/product-registry/discovery/diagnostics`
- `wp scfs products discovery-diagnostics`

Unknown, malformed, and duplicate plugins remain private and never publish themselves to the release board.

## Compatibility

The WordPress plugin folder, text domain, shortcodes, REST namespace, post types, taxonomies, and existing option keys remain unchanged. The patch adds only compatible registry fields and one diagnostics option. Catalyst Intelligence Platform remains manually maintained.
