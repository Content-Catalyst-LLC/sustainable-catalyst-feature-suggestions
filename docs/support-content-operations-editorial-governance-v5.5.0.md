# Support Content Operations and Editorial Governance — v5.5.0

Version 5.5.0 adds a shared content-governance layer across Support Articles, Known Issues, and Release Records. It coordinates the existing support-content operations, editorial workflow, publication-integrity, and issue/release intelligence systems without replacing their post types, routes, URLs, or stored records.

## Operating model

Each managed record can have:

- a content owner;
- a technical owner;
- a governance priority;
- a review cadence;
- a verification state;
- a last-verified date;
- a next-review date;
- a verification note;
- supersedes and superseded-by relationships;
- a bounded verification-history ledger; and
- a stored governance snapshot.

The governance engine evaluates those fields together with the existing editorial workflow and Support Article integrity results. It assigns one advisory queue state:

1. Publication blocked
2. Review overdue
3. Review due soon
4. Ownership required
5. Review required
6. Ready for verification
7. Verified
8. Superseded

## WordPress administration

The **Support & Feedback → Content Operations** screen provides:

- queue totals and operational status cards;
- state, priority, content-type, and owner filters;
- record-level ownership, verification, and review dates;
- bulk review requests, verification, assignment, priority, and cadence actions;
- CSV queue export;
- a daily governance scan; and
- editor-side ownership and verification controls.

The system stores new values as WordPress post metadata and options. It does not require a custom database table or migration.

## Verification boundaries

Verification is a human action. A record cannot be marked verified when required ownership, editorial workflow, or article-integrity blockers remain. Verification:

- does not publish a draft;
- does not change a public URL;
- does not automatically approve editorial content;
- does not declare a product incident;
- does not alter release status; and
- does not expose private editorial notes publicly.

## Supersession

A managed record can point to a successor. The predecessor is then marked superseded in governance and content-lifecycle metadata while its WordPress record and URL remain intact. This preserves historical references and prevents silent deletion.

## REST API

The existing `scfs/v1` namespace now includes:

- `GET /content-governance/schema`
- `GET /content-governance/queue`
- `GET /content-governance/record/{id}`
- `POST /content-governance/scan`
- `POST /content-governance/bulk`
- `POST /content-governance/verify/{id}`

Only the schema endpoint is public. Queue, record, scan, bulk, and verification data require WordPress administrative capabilities.

## WP-CLI

```bash
wp scfs content-governance scan
wp scfs content-governance queue --state=overdue
wp scfs content-governance verify 812 --note="Verified against v2.0.1."
```

## FastAPI parity

The deterministic backend includes:

- `GET /v1/content-governance/capabilities`
- `POST /v1/content-governance/evaluate`
- `POST /v1/content-governance/queue/summarize`
- `POST /v1/content-governance/bulk/plan`

The FastAPI service evaluates evidence and plans actions. WordPress remains the source of truth and the only system that mutates editorial records.

## Compatibility

Version 5.5.0 preserves:

- the `sustainable-catalyst-feature-suggestions` plugin directory and repository slug;
- the `Sustainable_Catalyst_Feature_Suggestions` class;
- all `scfs_*` functions, hooks, options, metadata, shortcodes, and REST routes;
- `sc_feature_suggest`, `sc_support_article`, `sc_known_issue`, and `sc_release_record`;
- `/support/` and `/support/guides/` URLs;
- the unified Support Center, Guided Resolution, Knowledge Base browser, Article Integrity, and Issue/Release Intelligence; and
- all existing content and relationships.
