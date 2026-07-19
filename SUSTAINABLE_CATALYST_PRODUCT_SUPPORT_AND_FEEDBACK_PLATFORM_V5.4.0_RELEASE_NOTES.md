# Sustainable Catalyst Product Support and Feedback Platform v5.4.0

## Known Issues and Release Intelligence Integration

Version 5.4.0 connects the platform’s existing Known Issue, Release Record, Support Article, product-version, component, and changelog structures into one operational support layer.

## Added

- Versioned `scfs-known-issue-release-intelligence/1.0` contract.
- Bidirectional relationships between Known Issues and existing Release Records.
- Explicit target-release and fixed-release relationships.
- Related publication-grade Support Article relationships for Known Issues.
- Derived open and resolved issue groupings on Release Records.
- Affected-version, component, workaround, resolution, status-note, and verification context.
- Release verification state and last-verified date.
- Relationship-health checks for missing affected versions, workarounds, target releases, fixed-release evidence, changelogs, and Support Articles.
- `Support & Feedback → Issue & Release Links` administration screen.
- Bulk relationship synchronization and advisory review queue.
- `[scfs_issue_release_intelligence]` public shortcode and legacy alias.
- Publication-style operational metadata on single Known Issue and Release pages.
- Known Issue and Release context inside the unified Support Center and Unified Support Search.
- Public WordPress REST schema, issue, release, and record endpoints.
- Protected administrative synchronization endpoint.
- Deterministic FastAPI relationship evaluation and capability endpoints.
- Responsive, print-safe, and admin interface styling.

## Compatibility

The release preserves:

- `Sustainable_Catalyst_Feature_Suggestions`;
- all `scfs_*` functions, hooks, options, metadata, and shortcodes;
- REST namespace `scfs/v1`;
- `sc_feature_suggest`, `sc_support_article`, `sc_known_issue`, and `sc_release_record`;
- existing Support Article URLs under `/support/guides/`;
- the canonical `/support/` page and legacy Knowledge Base redirects;
- existing release relationship metadata and all current records.

No database migration is required. New relationships use WordPress post metadata and existing taxonomies.

## Governance

The integration is advisory. It does not automatically declare incidents, change issue or release status, block releases, publish content, modify roadmaps, create private support cases, or expose private correspondence. WordPress remains the source of truth and human review remains required.
