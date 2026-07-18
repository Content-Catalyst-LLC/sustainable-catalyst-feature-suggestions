# Sustainable Catalyst Feature Suggestions

## Version 5.1.0 — Integrated Knowledge Base and Documentation Library

Version 5.1.0 turns the Support Center Knowledge Base into a modern, first-party documentation library. A clean expandable directory organizes 96 detailed guides across 16 products, with standardized sections, search, breadcrumbs, previous/next navigation, related guides, direct sample downloads, print support, and anonymous usefulness ratings.

The documentation corpus is bundled with the plugin and imported through an idempotent, editorially governed migration. Existing human edits are protected. Knowledge Base navigation remains visible even before content is available, so Support never loses its documentation pathway. No legacy KnowledgeBuilder runtime code is included.

See `docs/integrated-knowledge-base-documentation-library.md`.

## Version 5.0.0 — Connected Product Support Operations Platform

Version 5.0.0 consolidates the complete public support and operational roadmap into one governed operations layer. It adds a Connected Operations workspace, product operations dossiers, unified module health, human-approved action queues, daily snapshots, and integrity-protected reports while preserving each specialist module as the source of truth.


A WordPress and FastAPI Product Support and Feedback Platform for collecting feature suggestions, publishing support documentation and known issues, managing public participation, and producing product intelligence.

## Version 5.0.0 — Cross-Product Support Orchestration

Version 5.0.0 coordinates public support when an incident, dependency, shared component, release, or resolution path spans more than one Sustainable Catalyst product.

Open **Feature Suggestions → Cross-Product Support** to configure the product dependency graph, publish governed Platform Incident records, review shared-component relationships, and manage related-product support pathways. The Product Support Center gains a **Platform status** workspace, and `[scfs_cross_product_support]` can render the orchestration view independently.

The orchestration layer can recommend related products and build public resolution journeys. It cannot declare incidents automatically, block releases automatically, change the roadmap, expose private case content, or create private support cases.

See `docs/cross-product-support-orchestration.md`.

## Version 4.3.0 — Repository and Release Synchronization

Version 4.3.0 maps Sustainable Catalyst products to public GitHub repositories and inspects README files, CHANGELOG files, documentation directories, and published GitHub releases. Remote changes become approval-gated WordPress drafts with commit, path, URL, and content-hash provenance.

Open **Feature Suggestions → Repository Sync** to configure product mappings, inspect remote sources, create review drafts, detect local/remote documentation drift, check public links, and export synchronization logs. Published records are never overwritten, conflicting local edits create review copies, private repository access is disabled, and automatic approval or publication remains prohibited.

See `docs/repository-release-synchronization.md`.

## Version 4.2.0 — Documentation Workflow and Editorial Governance

Version 4.2.0 adds a controlled editorial lifecycle for Support Articles, Known Issues, and Release Records. Assign authors, reviewers, and approvers; submit records into review queues; request changes; approve version-specific documentation; schedule publication; expire outdated records; and maintain private editorial comments and audit history.

See `docs/documentation-workflow-editorial-governance.md`.

## Version 4.1.1 — Content Operations Reliability Patch

Version 4.1.1 hardens **Feature Suggestions → Content Operations** with recoverable import batches, malformed-source inspection, refined duplicate detection, restored starter drafts, product-context integrity checks, operation progress, scheduled validation health, checksummed exports, accessibility improvements, and administrator capability controls.

Successful imports retain a time-limited **Roll back batch** action that moves created records to WordPress Trash. Strict import validation can roll back the whole batch automatically when any record fails. Private Contact and Engagement case content remains excluded.

See `docs/support-content-operations-reliability.md`.

## Version 4.0.2 — Navigation and Embedded Pathway Reliability Patch

Version 4.0.2 makes the embedded Support Center behave like one application. Internal navigation changes the workspace without returning visitors to the top of the page, updates browser history and direct URLs, preserves product context, and falls back to anchored full-page links when JavaScript or the REST endpoint is unavailable.

Recommended Support page shortcode:

```text
[scfs_product_support_center mode="embedded" branding="sustainable-catalyst" default_view="resolve" anchor="support-center"]
```

The optional “Choose another support pathway” cards now use protected horizontal typography and responsive card widths, so page or theme CSS cannot collapse headings into letter-by-letter columns.

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

## Version 4.0.2 — Embedded Support Center Interface Reliability Patch

Version 4.0.2 adds a site-native embedded mode and a configurable branding system for the unified Support Center. The recommended shortcode for the Sustainable Catalyst Support page is:

```text
[scfs_product_support_center mode="embedded" branding="sustainable-catalyst" default_view="resolve"]
```

Administrators can select **Platform**, **Sustainable Catalyst**, **Inherit active theme**, or **Custom** branding under **Feature Suggestions → Support Platform**. Custom controls cover colors, typography, border radius, shadow, maximum width, navigation columns, embedded chrome, and zero-value status visibility. The same scoped tokens flow into Guided Resolution, Knowledge Base, Known Issues, releases, public ideas, suggestion forms, and surveys.

Embedded mode removes duplicate application chrome, uses the containing page width, preserves product/view context, and includes CSS safeguards against broad Astra or page-level link and button rules. See `docs/embedded-support-center-branding.md`.

## Version 4.0.0 — Product Support and Feedback Platform

Version 4.0.0 introduces the unified public Support Center:

```text
[scfs_product_support_center]
```

The Support Center combines Guided Resolution, the Support Knowledge Base, Known Issues, Release Intelligence, feature suggestions, moderated public ideas, advisory voting, and open surveys. A shared product selector carries context across the public workflow.

The new public `sc_release_record` post type publishes release lifecycle state, support and compatibility notes, highlights, known limitations, product context, and privacy-safe relationships to documentation, Known Issues, and public ideas.

Feature Suggestions remains the public support and feedback system. Contact and Engagement remains the private case, communication, document, and lifecycle system of record. Automatic private case creation is disabled.

Administration: **Feature Suggestions → Support Platform**. See `docs/product-support-feedback-platform.md`.

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
