# Feature Suggestions v3.2.0 — Support Knowledge Base Foundation

Version 3.2.0 turns Feature Suggestions into the foundation of the Sustainable Catalyst Product Support and Feedback Platform. It adds a first-party documentation system while preserving Contact and Engagement as the private case-management layer.

## Content models

### Support Articles

WordPress post type: `sc_support_article`

Support Articles are public, revisioned, REST-enabled documentation records. They use the shared v3.1.0 Product, Product Version, Component, Issue Type, and Release taxonomies, plus two Knowledge Base taxonomies:

- `scfs_doc_collection` — hierarchical product documentation collections.
- `scfs_article_type` — getting started, how-to, troubleshooting, technical reference, FAQ, known-issue companion, and release-note classifications.

Article metadata includes:

- support summary;
- audience;
- prerequisites;
- estimated completion time;
- last verified product version;
- selected article template;
- related feature suggestion IDs.

### Known Issues

WordPress post type: `sc_known_issue`

Known Issues are public, revisioned, REST-enabled records with:

- investigation and resolution status;
- severity;
- symptoms or exact error-message context;
- workaround;
- resolution;
- first-observed and resolved dates;
- shared product, version, component, issue, and release relationships;
- privacy-safe related feature suggestion relationships.

## Article templates

The editor includes reusable templates for:

- Getting Started;
- How-to Guide;
- Troubleshooting;
- Technical Reference;
- Known Issue Companion.

A selected template is inserted only when the post content is empty. Existing content is never replaced.

## Public Knowledge Base

Archive URL:

`/support-knowledge-base/`

Shortcodes:

`[scfs_support_knowledge_base]`

`[sustainable_catalyst_support_knowledge_base]`

The public interface includes:

- keyword search;
- product filtering;
- component filtering;
- documentation collection filtering;
- article-type filtering;
- current known-issue notices;
- responsive documentation cards;
- paginated results.

The known-issue archive is available at `/known-issues/`.

## REST API

Public published-content endpoints:

- `GET /wp-json/scfs/v1/knowledge-base/schema`
- `GET /wp-json/scfs/v1/knowledge-base/articles`
- `GET /wp-json/scfs/v1/knowledge-base/articles/{id}`
- `GET /wp-json/scfs/v1/knowledge-base/known-issues`
- `GET /wp-json/scfs/v1/knowledge-base/known-issues/{id}`
- `GET /wp-json/scfs/v1/knowledge-base/collections`
- `GET /wp-json/scfs/v1/knowledge-base/templates`

Supported article filters include `search`, `product`, `product_version`, `component`, `issue_type`, `release`, `collection`, and `article_type`.

Known-issue filters additionally include `status`, `severity`, and `active`.

Authenticated editors may request unpublished records with `include_unpublished=1`.

## Relationship and privacy boundary

Support Articles and Known Issues may reference Feature Suggestion IDs and Release taxonomy terms. Public output follows these rules:

- private suggestion content is never returned;
- contact information is never returned;
- a suggestion appears publicly only when it was explicitly approved for the Public Ideas directory;
- authenticated editors may retrieve administrative relationship metadata;
- Contact and Engagement remains responsible for private support cases, sender communication, attachments, and lifecycle management.

## Administration

Open **Feature Suggestions → Knowledge Base** for the foundation dashboard. Support Articles and Known Issues appear as dedicated menu items beneath Feature Suggestions.

After upgrading, visit **Settings → Permalinks** and save once if the new archive routes do not appear immediately. The plugin activation routine normally refreshes rewrite rules automatically.
