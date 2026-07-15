# Feature Suggestions v4.5.0 — Cross-Product Support Orchestration

## Summary

Version 4.5.0 adds the public coordination layer needed when support issues, components, releases, and resolution paths span multiple Sustainable Catalyst products.

## Platform Incidents

- Added the public `sc_platform_incident` post type.
- Added investigating, identified, monitoring, and resolved states.
- Added low, moderate, high, and critical severity.
- Added public summaries, workarounds, start dates, and resolution dates.
- Added shared product, version, component, issue-type, and release relationships.
- Added deterministic advisory incident-impact scoring.
- Preserved human editorial review before publication.

## Product dependency graph

- Added product dependency administration under **Feature Suggestions → Cross-Product Support**.
- Added `depends_on`, `integrates_with`, `shares_component`, `routes_to`, and `provides_data_to` relationships.
- Added component and criticality context.
- Added duplicate-resistant line parsing and normalized dependency records.
- Added public related-product recommendations.

## Multi-product support orchestration

- Added orchestration metadata to Support Articles, Known Issues, and Release Records.
- Added related-product, related-incident, dependent-release, and resolution-stage fields.
- Added dependency-aware Known Issue context.
- Added platform-wide release dependencies.
- Added support for records assigned to multiple Product taxonomy terms.

## Public Support Center

- Added the **Platform status** workspace.
- Added public incident summaries and affected-product labels.
- Added related-product pathways.
- Added cross-product resolution journeys.
- Added the `[scfs_cross_product_support]` standalone shortcode.
- Preserved Guided Resolution, Knowledge Base, Known Issues, releases, suggestions, surveys, and private support.

## REST and FastAPI

WordPress routes:

- `GET /wp-json/scfs/v1/cross-product/schema`
- `GET /wp-json/scfs/v1/cross-product/overview`
- `GET /wp-json/scfs/v1/cross-product/incidents`
- `GET /wp-json/scfs/v1/cross-product/routes`
- `POST /wp-json/scfs/v1/cross-product/journey`
- `GET /wp-json/scfs/v1/cross-product/snapshot`

FastAPI routes:

- `GET /v1/cross-product/capabilities`
- `POST /v1/cross-product/incidents/evaluate`
- `POST /v1/cross-product/routes/recommend`
- `POST /v1/cross-product/journeys/build`
- `POST /v1/cross-product/reports/verify`

## Governance and privacy

- Automatic incident declaration remains disabled.
- Automatic release blocking remains disabled.
- Automatic roadmap changes remain disabled.
- Automatic private-case creation remains disabled.
- Requester identity, correspondence, private documents, and private case narratives are not stored in cross-product records.
- Contact and Engagement remains the private case-management system of record.
