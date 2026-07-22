# Product Support and Feedback Platform v7.1.0

## Canonical Product Registry

v7.1.0 creates the governed catalog that future release boards, release records, documentation, support analytics, and product discovery will use as their source of product identity.

### Delivered

- Seventeen seeded Sustainable Catalyst product records across five product families.
- Required foundation records for Core, Product Support and Feedback, Contact and Engagement, and Knowledge Library.
- Stable canonical IDs, public and short names, legacy aliases, product type, version source, installed and public versions, release channel, status, visibility, display order, URLs, ownership, and verification metadata.
- A manual Catalyst Intelligence Platform record at v0.23.1 in development status.
- A Product Registry administration screen under Support & Feedback.
- Authenticated registry REST routes, JSON export, and WP-CLI list, export, and seed commands.
- Python registry validation with duplicate-ID, visibility, required-product, source-state, and governance checks.
- A JSON Schema, example payload, documentation, and release contracts.

### Boundaries

- Installed-plugin discovery is not activated until v7.2.0.
- Remote manifests, service endpoints, and package manifests remain reserved sources.
- The registry does not automatically publish product or release information.
- Private Catalyst Intelligence repository and build details are not exposed.
- Existing product taxonomies remain the classification system for cases, articles, known issues, and releases.

### Compatibility

The public plugin name remains **Sustainable Catalyst Product Support and Feedback Platform**. The existing WordPress plugin directory, text domain, PHP class, post types, REST namespace, routes, settings, and shortcodes are preserved. The new registry uses a non-autoloaded WordPress option and requires no destructive database migration.
