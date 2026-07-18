# Integrated Knowledge Base and Documentation Library

## Purpose

Feature Suggestions v5.1.0 makes documentation a first-class part of the Product Support and Feedback Platform. It combines a traditional browsable Knowledge Base with product-aware search, known issues, releases, guided resolution, usefulness feedback, editorial governance, and private-support handoffs.

The implementation is native to WordPress and Feature Suggestions. It does not execute or depend on the legacy KnowledgeBuilder application.

## Content model

Every guide is a real `sc_support_article` record. Existing taxonomies continue to provide cross-product intelligence:

- `scfs_product`
- `scfs_product_version`
- `scfs_component`
- `scfs_issue_type`
- `scfs_release`
- `scfs_article_type`
- `scfs_doc_collection`

Documentation Collections are hierarchical and provide the visible directory structure. The integrated corpus uses two collection levels:

1. Product folder.
2. Documentation-section folder.

Articles sit under the section folder while retaining all other product and support metadata.

## Standard directory

Each of the 16 products receives the same six sections:

1. Start Here
2. Setup and Configuration
3. Tools and Features
4. Worked Examples
5. Troubleshooting
6. Technical Reference

The consistent structure helps readers move between products without learning a new documentation system each time.

## Public browser

The public browser is intentionally compact until opened. Its primary disclosure is labeled **Browse the Knowledge Base**. Once expanded it shows product folders and nested section folders with article counts.

The browser includes:

- full-text WordPress search;
- product filtering;
- accessible native disclosure controls;
- expand-all and collapse-all actions;
- article titles, summaries, and reading time;
- product descriptions and counts;
- clear empty states;
- mobile accordion behavior;
- no-JavaScript browsing support.

JavaScript improves convenience but is not required to access any article.

## Article experience

The article content remains normal WordPress HTML so editors can revise it using the WordPress editor. The public article wrapper adds:

- Knowledge Base, product, and section breadcrumbs;
- print action;
- return-to-product-guides action;
- related guides;
- previous and next guide navigation;
- visitor usefulness rating;
- existing Support Article metadata and known-issue relationships.

## Bundled documentation

The first-party corpus lives at:

```text
wordpress/sustainable-catalyst-feature-suggestions/content/knowledge-base/
├── articles.json
└── samples/
```

The corpus contains 96 articles, 16 products, 283 feature demonstrations, and 32 synthetic CSV/JSON sample files.

Sample files are linked directly from the plugin package. They are not inserted into the WordPress Media Library, which avoids CSV and JSON MIME restrictions and prevents sample delivery from blocking article creation.

## Import and repair

The migration uses stable content keys and generated hashes.

- Missing articles are created.
- Unmodified generated articles are updated.
- Manually changed articles retain their human content.
- Taxonomies and metadata are repaired even when article content is protected.
- Existing non-corpus Support Articles are ignored.
- Repeat runs are idempotent.

Administrators can inspect and rerun the migration under **Feature Suggestions → Knowledge Base Library**.

## Editorial governance

Feature Suggestions normally blocks publication until editorial review requirements are satisfied. The integrated corpus is a verified first-party release artifact, so v5.1.0 introduces a scoped system-publication filter.

The exception is active only while the integrated migration is running and only for `sc_support_article` records. It does not bypass editorial governance for manually created articles, Known Issues, Release Records, Feature Suggestions, repository-sync drafts, or imported third-party content.

Imported articles receive:

- published WordPress status;
- published lifecycle metadata;
- published editorial state;
- migration change summary and approval note;
- source version and content key;
- generated-content hash;
- last-verified product version.

## Usefulness ratings

Visitors can answer **Was this article useful?** with Yes or Not yet. A negative rating can include a reason and an optional comment. The existing Documentation Intelligence layer stores privacy-minimized feedback, limits duplicates, and powers administrator reporting.

Usefulness is advisory. It identifies articles that may need clarification but does not automatically edit, unpublish, or reprioritize documentation.

## Support integration

Knowledge Base navigation remains visible regardless of current article count. The public Support Center can still connect documentation to:

- Guided Resolution;
- Known Issues;
- Release Intelligence;
- Public Ideas;
- Forms and surveys;
- private Contact and Engagement handoffs;
- documentation-gap and reliability analytics.

## Accessibility and visual design

The interface uses semantic headings, labeled search controls, native disclosure elements, visible focus states, keyboard-operable buttons, readable contrast, responsive layouts, and reduced visual noise. The palette follows the current Sustainable Catalyst maroon, black, white, and warm-neutral system.

## Retirement of the separate content pack

After the v5.1.0 migration is confirmed, deactivate the separate Sustainable Catalyst Support Knowledge Base content-pack plugin. Its imported records are retained because the records belong to WordPress, while Feature Suggestions v5.1.0 becomes the single owner of the Knowledge Base experience and future migrations.
