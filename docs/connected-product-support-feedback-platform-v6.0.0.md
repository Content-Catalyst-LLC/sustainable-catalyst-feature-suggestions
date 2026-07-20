# Connected Product Support and Feedback Platform v6.0.0

v6.0.0 establishes one governed platform contract across the public Support Center, publication-grade Support Articles, Known Issues and releases, feedback intelligence, analytics, public integrations, and cross-product handoffs.

## Five connected layers

1. **Support Center** — the canonical `/support/` destination, unified search, and Guided Resolution.
2. **Publication Library** — Support Articles, worked examples, article integrity, and editorial governance.
3. **Operational Intelligence** — Known Issues, affected versions, workarounds, releases, and reliability context.
4. **Feedback Intelligence** — suggestions, article feedback, documentation gaps, votes, and product signals.
5. **Platform Integration** — analytics, public APIs, product embeds, institutional contracts, and cross-product handoffs.

## Source-of-truth model

The connected platform does not replace its specialist modules. Each module remains authoritative for its own records. The v6 layer composes public summaries, product dossiers, health state, and journey plans without automatically publishing, resolving issues, changing releases, changing roadmap state, or creating private support cases.

## Public shortcode

```text
[scfs_connected_product_support_platform]
```

Product-scoped example:

```text
[scfs_connected_product_support_platform product="decision-studio"]
```

Compatibility alias:

```text
[scfs_connected_support_platform]
```

## WordPress REST API

All routes retain the `scfs/v1` namespace:

```text
GET  /wp-json/scfs/v1/connected-platform/schema
GET  /wp-json/scfs/v1/connected-platform/overview
GET  /wp-json/scfs/v1/connected-platform/products
GET  /wp-json/scfs/v1/connected-platform/product/{product}
POST /wp-json/scfs/v1/connected-platform/journey
GET  /wp-json/scfs/v1/connected-platform/health
POST /wp-json/scfs/v1/connected-platform/refresh
```

The refresh route is administrator-only.

## FastAPI parity

```text
GET  /v1/connected-platform/capabilities
POST /v1/connected-platform/evaluate
POST /v1/connected-platform/journey/plan
POST /v1/connected-platform/reports/verify
```

## Privacy boundary

The connected layer uses public support records and aggregate administrative health only. It does not expose requester identities, raw private search text, private correspondence, contact records, or uploaded private documents. Private support continues through the consent-gated Contact and Engagement Platform.
