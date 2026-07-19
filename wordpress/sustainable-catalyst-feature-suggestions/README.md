# Sustainable Catalyst Product Support and Feedback Platform

## Current release: v5.2.8

**Support Article Content Integrity and Publication Validation**

Version 5.2.8 adds publication-readiness validation to every existing Support Article. It checks article completeness, summaries, product and version metadata, verified-version assignments, heading hierarchy, required editorial sections, template placeholders, internal links, image and table accessibility, relationship context, freshness, and overdue review dates without changing article URLs or publishing content automatically.

- Administration: **Support & Feedback → Article Integrity**
- Article editor panel: **Publication Readiness**
- REST base: `/wp-json/scfs/v1/support-article-integrity/`
- WP-CLI: `wp scfs article-integrity scan`
- Existing Support Article permalink base retained: `/support/guides/`

## Version 5.1.0 — Integrated Knowledge Base and Documentation Library

This release adds a modern expandable Knowledge Base browser, 96 first-party product guides, 32 sample files, article breadcrumbs and sequencing, related documentation, print support, and anonymous usefulness ratings. Documentation is stored as real `sc_support_article` records and organized through hierarchical Documentation Collections.

Version 5.0.0 is a Connected Product Support Operations WordPress plugin for documentation, known issues, releases, guided resolution, feature suggestions, surveys, repository synchronization, editorial governance, product reliability intelligence, and cross-product support orchestration.

Version 5.0.0 adds a Connected Operations workspace with product dossiers, unified module health, a human-approved action queue, daily snapshots, and integrity-protected reports. It also retains public Platform Incident records, a configurable product dependency graph, shared-component relationships, dependency-aware Known Issues and releases, related-product recommendations, and cross-product resolution journeys.

Recommended integration:

```text
[scfs_product_support_center mode="embedded" branding="sustainable-catalyst" default_view="resolve" anchor="support-center"]
```




## Cross-Product Support Orchestration

Use **Support & Feedback → Cross-Product Support** to configure dependencies and publish human-reviewed Platform Incidents. The Product Support Center includes a **Platform status** workspace. Standalone shortcode:

```text
[scfs_cross_product_support product="research-librarian"]
```

Private Contact and Engagement case content is never copied into public orchestration records.


## Support Analytics and Product Reliability Center

Use **Support & Feedback → Product Reliability** to compare support health across products, inspect score dimensions and blockers, review recurring unresolved searches and issues, prioritize documentation gaps, and export integrity-protected reports. Reliability intelligence is aggregate and advisory; it does not expose private case content or make automatic roadmap, publication, or incident decisions.

## Repository and Release Synchronization

Use **Support & Feedback → Repository Sync** to map each Product term to a public GitHub repository, inspect documentation and releases, create review drafts, detect local/remote drift, check links, and export synchronization logs. Optional GitHub credentials and webhook secrets are supplied through constants or environment variables, not WordPress options.

## Documentation Workflow and Editorial Governance

Use **Support & Feedback → Editorial Governance** to assign authors, reviewers, and approvers; manage review queues; require approval before publication; assess documentation standards; approve product versions; schedule publication; manage expiration and review dates; add private editorial comments; and export governance audit history.


## Support Content Operations

Use **Support & Feedback → Content Operations** to onboard products, create missing starter records, import README/CHANGELOG/JSON sources, validate support content, roll back recent import batches, inspect scheduled validation health, and export checksummed product support records. Human review remains mandatory and imported content defaults to draft.

## Embedded Support Center and branding

Version 4.0.2 adds a reliable embedded mode and configurable design tokens for matching the Support Center to the surrounding site. Recommended Sustainable Catalyst page integration:

```text
[scfs_product_support_center mode="embedded" branding="sustainable-catalyst" default_view="resolve"]
```

Branding presets, custom colors, typography, shape, width, navigation density, and embedded-layout controls are available under **Support & Feedback → Support Platform**. Theme inheritance and shortcode-level overrides are supported.

## Unified Product Support Center

Version 4.0.0 adds `[scfs_product_support_center]`, a single product-aware public interface for Guided Resolution, documentation, Known Issues, Release Intelligence, public ideas and advisory voting, feature suggestions, surveys, and private-support continuation.

Release records use the public `sc_release_record` post type. Contact and Engagement remains the private case, communication, and document system of record.

## Support Knowledge Base Foundation

Version 3.2.0 adds:

- public `sc_support_article` Support Articles;
- public `sc_known_issue` Known Issue records;
- product documentation collections and article types;
- Getting Started, How-to, Troubleshooting, Technical Reference, and Known Issue Companion templates;
- `[scfs_support_knowledge_base]`;
- canonical Support Articles at `/support/#knowledge-base`; legacy Knowledge Base landing routes redirect there;
- `/known-issues/` archive;
- published-content REST endpoints under `/wp-json/scfs/v1/knowledge-base/*`;
- related release and privacy-safe feature-suggestion relationships.

