# Support Analytics and Documentation Effectiveness — v5.7.0

Version 5.7.0 adds a privacy-safe analytics layer for evaluating whether public Support Articles and resolution pathways are discoverable, useful, current, and connected to Known Issues and releases.

## What is measured

- Guided Resolution search success and no-match pressure
- Search-to-guidance engagement
- Support Article helpfulness
- Publication-integrity scores
- Article freshness and verification age
- Known Issue guidance and workaround coverage
- Release documentation and changelog coverage
- Documentation Gap linkage and closure

## WordPress operations

The administrator dashboard is available at **Support & Feedback → Support Analytics**. It provides product-level effectiveness scores, review states, low-performing dimensions, articles needing attention, daily snapshots, trend history, CSV export, REST endpoints, and WP-CLI commands.

## Scoring model

The deterministic score uses eight dimensions: search success (20%), search engagement (10%), article helpfulness (20%), publication integrity (15%), content freshness (10%), Known Issue coverage (10%), release coverage (10%), and Documentation Gap resolution (5%).

States are advisory: insufficient evidence, effective, healthy, watch, and intervention review.

## Privacy and governance

The dashboard is administrator-only. It does not expose requester identities, contact details, private case content, uploaded documents, credentials, or raw search text. It cannot publish content, resolve issues, alter releases, or reprioritize the roadmap. Human review remains required.

## Compatibility

No database migration is required. Existing CPTs, taxonomies, metadata, shortcodes, REST namespace, Support Article URLs, Support Center integration, and legacy Knowledge Base redirects remain unchanged.
