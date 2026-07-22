# Sustainable Catalyst Product Support and Feedback Platform v7.3.1

## Release Telemetry and Homepage Presentation

v7.3.1 transforms `[sc_release_board]` into a sleek terminal-inspired **Release Telemetry** surface designed to complement Live Intelligence.

### Included

- Release Telemetry as the default shortcode title and terminal layout
- Compact command header with registry state, display scope, system counts, source counts, and last synchronization time
- Fixed telemetry columns for product, version, state, and version source
- Restrained terminal styling with a matte-black surface, off-white data, muted structure, green stable states, amber attention states, and red failure states
- Responsive single-column behavior without horizontal scrolling
- Accessible headings, semantic lists, visible status text, source labels, unique cached IDs, focus states, and reduced-motion safeguards
- Existing blackboard, compact, and directory layouts preserved
- Knowledge Library migrated to required public homepage visibility
- `Catalyst AnalyticsR` corrected to **Catalyst Analytics R**, with **Analytics R** as its public telemetry label
- Plugin-sourced and manually governed version counts
- FastAPI Release Telemetry projection and source-count validation

### Compatibility

No destructive database migration is required. The registry schema advances additively to `scfs-canonical-product-registry/1.1`, and the release-board schema advances to `scfs-release-board/1.1`. Canonical product IDs, the WordPress plugin directory, text domain, REST namespace, post types, option keys, and existing shortcodes remain unchanged.
