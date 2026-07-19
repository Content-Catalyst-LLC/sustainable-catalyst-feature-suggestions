# Sustainable Catalyst Product Support and Feedback Platform v5.2.8

## Support Article Content Integrity and Publication Validation

Release date: 2026-07-18

Version 5.2.8 adds a dedicated publication-readiness layer for the existing Support Article library. It validates completeness, metadata, supported versions, heading structure, accessibility, relationships, and freshness without changing article URLs, rewriting content, or automatically publishing records.

## Support Article integrity engine

Every `sc_support_article` can now be assessed against a versioned `scfs-support-article-integrity/1.0` contract. The engine checks:

- article title specificity;
- minimum and recommended article length;
- excerpts and Knowledge Base summaries;
- product, version, component, Article Type, and collection assignments;
- last verified version coverage;
- H2-led heading hierarchy and skipped heading levels;
- Editorial Governance required sections;
- unreplaced article-template placeholder text;
- empty, unsafe, missing-anchor, and unresolved internal links;
- image alternative text;
- figure captions;
- table header cells;
- related release, Known Issue, article, and reviewed-suggestion context;
- modification freshness and overdue editorial review dates.

## Publication-readiness scoring

Assessments produce a 0–100 score and one of five stored states:

- Publication ready
- Review recommended
- Needs work
- Publication blocked
- Not validated

Errors, warnings, and informational recommendations remain separately visible. The score is advisory and does not replace WordPress permissions or the existing Editorial Governance publication gate.

## WordPress administration

The release adds **Support & Feedback → Article Integrity** with:

- one-click validation of all Support Articles;
- readiness and freshness summaries;
- readiness-state filtering;
- article-level scores and top issues;
- direct editor links;
- CSV export;
- stored last-scan results.

Each Support Article editor now includes a **Publication Readiness** panel. The Support Article list includes a readiness column, state filter, and per-record **Validate readiness** action.

## Automatic validation

Support Articles are reassessed when they are saved or move into Pending Review, Scheduled, or Published status. The validator stores only structured integrity metadata. It does not change article text, taxonomy assignments, URLs, publication state, or private editorial notes.

## REST and WP-CLI

The existing REST namespace remains `scfs/v1`. New administrative routes are available under:

```text
/wp-json/scfs/v1/support-article-integrity/*
```

The schema endpoint is public; article assessments, bulk scans, and reports require editorial capabilities.

WP-CLI commands:

```bash
wp scfs article-integrity scan
wp scfs article-integrity scan --product=decision-studio
wp scfs article-integrity article 812
```

## FastAPI advisory contract

The backend adds a deterministic Support Article integrity evaluator and capability endpoint. It mirrors the publication-readiness scoring model for external validation workflows while keeping WordPress as the source of truth.

## Compatibility

Version 5.2.8 preserves:

- the plugin directory, repository slug, and text domain;
- `Sustainable_Catalyst_Feature_Suggestions`;
- all existing `scfs_*` functions, hooks, options, and metadata;
- the `sc_feature_suggest`, `sc_support_article`, `sc_known_issue`, and `sc_release_record` post types;
- the `scfs/v1` REST namespace;
- all current shortcodes and aliases;
- `/support/` as the canonical Support Center;
- Support Article URLs under `/support/guides/`;
- Knowledge Base route redirects and filter preservation;
- all existing suggestions, documentation, issues, releases, surveys, votes, settings, and relationships.

No database migration is required.

## Safety boundaries

The integrity layer does not:

- rewrite Support Article content;
- publish or unpublish records automatically;
- delete or move articles;
- change public URLs;
- create private support cases;
- expose private Contact and Engagement records;
- expose private editorial notes;
- make automatic roadmap decisions.

Human editorial review remains mandatory.
