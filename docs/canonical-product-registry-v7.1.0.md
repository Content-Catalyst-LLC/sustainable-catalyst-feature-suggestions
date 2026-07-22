# Canonical Product Registry v7.1.0

The Canonical Product Registry is the governed source of product identity for the Sustainable Catalyst Product Support and Feedback Platform. It is separate from the existing product taxonomies used to classify support cases, articles, known issues, and releases.

## Purpose

The registry provides stable product identifiers, public names, product families, version-source rules, visibility controls, URLs, ownership, release state, and display ordering. Future release-board, release-directory, documentation, and product-discovery surfaces consume this registry instead of maintaining independent product lists.

## Storage and compatibility

The registry is stored in the non-autoloaded WordPress option `scfs_canonical_product_registry`. No custom database table or destructive migration is required. The WordPress plugin directory, text domain, PHP class, post types, REST namespace, routes, settings, and existing shortcodes remain unchanged.

## Seed catalog

v7.1.0 seeds seventeen canonical records across five families:

- Foundation: Core, Product Support and Feedback, Contact and Engagement, and Knowledge Library.
- Research and Intelligence: Research Librarian, Site Intelligence, Decision Studio, and Narrative Risk.
- Data and Analysis: Catalyst Data, Catalyst AnalyticsR, Catalyst Finance, and Global Impact Catalyst.
- Creation and Systems: Catalyst Canvas, Catalyst Grit, Workbench, and Sustainable Catalyst Lab.
- Commercial Platform: Catalyst Intelligence Platform.

Catalyst Intelligence is seeded as a manual commercial development record. Its private repository and private build details are not part of the public record.

## Version sources

v7.1.0 activates two version-source modes:

- `wordpress_plugin`
- `manual`

The schema reserves `remote_manifest`, `service_endpoint`, and `package_manifest` for later governed releases. They are not yet used for automatic publication.

## Administration

The **Support & Feedback → Product Registry** screen allows administrators to maintain product names, families, version sources, current and public versions, channels, statuses, visibility, order, URLs, ownership, aliases, and notes. Canonical identifiers are preserved as stable keys.

Authenticated REST routes expose the schema and administrative registry:

- `GET /wp-json/scfs/v1/product-registry/schema`
- `GET /wp-json/scfs/v1/product-registry`
- `GET /wp-json/scfs/v1/product-registry/{product_id}`

WP-CLI commands are available for listing, exporting, and seeding the registry.

## Governance boundaries

The registry does not automatically publish releases, inspect installed plugins, disclose private repository fields, or replace human review. Installed-plugin discovery begins in v7.2.0. The homepage release-board shortcode begins in v7.3.0.
