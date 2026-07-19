# Sustainable Catalyst Product Support and Feedback Platform v5.8.0

## Cross-Product Support Graph and Platform Handoffs

Version 5.8.0 turns the existing product taxonomy and cross-product orchestration layer into a visible support graph. The Support Center can now show how a question moves among products while preserving the reader’s product, version, component, and capability context.

## Product graph

The release introduces a canonical node contract for the Sustainable Catalyst product ecosystem. Each node includes the product name, routes, capabilities, support coverage, and handoff destinations. Existing WordPress product terms extend the catalog, and product modules can contribute contracts through a filter rather than a direct dependency.

## Support coverage

Each node receives a deterministic coverage score based on published Support Articles, Known Issues, release records, worked examples, troubleshooting guidance, and registered capabilities. Coverage states are `connected`, `strong`, `partial`, `limited`, and `unmapped`.

## Handoff planning

The platform compares a public task or symptom with product capabilities, graph relationships, and component context. Recommendations include the destination product, score, reasons, filtered Support Center route, public product route, and destination coverage state.

No handoff is performed automatically. The visitor chooses whether to continue.

## Graph integrity

The integrity engine detects duplicate product slugs, self-referencing edges, duplicate edges, unknown products, missing support routes, and products with no registered capabilities. Reports use deterministic ordering and SHA-256 checksums.

## Public and administrative interfaces

Public shortcode:

```text
[scfs_cross_product_support_graph]
```

Administration:

```text
Support & Feedback → Support Graph
```

WordPress REST routes remain under `scfs/v1`. FastAPI parity is available under `/v1/support-graph/`.

## Compatibility

All legacy plugin identifiers and existing data remain intact. Support Articles continue to use `/support/guides/`; the canonical public destination remains `/support/`; and no database migration is required.

## Privacy boundary

Only public product and support records are represented. The graph excludes requester identity, raw query text, private correspondence, private documents, and private Contact and Engagement records.
