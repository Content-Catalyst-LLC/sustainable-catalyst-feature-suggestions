# Sustainable Catalyst Feature Suggestions

A WordPress and FastAPI Product Support and Feedback Platform for collecting feature suggestions, publishing support documentation and known issues, managing public participation, and producing product intelligence.

This repository supports the public feature suggestion workflow at:

```text
/platform/feature-suggestions/
```

The plugin gives visitors a structured way to suggest new platform modules, demo improvements, Research Librarian features, repository upgrades, documentation improvements, accessibility fixes, bug reports, and data/export ideas without requiring GitHub knowledge.

## Where submissions and settings appear

Submitted ideas are stored as the `sc_feature_suggest` custom post type. Review them in WordPress Admin → Feature Suggestions, or open `/wp-admin/edit.php?post_type=sc_feature_suggest`. Configure the plugin in Feature Suggestions → Settings, Settings → Feature Suggestions, or from the plugin-row Settings link on the Installed Plugins screen.


## Shortcode

```text
[sustainable_catalyst_feature_suggestions]
```

Optional category preselection:

```text
[sustainable_catalyst_feature_suggestions category="Research Librarian feature"]
```

## Version 3.4.0 — Documentation and Feature Intelligence

Version 3.4.0 adds Support Article feedback, privacy-minimized documentation-gap detection, protected case-to-article and case-to-suggestion relationships, and a distinct Support Demand signal in opportunity scoring. Contact and Engagement remains the private case-management system; Feature Suggestions stores only public-record relationships and opaque case references.

Administration: **Feature Suggestions → Documentation Intelligence**.

## Version 3.3.0 — Search and Guided Resolution

Version 3.3.0 turns the Support Knowledge Base into an actionable support-resolution environment. Visitors can describe a task or problem, add an exact non-sensitive error fragment, narrow the request by product, version, and component, and receive ranked known issues, support articles, releases, and related public ideas. Current known issues receive deliberate priority.

The public archive now uses `[scfs_guided_resolution]`. The v3.2.0 `[scfs_support_knowledge_base]` shortcode remains available for conventional documentation browsing. Unresolved searches can be transferred through a short-lived, consent-gated token to Contact and Engagement; Feature Suggestions does not create or retain a private support case.

## Version 3.2.0 — Support Knowledge Base Foundation

Version 3.2.0 adds the first-party Sustainable Catalyst Support Knowledge Base directly to Feature Suggestions. The release introduces public Support Articles, Known Issue records, product documentation collections, article types, non-destructive editor templates, a responsive public archive, current-issue notices, and published-content REST APIs.

Public Knowledge Base shortcode:

```text
[scfs_support_knowledge_base]
```

Default archive routes:

```text
/support-knowledge-base/
/known-issues/
```

Support Articles and Known Issues reuse the shared Product, Product Version, Component, Issue Type, and Release taxonomies from v3.1.0. They may be connected to reviewed feature suggestions, but private suggestion text and contact information are never exposed publicly. Contact and Engagement remains the private support-case and communication platform.

Manage the foundation under **Feature Suggestions → Knowledge Base**. See `docs/support-knowledge-base-foundation.md`.

## Version 2.2 highlights


- Python/FastAPI advisory triage service with deterministic fallback.
- Optional Gemini, DeepSeek, and OpenAI structured-analysis adapters.
- Topic, feature-type, platform-area, urgency, impact, effort, and strategic-alignment analysis.
- Sensitive-information and abuse flags, duplicate keys, confidence, rationale, and version metadata.
- Manual per-submission analysis and optional automatic analysis in WordPress.
- Human review remains mandatory; AI never changes workflow or roadmap status automatically.
- Real **Feature Suggestions → Settings** screen.
- Public REST health, schema, and feature-suggestion submission endpoints.
- Protected REST listing, detail, and workflow-update endpoints for authenticated administrators.
- Stable UUIDs, source labels, correlation IDs, and schema-version metadata.
- Shared `scfs_event` and `sc_platform_event` WordPress hooks.
- Optional HMAC-signed webhooks with exponential retry and an integration-status screen.
- Configurable categories, priorities, messages, visible fields, notification email, and saved post status.
- Saves new submissions as **Pending Review** by default to avoid front-end private-post save failures.
- Strict WordPress nonce validation is configurable and off by default because cached public pages often break nonce-based forms.
- Honeypot, IP-hash rate limiting, duplicate detection, link limits, blocked terms, and minimum field lengths.
- Admin workflow metadata: review status, impact score, effort score, roadmap area, GitHub issue URL, and internal notes.
- Admin list filters for category, priority, and workflow status.
- Expanded CSV export for backlog planning and GitHub issue creation.
- Configurable email notifications.
- Non-confidential submission boundary retained.

