# Sustainable Catalyst Feature Suggestions

Version 3.3.0 is a Product Support and Feedback WordPress plugin for collecting, storing, triaging, and exporting structured Sustainable Catalyst feature suggestions.

## Support Knowledge Base Foundation

Version 3.2.0 adds:

- public `sc_support_article` Support Articles;
- public `sc_known_issue` Known Issue records;
- product documentation collections and article types;
- Getting Started, How-to, Troubleshooting, Technical Reference, and Known Issue Companion templates;
- `[scfs_support_knowledge_base]`;
- `/support-knowledge-base/` and `/known-issues/` archives;
- published-content REST endpoints under `/wp-json/scfs/v1/knowledge-base/*`;
- related release and privacy-safe feature-suggestion relationships.

Manage these records under **Feature Suggestions → Knowledge Base**, **Support Articles**, and **Known Issues**.

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
- Adds a real **Feature Suggestions → Settings** screen, plus a standalone fallback settings route for hosts with unusual admin capabilities.
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
Feature Suggestions → Settings
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

Submitted ideas are stored as the `sc_feature_suggest` custom post type. Review them in WordPress Admin → Feature Suggestions. Configure the plugin from Feature Suggestions → Settings, Settings → Feature Suggestions, the plugin-row Settings link on the Installed Plugins screen, or `/wp-admin/admin.php?page=scfs-settings-standalone`.


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

Version 3.1.0 registers shared Product, Product Version, Component, Issue Type, and Release taxonomies; migrates existing suggestions; adds product-aware analytics and exports; and defines a protected Contact and Engagement support handoff contract. Manage the layer from **Feature Suggestions → Products & Integration**.

## Guided Resolution

Use `[scfs_guided_resolution]` for the product-aware support workflow. It supports version and component filtering, short error fragments, current known-issue prioritization, suggested articles, releases, related public ideas, and a consent-gated Contact and Engagement handoff. The existing `[scfs_support_knowledge_base]` shortcode remains supported.
