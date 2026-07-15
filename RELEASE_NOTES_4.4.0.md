# Feature Suggestions v4.4.0 — Support Analytics and Product Reliability Center

## Summary

Version 4.4.0 adds a protected operational intelligence center for comparing support health across Sustainable Catalyst products. It brings together existing evidence from Guided Resolution, Support Articles, Known Issues, releases, Content Operations, Editorial Governance, Repository Synchronization, documentation gaps, and privacy-safe case relationships.

The release does not create a new public tracking system and does not weaken the Contact and Engagement boundary. Reliability scores remain advisory and require human review.

## Product reliability scoring

Each product receives a bounded 0–100 score across seven dimensions:

- Resolution success — 25%
- Documentation usefulness — 20%
- Known-issue health — 15%
- Release readiness — 15%
- Support-content readiness — 10%
- Repository and link health — 10%
- Editorial-governance health — 5%

Open critical issues, high-priority documentation gaps, and overdue editorial reviews are explicit blockers and can reduce the composite score.

## Support and documentation analytics

The Reliability Center includes:

- Guided Resolution search totals and resolution-success rates
- No-match and low-confidence search totals
- Documentation helpfulness and needs-improvement responses
- Aggregate case-to-article and case-to-suggestion relationship counts
- Repeated unresolved-query clusters
- Priority scores based on volume, no-match rates, recency, and operational demand
- Documentation-gap rankings

Search text is sourced from the existing privacy-redacted Guided Resolution analytics table. IP addresses are not stored.

## Known Issue and release intelligence

Product views summarize:

- Active, resolved, high-severity, and critical Known Issues
- Recurring issues supported by aggregate relationship counts
- Release-record counts
- Average release readiness
- Readiness states and blockers

## Content, repository, and governance health

Reliability records also include:

- Product onboarding and support-content readiness
- Repository mapping state
- Local documentation drift
- Broken-link and timeout counts from stored link-health reports
- Overdue editorial reviews
- Standards-blocked records
- Expiring content

The Reliability Center does not run remote repository requests while rendering the dashboard. It uses stored synchronization and link-health evidence.

## Trends and snapshots

A daily scheduled task stores product snapshots for the configured retention period. Product views can compare score, unresolved-search, documentation-usefulness, issue, release, content, and repository-health changes over time.

Administrators can also refresh snapshots manually.

## Reports

CSV and JSON exports provide deterministic product ordering, record counts, and a SHA-256 checksum over the product record collection.

Reports exclude private case identifiers, contact details, correspondence, and documents.

## Administration

Open:

```text
Feature Suggestions → Product Reliability
```

The workspace provides product filtering, scorecards, dimensions, blockers, operational signals, documentation gaps, unresolved clusters, trend direction, scheduled-task health, settings, and report exports.

## WordPress REST API

Protected routes:

```text
GET  /wp-json/scfs/v1/support-reliability/schema
GET  /wp-json/scfs/v1/support-reliability/dashboard
GET  /wp-json/scfs/v1/support-reliability/products/{product}
GET  /wp-json/scfs/v1/support-reliability/trends/{product}
POST /wp-json/scfs/v1/support-reliability/refresh
GET  /wp-json/scfs/v1/support-reliability/report
```

## FastAPI advisory endpoints

```text
GET  /v1/support-reliability/capabilities
POST /v1/support-reliability/score
POST /v1/support-reliability/trends/summarize
POST /v1/support-reliability/clusters/prioritize
POST /v1/support-reliability/reports/verify
```

## Governance and privacy

Version 4.4.0 cannot automatically:

- Change a product roadmap
- Approve a feature suggestion
- Publish support content
- Close a Known Issue
- Declare an incident
- Create a private support case

Contact and Engagement remains the private system of record for identity, messages, documents, and case lifecycle.
