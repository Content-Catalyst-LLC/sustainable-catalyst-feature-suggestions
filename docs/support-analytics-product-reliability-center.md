# Support Analytics and Product Reliability Center

Feature Suggestions v4.4.0 adds a protected operational intelligence workspace for comparing support health across Sustainable Catalyst products.

## Purpose

The Reliability Center combines privacy-minimized signals from Guided Resolution, documentation feedback, Known Issues, Release Intelligence, Content Operations, Editorial Governance, and Repository Synchronization. It does not replace those systems and does not make roadmap or incident decisions automatically.

## Reliability dimensions

Each product receives a bounded 0–100 advisory score composed of:

- Resolution success: 25%
- Documentation usefulness: 20%
- Known-issue health: 15%
- Release readiness: 15%
- Support-content readiness: 10%
- Repository and link health: 10%
- Editorial-governance health: 5%

Open critical issues, high-priority documentation gaps, and overdue editorial reviews act as explicit blockers and can lower the composite score.

## Operational views

The administration workspace provides:

- Product reliability scorecards
- Resolution and unresolved-search counts
- Documentation helpfulness trends
- Active and recurring Known Issues
- Release-readiness aggregation
- Product onboarding and content-readiness status
- Repository drift and broken-link health
- Editorial review health
- Documentation-gap prioritization
- Repeated unresolved-query clusters
- Daily snapshots and trend direction

## Privacy boundary

The Reliability Center stores and exports aggregate operational evidence only. It does not store IP addresses, requester identity, contact details, private case correspondence, private documents, or private case narratives. Contact and Engagement remains the system of record for private support.

## Governance boundary

Reliability scores are advisory. They cannot automatically:

- Change a roadmap
- Approve a feature suggestion
- Publish support content
- Close a Known Issue
- Declare an incident
- Create a private support case

## Administration

Open **Feature Suggestions → Product Reliability**.

Administrators can select a product, refresh daily snapshots, inspect dimensions and blockers, adjust thresholds, and export checksum-protected CSV or JSON reports.

## REST API

Protected WordPress routes:

- `GET /wp-json/scfs/v1/support-reliability/schema`
- `GET /wp-json/scfs/v1/support-reliability/dashboard`
- `GET /wp-json/scfs/v1/support-reliability/products/{product}`
- `GET /wp-json/scfs/v1/support-reliability/trends/{product}`
- `POST /wp-json/scfs/v1/support-reliability/refresh`
- `GET /wp-json/scfs/v1/support-reliability/report`

FastAPI advisory routes:

- `GET /v1/support-reliability/capabilities`
- `POST /v1/support-reliability/score`
- `POST /v1/support-reliability/trends/summarize`
- `POST /v1/support-reliability/clusters/prioritize`
- `POST /v1/support-reliability/reports/verify`
