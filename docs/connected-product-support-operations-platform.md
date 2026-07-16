# Connected Product Support Operations Platform

Feature Suggestions v5.0.0 consolidates the operational layers introduced from v3.1.0 through v4.5.0 into one governed support-operations environment.

## Operating model

The Connected Operations Center does not replace specialist modules. Product taxonomy, Content Operations, Repository Sync, Editorial Governance, Guided Resolution, Knowledge Base, Known Issues, releases, suggestions, surveys, Product Reliability, and Cross-Product Orchestration remain the source of truth for their own records.

The v5 layer provides:

- unified module-health inventory;
- product operations dossiers;
- combined content-readiness and reliability evidence;
- repository, governance, incident, and unresolved-search blockers;
- a human-approved action queue;
- daily operational snapshots;
- integrity-protected JSON reports;
- aggregate Contact and Engagement handoff readiness.

## Governance boundary

Connected Operations cannot automatically publish content, approve editorial work, declare incidents, change the roadmap, or create private support cases. Every operational action is queued and requires an authenticated administrator to execute it.

## Administration

Open **Feature Suggestions → Connected Operations**.

## REST API

Protected WordPress routes:

- `GET /wp-json/scfs/v1/connected-operations/schema`
- `GET /wp-json/scfs/v1/connected-operations/dashboard`
- `GET /wp-json/scfs/v1/connected-operations/products/{product}`
- `GET /wp-json/scfs/v1/connected-operations/actions`
- `POST /wp-json/scfs/v1/connected-operations/refresh`
- `GET /wp-json/scfs/v1/connected-operations/report`

FastAPI advisory routes:

- `GET /v1/connected-operations/capabilities`
- `POST /v1/connected-operations/readiness/score`
- `POST /v1/connected-operations/actions/plan`
- `POST /v1/connected-operations/reports/verify`
