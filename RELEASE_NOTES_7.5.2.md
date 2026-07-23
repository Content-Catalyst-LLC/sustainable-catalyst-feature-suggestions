# Sustainable Catalyst Product Support and Feedback Platform v7.5.2

## Canonical Plugin Mapping and Review Workflow

v7.5.2 turns Plugin Discovery into an actionable Canonical Product Registry workflow while retaining the Release Console alignment and status repairs delivered in v7.5.1.

### Canonical product mapping

- Adds a **Map to canonical product** dropdown to every actionable Plugin Discovery candidate row.
- Limits mapping targets to governed registry records that accept installed WordPress plugin discovery.
- Gives administrator-confirmed mappings precedence over automatic file, slug, text-domain, header, and name-alias matching.
- Persists the installed plugin file, directory slug, text domain, and display name as governed canonical identifiers or aliases.
- Preserves the physical WordPress plugin folder and does not rename plugin files, shortcodes, post types, options, or routes.
- Refuses mappings that would create an ambiguous identifier collision.
- Supports deterministic reassignment of duplicate candidates when the administrator chooses a different canonical product.

### Review controls

- Adds **Not a Sustainable Catalyst product** as a reversible ignore decision.
- Adds an ignored-plugin review panel with a **Restore to review** action.
- Adds **Remove manual mapping** for administrator-created mappings and restores the prior canonical identifiers.
- Records mapping time, administrator identity, selected product, source identifiers, and rollback snapshots.
- Recalculates matched, pending, duplicate, ignored, and manual-mapping counts after every decision.

### Accessible live updates

- Uses an authenticated REST decision endpoint to update Plugin Discovery without a full-page reload.
- Replaces the summary, matched-products table, review queue, ignored-plugin panel, and zero state from server-rendered fragments.
- Retains ordinary nonce-protected forms when JavaScript is unavailable.
- Uses an `aria-live` status region, visible busy state, semantic tables, keyboard-operable controls, and reduced-motion styling.
- Displays **No plugins awaiting review** immediately when the final actionable candidate is resolved.

### Compatibility and validation

- Preserves `[sc_release_board]` and every inherited public and administrative shortcode.
- Preserves the WordPress plugin folder `sustainable-catalyst-feature-suggestions` and its text domain.
- Preserves canonical IDs, manual version governance, discovery locks, private plugin-path boundaries, and human authorization.
- Requires no destructive database migration.
- Passes 271 sequential PHP contracts and 318 backend tests.
