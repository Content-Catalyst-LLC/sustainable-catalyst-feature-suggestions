# Sustainable Catalyst Product Support and Feedback Platform v5.3.0

## Unified Search and Guided Resolution

Version 5.3.0 connects Support Discovery with Guided Resolution so visitors no longer need to decide whether to search documentation or begin a troubleshooting workflow. One query now searches current Known Issues, verified Support Articles, release context, and moderated public improvement records, then builds an ordered resolution path.

### Public experience

- Adds `[scfs_unified_support_search]` and the compatibility alias `[scfs_unified_guided_resolution]`.
- Uses the unified search in the canonical Support Center overview and resolve views.
- Prioritizes current Known Issues before general guidance.
- Enriches Support Article results with the v5.2.9 discovery score and transparent discovery reasons.
- Presents an ordered journey: issues, verified guidance, releases, public improvements, then private support.
- Provides a recommended starting point, confidence, evidence count, grouped results, and privacy reminders.
- Retains server-rendered behavior and adds lightweight keyboard and dynamic-view initialization support.

### API and backend

- Adds WordPress routes under `/wp-json/scfs/v1/unified-support/`.
- Adds FastAPI capability, search, and journey endpoints under `/v1/unified-support/`.
- Adds the `scfs-unified-support-search/1.0` and `scfs-support-resolution-journey/1.0` contracts.
- Keeps WordPress as the content and relationship source of truth.

### Compatibility

The release preserves all existing post types, URLs, taxonomies, settings, options, database tables, shortcodes, Guided Resolution routes, Knowledge Base Discovery routes, and private support handoff behavior. No database migration is required.

### Safety and governance

The workflow searches public support records only. It does not store personal search history, create private cases, publish content, change roadmap state, declare incidents, or make autonomous support decisions. Private handoff remains short-lived, consent-gated, and routed to the separate Contact and Engagement Platform.
