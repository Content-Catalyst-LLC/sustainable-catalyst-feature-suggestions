# Sustainable Catalyst Feature Suggestions v3.1.0

## Product Taxonomy and Platform Integration

This release establishes the shared product context required before the Support Knowledge Base is added in v3.2.0.

### Added

- Shared Product, Product Version, Component, Issue Type, and Release taxonomies.
- Canonical Sustainable Catalyst product, component, and issue vocabularies.
- Stable canonical term IDs, status metadata, and product relationships.
- Idempotent migration for existing suggestions.
- Automatic incremental migration after an in-place plugin upgrade.
- Manual migration dashboard and WP-CLI migration command.
- Optional product-context fields on the public suggestion form.
- Product-aware administrator columns and filters.
- Product-aware Feedback Intelligence filters and charts.
- Product context in REST records, shared events, AI triage payloads, and CSV exports.
- Public taxonomy schema and term endpoints.
- `sc-contact-engagement-handoff/1.0` contract.
- Protected Contact and Engagement handoff payload endpoint.

### Architectural boundary

The handoff contract does not merge private support case management into Feature Suggestions. It keeps public product feedback and documentation in the Product Support and Feedback Platform while Contact and Engagement remains responsible for private cases, communication, documents, and lifecycle management.

### Upgrade

Upload the new plugin ZIP through WordPress and replace the existing version. Then open:

```text
Feature Suggestions → Products & Integration
```

The upgrade detector schedules the migration automatically. The full migration can also be run manually from that screen or with:

```bash
wp scfs migrate-product-taxonomies --batch=200
```

### Validation

- PHP lint: passed for all plugin PHP files.
- Static v3.1.0 structure checks: 13 passed.
- Lightweight plugin bootstrap checks: 6 passed.
- Python/FastAPI tests: 6 passed.
