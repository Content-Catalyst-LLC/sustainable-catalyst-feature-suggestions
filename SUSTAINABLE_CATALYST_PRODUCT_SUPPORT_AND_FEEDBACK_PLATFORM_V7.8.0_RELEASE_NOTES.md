# Sustainable Catalyst Product Support and Feedback Platform v7.8.0

## GitHub Release Intelligence

v7.8.0 turns the existing GitHub connection into a governed release-intelligence layer for every canonical product.

### Release authority

- Keeps published stable GitHub Releases as the highest GitHub version authority.
- Allows published prereleases only when an administrator explicitly enables them globally or for a selected product.
- Excludes draft releases from console version authority.
- Falls back to the newest valid semantic Git tag when no eligible GitHub Release exists.
- Keeps commits as repository activity evidence rather than presenting every push as a product release.

### Repository and release evidence

- Synchronizes repository visibility, default branch, archive and disabled state, fork evidence, and repository update time.
- Detects repository renames or transfers and updates the canonical GitHub URL while retaining configured-versus-resolved evidence.
- Captures release name, author, publication time, tag, URL, and release asset inventory.
- Records latest commit SHA and activity separately from governed release versions.

### Operations and diagnostics

- Adds GitHub API rate-limit visibility, reset timing, OAuth scope evidence, and organization/SSO approval hints.
- Adds per-product synchronization history and failed-synchronization history.
- Adds retry-failed-only controls and configurable hourly, twice-daily, daily, or disabled polling.
- Adds signed webhook delivery history, duplicate-delivery protection, and an administrator manual webhook test.
- Records rejected webhook signatures without consuming a legitimate future delivery ID.

### Compatibility

- Preserves active WordPress plugin mappings, canonical aliases, Product Connections, Release Operations, Plugin Discovery Intelligence, legacy shortcodes, accessibility behavior, reduced-motion preferences, and the historical `sustainable-catalyst-feature-suggestions` WordPress plugin folder.
- Does not automatically publish release notes or other public communications.