## Repository structure

```text
wordpress/sustainable-catalyst-feature-suggestions/   WordPress plugin source
backend/                                             FastAPI AI triage service and Render blueprint
docs/                                                Page HTML, site CSS, workflows, and boundaries
examples/                                            Example suggestion payloads
exports/                                             Placeholder for local exports, not production data
dist/                                                Built plugin zip
.github/workflows/                                   Lightweight PHP lint workflow
feature_suggestions_manifest.json                    Repository manifest
```

## WordPress installation

1. Download `dist/sustainable-catalyst-feature-suggestions.zip`.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**.
3. Upload and activate the plugin.
4. Go to **Feature Suggestions → Settings** and configure the form.
5. Create a page at `/platform/feature-suggestions/`.
6. Add the shortcode: `[sustainable_catalyst_feature_suggestions]`.
7. Review **Feature Suggestions → Integration** and configure REST/API-key or webhook settings as needed.
8. Optionally paste the surrounding page HTML from `docs/feature-suggestions-page.html` and site CSS from `docs/feature-suggestions-site.css`.

## Important v2 save fix

The previous version had no settings page and saved public submissions as private records by default. On some WordPress installs that can fail or behave inconsistently from the public front end. Version 2 defaults to **Pending Review**, assigns a safe author when possible, and adds configurable nonce behavior for cached pages.

## Suggested page role

The page should be treated as a public improvement loop for Sustainable Catalyst, not as a support ticket system or professional-advice intake channel.

## Boundaries

Suggestions may inform future development, but submission does not guarantee implementation, attribution, compensation, or response. Visitors should not submit confidential, proprietary, sensitive personal, medical, legal, financial, or regulated information through the form.

## License

GPL-2.0-or-later for WordPress compatibility unless otherwise stated.


## Version 2.8.0 — Public Ideas, Voting, and Participatory Prioritization

Publish approved ideas with `[sc_public_ideas]`, collect advisory support votes, merge duplicates into canonical records, publish official responses, and link roadmap or release updates. Public participation never changes workflow or roadmap status automatically.

## Feedback Intelligence Dashboard

Version 2.3.0 adds an administrator dashboard with date, status, category, platform, and feature-type filters; aggregate AI triage signals; roadmap-candidate ranking; privacy-conscious CSV export; and a protected REST intelligence endpoint. Scores and classifications remain advisory and require human review.


## v2.5.0 Advanced Surveys and Conditional Logic

Create reusable forms and surveys, embed them with `[sc_feedback_form id="slug"]`, store private responses, expose public schemas and response endpoints, export CSV files, and publish shared response events. See `docs/form-survey-builder.md`.


## Survey Research Intelligence (v2.6.0)

The Python service now produces descriptive survey summaries, completion analysis, cross-tabs, scale reliability estimates, open-text themes, warnings, and administrator-reviewed JSON exports.

## Research Librarian contextual feedback

Version 2.7.0 adds a privacy-limited adapter for route ratings, source-card relevance, grounding issues, missing research coverage, and missing tools. Use `[sc_librarian_feedback]`, the `/scfs/v1/librarian/*` REST routes, or the `scfs_research_librarian_feedback` WordPress action.

## Opportunity scoring and roadmap workflow

Version 3.0.0 adds configurable evidence-weighted opportunity scoring, minimum-evidence gates, human-controlled roadmap states, owners, target releases, decision rationales, audit history, and protected JSON handoffs for GitHub, Decision Studio, and Site Intelligence. Scores and public support remain advisory.

## Version 3.1.0 — Product Taxonomy and Platform Integration

Version 3.1.0 adds shared Product, Product Version, Component, Issue Type, and Release taxonomies for Feature Suggestions and the future Support Knowledge Base, Known Issues, and Release Intelligence layers.

Existing suggestions are migrated idempotently from their current category, roadmap area, AI triage, product-version, and target-release metadata. Product context now appears in the public form, authenticated suggestion records, events, AI triage payloads, intelligence filters and charts, and CSV exports.

Administration is available under **Feature Suggestions → Products & Integration**. A complete migration can also be run with:

```bash
wp scfs migrate-product-taxonomies --batch=200
```

The release also defines the private, review-gated `sc-contact-engagement-handoff/1.0` contract. It allows an authorized integration to create or enrich a Contact and Engagement support case without merging private case management into the public feedback platform. See `docs/product-taxonomy-platform-integration.md` and `docs/contact-engagement-handoff-contract.md`.
