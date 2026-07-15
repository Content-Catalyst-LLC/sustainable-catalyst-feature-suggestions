# Sustainable Catalyst Feature Suggestions v4.0.0

## Product Support and Feedback Platform

Version 4.0.0 consolidates the public support and feedback roadmap into one product-aware platform while preserving the boundary between public product intelligence and private case management.

## Unified Support Center

The `[scfs_product_support_center]` shortcode provides one interface for:

- Guided Resolution;
- the Support Knowledge Base;
- Known Issues;
- Release Intelligence;
- moderated feature ideas and advisory voting;
- structured feature-suggestion submission;
- open forms and surveys;
- consent-gated continuation into private support.

The legacy `[scfs_support_center]` alias remains supported. The Support Knowledge Base archive now opens the unified Support Center.

## Release Intelligence

The new public `sc_release_record` post type supports planned, preview, current, maintenance, superseded, and retired releases. Release records can include:

- release dates and lifecycle status;
- public summaries and support notes;
- highlights and known limitations;
- documentation and changelog links;
- canonical product/version context;
- related Support Articles and Known Issues;
- related moderated public ideas;
- deterministic release-readiness evidence.

Private suggestion text and contact data are never included in public release records.

## Shared product routing

A Support Center product selector carries context into Guided Resolution, Knowledge Base browsing, Known Issues, release records, public ideas, surveys, suggestion submission, and private handoff routing.

## Contact and Engagement boundary

Feature Suggestions owns public documentation, support search, Known Issues, releases, suggestions, voting, surveys, and product intelligence.

Contact and Engagement owns requester identity, private support cases, correspondence, secure documents, and case lifecycle management.

Automatic private case creation remains disabled. Private continuation requires consent and uses short-lived context rather than copying private case content into Feature Suggestions.

## REST and backend intelligence

New WordPress REST endpoints expose:

- the Product Support Platform schema;
- public support overview data;
- release records;
- product summaries;
- the private-support handoff schema;
- a protected administrator-only operational snapshot.

The FastAPI service adds deterministic public-support state summarization and release-readiness scoring. Automated scores remain advisory and require human review.

## Compatibility

Version 4.0.0 remains backward-compatible with:

- v3.1.0 shared product taxonomy and platform integration;
- v3.2.0 Support Knowledge Base records;
- v3.3.0 Guided Resolution;
- v3.4.0 Documentation and Feature Intelligence.

## Validation

The release passed:

- PHP syntax validation across 20 plugin and test files;
- 37 release-structure checks;
- 15 plugin-bootstrap checks;
- 15 WordPress registration checks;
- 16 platform-schema checks;
- 8 public/private boundary rendering checks;
- 16 Python and FastAPI tests;
- validation of 18 JSON records;
- shell-script syntax validation;
- WordPress ZIP root and integrity validation;
- push-safe secret scanning.
