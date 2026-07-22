# Installed Plugin Discovery — v7.2.0

## Purpose

Installed Plugin Discovery keeps the canonical product registry aligned with the WordPress plugins actually installed on Sustainable Catalyst. It replaces manual version maintenance for approved WordPress products while preserving explicit human control.

## Matching hierarchy

Discovery evaluates installed plugins in this order:

1. Exact configured plugin file.
2. `SC Product ID` custom plugin header.
3. Canonical plugin slug or directory.
4. Configured text domain.
5. Exact approved product name or legacy alias when the author identifies Content Catalyst.

The first approved match wins. A second plugin matching the same product is placed in the private duplicate-review queue.

## Governance boundaries

- Only products already present in the canonical registry can be updated automatically.
- Unknown plugins are never added to the public registry or homepage release board.
- Unknown Catalyst-looking plugins are visible only to administrators.
- Absolute server paths are never stored in discovery records or exposed publicly.
- Discovery never publishes release notes or changes public visibility.
- Manual products, including Catalyst Intelligence Platform, remain manual.
- A product-level lock preserves configured installed version, public version, and status while discovery evidence continues to refresh.

## Version behavior

For an unlocked WordPress product, discovery updates `installed_version`. It updates `public_version` only when that field is empty or still equals the previously discovered installed version. This preserves intentional public-version overrides. Active plugins receive `current`; installed but inactive plugins receive `inactive`.

## Refresh and caching

Discovery caches its snapshot for 15 minutes. The cache is invalidated and refreshed after plugin activation, deactivation, deletion, or upgrade. Registry edits invalidate the cache. Administrators can force a rescan under **Support & Feedback → Plugin Discovery**.

## Maintenance interfaces

- WordPress administrator screen: `Plugin Discovery`
- REST status: `GET /wp-json/scfs/v1/product-registry/discovery`
- REST rescan: `POST /wp-json/scfs/v1/product-registry/discovery/rescan`
- WP-CLI rescan: `wp scfs products discover`
- WP-CLI status: `wp scfs products discovery-status`

All discovery interfaces require administrative authorization.
