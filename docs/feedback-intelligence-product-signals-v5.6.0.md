# Feedback Intelligence and Product Signals — v5.6.0

Version 5.6.0 turns previously separate support evidence into a governed product-intelligence layer. It combines feature requests, public votes, Support Article feedback, unresolved Guided Resolution searches, failed public resolution paths, Documentation Gaps, Known Issues, and privacy-safe support relationships.

The layer is designed for product and documentation review. It does not replace editorial judgment, incident management, roadmap governance, or private support.

## WordPress administration

Open **Support & Feedback → Product Signals**.

The dashboard provides:

- product-level signal state and score
- total evidence volume
- feature-request and public-vote demand
- negative Support Article feedback
- unresolved and low-confidence searches
- failed public resolution paths
- open and high-priority Documentation Gaps
- active and critical Known Issues
- privacy-safe support relationship counts
- prioritized evidence clusters
- recommended human review actions
- CSV export

## Signal states

| State | Meaning |
|---|---|
| Insufficient evidence | The minimum evidence threshold has not been reached. |
| Monitor | Evidence exists but does not indicate a material current concern. |
| Emerging | A pattern is becoming visible and should remain under observation. |
| Elevated | Multiple evidence sources indicate a meaningful product or documentation need. |
| Critical review | The combined evidence should receive prompt human review. This is not an automatic incident declaration. |

## Deterministic scoring

The score is intentionally bounded and transparent. Evidence dimensions include:

- negative article feedback
- unresolved public support searches
- Documentation Gaps
- active Known Issues
- failed resolution paths
- feature requests
- public votes
- privacy-safe support relationships

Weights and thresholds are stored in the existing WordPress options system. No new table or database migration is required.

## Evidence clusters

The system produces reviewable clusters for:

- negative Support Article feedback reasons
- unresolved search hashes and product context
- open Documentation Gaps
- Known Issue demand
- feature-request demand

Raw search text is not included in the product-signal snapshot. Search hashes and aggregate counts are used instead.

## REST API

Administrator-authorized WordPress routes remain under the existing `scfs/v1` namespace:

```text
/wp-json/scfs/v1/feedback-product-signals/schema
/wp-json/scfs/v1/feedback-product-signals/summary
/wp-json/scfs/v1/feedback-product-signals/products
/wp-json/scfs/v1/feedback-product-signals/product/{product}
/wp-json/scfs/v1/feedback-product-signals/refresh
```

FastAPI deterministic parity is available at:

```text
GET  /v1/feedback-product-signals/capabilities
POST /v1/feedback-product-signals/score
POST /v1/feedback-product-signals/portfolio
POST /v1/feedback-product-signals/clusters/prioritize
```

WordPress remains the source of truth for records and aggregates.

## WP-CLI

```bash
wp scfs product-signals refresh
wp scfs product-signals summary
wp scfs product-signals product decision-studio
```

## Privacy and governance

The dashboard and APIs are administrator-only. They do not expose:

- contact details
- requester identity
- private case correspondence
- private uploaded documents
- raw search text
- credentials or secrets

Product signals are advisory. They cannot automatically:

- approve or reject a feature request
- change roadmap state
- declare an incident
- resolve a Known Issue
- change release status
- publish or unpublish content
- create a private support case

Human review remains required.