Manage these records under **Support & Feedback → Knowledge Base**, **Support Articles**, and **Known Issues**.

## Shortcode

```text
[sustainable_catalyst_feature_suggestions]
```

Optional category preselection:

```text
[sustainable_catalyst_feature_suggestions category="Research Librarian feature"]
```

## What v2.1 does

- Renders a configurable public feature suggestion form.
- Exposes public REST health, schema, and optional submission endpoints.
- Exposes authenticated administrator endpoints for listing, reading, and updating suggestions.
- Publishes privacy-minimized shared events through `scfs_event` and `sc_platform_event`.
- Supports optional HMAC-signed webhooks with retry delivery and integration diagnostics.
- Assigns stable submission UUIDs and accepts source and correlation metadata.
- Saves submissions as WordPress Feature Suggestion records.
- Adds a real **Support & Feedback → Settings** screen, plus a standalone fallback settings route for hosts with unusual admin capabilities.
- Lets you configure categories, priorities, messages, visible fields, notification email, saved post status, rate limits, duplicate detection, link limits, blocked terms, and nonce behavior.
- Defaults new submissions to **Pending Review** instead of private records, which avoids common front-end save failures on some WordPress installs.
- Keeps strict WordPress nonce validation off by default because cached public pages can break nonce-based forms.
- Uses honeypot spam protection, IP-hash rate limiting, duplicate fingerprinting, minimum field lengths, link limits, and optional blocked terms.
- Adds admin workflow metadata: review status, impact score, effort score, roadmap area, GitHub issue URL, and internal notes.
- Adds list-table filters for category, priority, and workflow status.
- Adds a richer CSV export for backlog planning and GitHub issue workflows.
- Sends configurable email notifications.
- Preserves the non-confidential submission boundary.

## Suggested page

Use the shortcode on:

```text
/platform/feature-suggestions/
```

## Recommended first settings

After activation, go to:

```text
Support & Feedback → Settings
```

If WordPress blocks that route, use the plugin-row Settings link or this direct fallback URL:

```text
/wp-admin/admin.php?page=scfs-settings-standalone
```

Recommended defaults for Sustainable Catalyst:

- Default saved status: **Pending Review**
- Strict nonce validation: **Off** unless the page is not cached
- Email notifications: **On**
- Max submissions per hour: **5**
- Max submissions per day: **20**
- Duplicate window: **24 hours**
- Maximum links: **4**

## Boundary note

The form is for non-confidential suggestions only. Visitors should not submit confidential, proprietary, sensitive personal, medical, legal, financial, or regulated information.


## Where submissions and settings appear

Submitted ideas are stored as the `sc_feature_suggest` custom post type. Review them in WordPress Admin → Support & Feedback. Configure the plugin from Support & Feedback → Settings, Settings → Support & Feedback, the plugin-row Settings link on the Installed Plugins screen, or `/wp-admin/admin.php?page=scfs-settings-standalone`.


## Feedback Intelligence Dashboard

Version 2.3.0 adds an administrator dashboard with date, status, category, platform, and feature-type filters; aggregate AI triage signals; roadmap-candidate ranking; privacy-conscious CSV export; and a protected REST intelligence endpoint. Scores and classifications remain advisory and require human review.


## v2.5.0 Advanced Surveys and Conditional Logic

Create reusable forms and surveys, embed them with `[sc_feedback_form id="slug"]`, store private responses, expose public schemas and response endpoints, export CSV files, and publish shared response events. See `docs/form-survey-builder.md`.


Version 2.6.0 adds Survey Intelligence under the Feature Suggestions administration menu.


## Public ideas

Use `[sc_public_ideas]` to publish moderator-approved ideas with advisory support voting, official responses, roadmap states, duplicate merging, and release links.

## Opportunity scoring and roadmap workflow

Version 3.0.0 adds configurable evidence-weighted opportunity scoring, minimum-evidence gates, human-controlled roadmap states, owners, target releases, decision rationales, audit history, and protected JSON handoffs for GitHub, Decision Studio, and Site Intelligence. Scores and public support remain advisory.


## Product taxonomy and platform integration

Version 3.1.0 registers shared Product, Product Version, Component, Issue Type, and Release taxonomies; migrates existing suggestions; adds product-aware analytics and exports; and defines a protected Contact and Engagement support handoff contract. Manage the layer from **Support & Feedback → Products & Integration**.

## Guided Resolution

Use `[scfs_guided_resolution]` for the product-aware support workflow. It supports version and component filtering, short error fragments, current known-issue prioritization, suggested articles, releases, related public ideas, and a consent-gated Contact and Engagement handoff. The existing `[scfs_support_knowledge_base]` shortcode remains supported.
