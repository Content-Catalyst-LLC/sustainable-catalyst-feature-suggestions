# Feature Suggestions v3.1.0 — Product Taxonomy and Platform Integration

Version 3.1.0 establishes the shared product context used by feature suggestions and the future Support Knowledge Base, Known Issues, and Release Intelligence layers.

## Shared taxonomies

| Taxonomy | WordPress name | Purpose |
|---|---|---|
| Product | `scfs_product` | Canonical Sustainable Catalyst products and platform areas |
| Product Version | `scfs_product_version` | Affected or reported product versions |
| Component | `scfs_component` | Reusable functional and technical components |
| Issue Type | `scfs_issue_type` | Feature, defect, documentation, support, reliability, privacy, and integration classifications |
| Release | `scfs_release` | Planned, active, and completed releases |

The taxonomies are registered for Feature Suggestions and reserved future object types for Support Articles, Known Issues, and Release Records. Other Sustainable Catalyst plugins can add object types through the `scfs_product_taxonomy_object_types` filter.

## Foundation vocabulary

Activation seeds the canonical Sustainable Catalyst products, common technical and user-facing components, and issue types. Versions and releases remain curator-controlled and are created from reviewed data or existing target-release metadata.

Each term supports:

- `scfs_canonical_id`
- `scfs_status`
- `scfs_product_ids`
- release date metadata for release terms

## Existing suggestion migration

The migration is idempotent. It preserves existing assignments and only fills missing context.

Product, component, and issue classifications may be inferred from:

- roadmap area
- suggestion category
- title and structured suggestion text
- existing AI triage output
- existing product-version metadata
- target release metadata

The migration can run automatically in small administrator-request batches, manually from **Feature Suggestions → Products & Integration**, or with WP-CLI:

```bash
wp scfs migrate-product-taxonomies --batch=200
```

Migration status and taxonomy coverage are available from the protected REST route:

```text
/wp-json/scfs/v1/taxonomy/migration
```

## Public and administrative interfaces

The public suggestion form can display product, version, component, and issue-type selectors. The setting can be disabled without removing the shared taxonomy system.

Public taxonomy endpoints:

```text
/wp-json/scfs/v1/taxonomy/schema
/wp-json/scfs/v1/taxonomy/terms
```

Authenticated suggestion records, events, CSV exports, AI triage requests, and intelligence results now include product context.

## Product-aware analytics

The Feedback Intelligence Dashboard now reports and filters by:

- product
- product version
- component
- issue type
- release

The original AI platform-area and feature-type signals remain available for compatibility and comparative review.

## Upgrade behavior

The plugin detects the v3.1.0 taxonomy schema even when WordPress replaces the plugin without rerunning the activation hook. It schedules an incremental migration on the next authorized administrator request.
