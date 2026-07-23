# Sustainable Catalyst Product Support and Feedback Platform v7.6.1

## Release Operations Stabilization

v7.6.1 stabilizes the v7.6.0 Release Operations control plane against production WordPress, GitHub, plugin-discovery, caching, and footer-routing failure modes.

### Exact GitHub diagnostics

- Records the failing GitHub endpoint, endpoint URL, HTTP status, error code, connection classification, and failure timestamp.
- Distinguishes authentication required, repository unavailable, rate limited, network error, and general request error states.
- Keeps default-branch commit failures nonblocking when release or semantic-tag evidence is otherwise available.
- Clears stale endpoint, status, error-code, and failure-time fields immediately after a successful retry.

### Active plugin and registry integrity

- Compares registry mappings with WordPress’s current active site and network plugin list.
- Flags a registry record that claims an inactive or missing implementation is active.
- Flags an active mapped plugin when the discovery snapshot has gone stale.
- Preserves every manual mapping, canonical alias, plugin folder, and legacy shortcode.

### Footer and cache verification

- Resolves and verifies the Release Console repository destination, including the canonical repository fallback.
- Verifies the configured Support destination.
- Exposes footer health in Release Operations.
- Invalidates the Release Console cache after registry, plugin-discovery, GitHub, footer, bulk-sync, and stabilization changes.

### One-click stabilization

The new **Stabilize release operations** action:

1. Rescans installed plugins.
2. Repairs the hourly GitHub synchronization schedule.
3. Synchronizes every connected repository.
4. Clears stale error evidence for successful retries.
5. Invalidates operational caches.
6. Runs the Release Operations integrity audit.

### Compatibility

- Preserves `[sc_release_board]`.
- Preserves blackboard, compact, directory, and terminal layouts.
- Preserves keyboard, focus, reduced-motion, screen-reader, and no-JavaScript behavior.
- Preserves the WordPress plugin folder `sustainable-catalyst-feature-suggestions`.
- Does not expose GitHub tokens or webhook secrets in reports, diagnostics, or exports.
