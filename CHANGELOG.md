# Changelog

## 5.6.0 — Feedback Intelligence and Product Signals

- Added administrator-only product signal intelligence.
- Added deterministic cross-signal scoring and evidence clusters.
- Added protected WordPress REST, WP-CLI, CSV, scheduled snapshot, and FastAPI parity surfaces.
- Preserved all existing identifiers, records, URLs, and human-authority boundaries.

## 5.5.0 — Support Content Operations and Editorial Governance

- Added shared content and technical ownership across Support Articles, Known Issues, and Release Records.
- Added verification states, notes, review cadence, last-verified and next-review dates, bounded history, and supersession relationships.
- Added a unified Content Operations queue that combines editorial workflow, article integrity, ownership, freshness, and verification evidence.
- Added bulk governance actions, CSV export, daily scans, protected REST routes, WP-CLI commands, FastAPI parity, schema, examples, tests, and release packaging.
- Preserved all existing CPTs, URLs, routes, shortcodes, settings, metadata, and records without a database migration.

## 5.4.0 — Known Issues and Release Intelligence Integration

- Connected Known Issues to affected versions, components, target releases, fixed releases, related Support Articles, and changelog evidence.
- Added derived open/resolved issue coverage for Release Records and advisory relationship-health validation.
- Integrated operational context into the Support Center, Unified Support Search, and publication-style issue/release pages.
- Added WordPress REST, administrative synchronization, FastAPI parity, schemas, examples, tests, and release packaging.
- Preserved all existing CPTs, URLs, routes, shortcodes, settings, metadata, and records without a database migration.

## 5.3.0 — Unified Search and Guided Resolution

- Unified Support Discovery and Guided Resolution in the public Support Center.
- Added ordered resolution journeys across Known Issues, Support Articles, releases, public improvements, and private handoff.
- Added WordPress and FastAPI unified-support contracts and endpoints.
- Preserved all legacy routes, shortcodes, post types, URLs, settings, and data.

# Changelog

## 5.2.9 — Support Discovery, Navigation, and Search Quality

- Added weighted, version-aware Support Article search and deterministic synonym expansion.
- Added browser breadcrumbs, removable active filters, explicit relevance/recent/title sorting, and no-results recovery.
- Added public discovery REST endpoints and deterministic FastAPI search parity.
- Preserved all existing shortcodes, routes, CPTs, settings, data, and Support Article URLs.


## 5.2.8 — Support Article Content Integrity and Publication Validation

- Adds a versioned Support Article publication-readiness engine for the existing `sc_support_article` post type.
- Validates titles, article length, excerpts or summaries, products, versions, components, Article Types, collections, and verified-version assignments.
- Detects invalid heading hierarchy, missing required editorial sections, unreplaced template placeholders, invalid internal links, missing image alternative text, uncaptained figures, and tables without header cells.
- Evaluates release, Known Issue, article, and reviewed-suggestion relationship context.
- Flags stale content and overdue editorial reviews.
- Stores advisory 0–100 scores and Publication ready, Review recommended, Needs work, Publication blocked, or Not validated states.
- Adds **Support & Feedback → Article Integrity**, an article-editor readiness panel, list-table status column and filter, bulk validation, CSV export, REST routes, and WP-CLI commands.
- Adds deterministic FastAPI integrity assessment and capability endpoints.
- Preserves all existing Support Article URLs, CPTs, shortcodes, REST namespace, settings, data, publication rendering, and Support Center integration.
- Does not rewrite content, publish automatically, expose private editorial notes, or create private support cases.

## 5.2.7 — Support Center Production Integration and Interface Hardening

- Automatically integrates the unified Support Center into the published `/support/` page when a shortcode is missing.
- Consolidates legacy Knowledge Base shortcodes into the unified Support Center.
- Loads assets before page content, suppresses duplicate interfaces, and provides stable Support Center anchors.
- Adds abortable support navigation, dynamic Knowledge Base reinitialization, responsive hardening, route-loop protection, and administrator diagnostics.
- Preserves all existing Support Article URLs, data, routes, settings, and public rendering.

## 5.2.6 — Unified Support Center, Embedded Knowledge Base Browser, and Legacy Knowledge Base Route Consolidation

- Makes the main `/support/` page the canonical public Support Center and embeds the complete two-panel Support Article browser directly below Guided Resolution and inside the Support overview.
- Renames the public documentation navigation item to Support Articles and removes the now-redundant documentation pathway card.
- Consolidates `/support/knowledge-base/` and `/support-documentation/` into `/support/?scfs_support_view=documentation#knowledge-base` using permanent redirects.
- Preserves Knowledge Base search and filter parameters during legacy-route redirects.
- Replaces plugin-managed legacy page content with a compact compatibility fallback instead of a second browser.
- Preserves all Support Article URLs under `/support/guides/`, CPTs, REST routes, options, shortcodes, taxonomies, metadata, relationships, and publication-parity rendering.
- Replaces the nested results `<main>` landmark with an embeddable region for valid page structure.
- Adds v5.2.6 route, unified-interface, backward-compatibility, CSS, manifest, and package validation.

## 5.2.5 — Product Support and Feedback Platform Rebrand, Knowledge Base Rendering Repair, Library Browser Redesign, and Publication-Parity Support Articles

- Rebrands the public plugin and WordPress navigation as the Sustainable Catalyst Product Support and Feedback Platform while retaining the `sustainable-catalyst-feature-suggestions` slug, text domain, main PHP class, `scfs_*` identifiers, custom post types, settings, REST routes, database records, and existing URLs.
- Repairs the dedicated `/support/knowledge-base/` page when its shortcode was saved only inside an HTML comment, and bundles a complete executable page layout for new or empty routes.
- Replaces the large nested-folder browser with a compact two-panel library: Products, Versions, and Categories on the left; Support Article search, filters, and results on the right.
- Adds an All Products view while preserving product, version, section, component, type, and search query parameters.
- Removes duplicate Support Article decoration so each article has one publication masthead and one editorial metadata layer.
- Renders Support Articles with Sustainable Catalyst publication typography, spacing, heading hierarchy, cream information panels, code blocks, tables, figures, related-content cards, and previous/next navigation.
- Presents verified version, product, component, updated date, reading time, related releases, related known issues, related articles, and existing feedback controls as publication metadata rather than dashboard boxes.
- Adds complete page and renderer CSS coverage, responsive behavior, print behavior, route-repair tests, compatibility tests, and HTML-class-to-CSS validation.

## 5.2.4 - 2026-07-18

- Repaired the dedicated Knowledge Base route and removed Support Article rewrite collisions.
- Added automatic page recovery and upgrade-safe rewrite refresh.

## 5.2.4 - 2026-07-18

- Query real published Support Article records for compact and full Knowledge Base interfaces.
- Expand compact categories inline to reveal real article titles, summaries, metadata, and permalinks.
- Remove constructed article destinations and skip records without valid WordPress permalinks.
- Add server-rendered search across article content, metadata, and support taxonomies.
- Replace the long full-page accordion with a refined two-column product navigator and documentation results pane.
- Preserve product and category state through query-string deep links.

## 5.2.2 - 2026-07-17

### Changed
- Matched Knowledge Base article presentation to Sustainable Catalyst publication articles while retaining support-specific metadata and controls.
- Repaired the article title/header and removed duplicate theme-generated headings.
- Expanded the Knowledge Base directory, product folders, and section folders by default.
- Added publication-style tables, code blocks, contents, related guides, and previous/next navigation.

## 5.1.0 — Integrated Knowledge Base and Documentation Library

- Added a modern expandable Knowledge Base directory modeled on Sustainable Catalyst's clean Library interaction pattern.
- Bundled and integrated 96 detailed HTML Support Articles across 16 products.
- Added 32 direct-download CSV and JSON sample files without Media Library MIME dependencies.
- Added standardized product and documentation-section folder navigation.
- Added persistent Knowledge Base navigation in the Support Center.
- Added breadcrumbs, previous/next navigation, related guides, article printing, and product return paths.
- Modernized anonymous visitor usefulness ratings with optional reasons and comments.
- Added idempotent import, manual-edit protection, taxonomy repair, validation, and editorially scoped system publication.
- Preserved existing Support Articles, Known Issues, Releases, Feature Suggestions, surveys, guided resolution, and private-support boundaries.
- Excluded all legacy KnowledgeBuilder runtime code and database assumptions.

## 5.0.0 — Connected Product Support Operations Platform

- Added the Connected Operations administration workspace.
- Added product operations dossiers and unified module health.
- Added a human-approved operations action queue.
- Added daily operations snapshots and SHA-256 report integrity.
- Added protected WordPress REST and FastAPI advisory endpoints.
- Preserved specialist-module authority and the Contact and Engagement privacy boundary.

## 4.5.0 — Cross-Product Support Orchestration

- Added the public `sc_platform_incident` post type with status, severity, summaries, workarounds, timestamps, and shared product context.
- Added a configurable product dependency graph with dependency, integration, shared-component, routing, and data-provider relationships.
- Added multi-product orchestration metadata for Support Articles, Known Issues, and Release Records.
- Added dependency-aware Known Issue and release relationships.
- Added related-product support recommendations and product handoff pathways.
- Added cross-product resolution journeys with Guided Resolution, platform status, documentation, related-product, and private-support steps.
- Added a Platform status workspace to the unified Product Support Center.
- Added the `[scfs_cross_product_support]` shortcode and public incident summaries.
- Added protected administration and snapshot APIs plus public schema, incident, route, and journey APIs.
- Added deterministic FastAPI incident-impact, route-recommendation, journey-planning, and report-integrity endpoints.
- Preserved human review and disabled automatic incident declaration, release blocking, roadmap changes, and private case creation.

## 4.4.0 — Support Analytics and Product Reliability Center

- Added a protected Product Reliability administration workspace.
- Added bounded 0–100 product reliability scoring across seven weighted dimensions.
- Added support-resolution rates and repeated unresolved-query clustering.
- Added documentation usefulness trends and privacy-safe support-demand counts.
- Added active, critical, and recurring Known Issue analysis.
- Added release-readiness and support-content-readiness aggregation.
- Added repository drift, broken-link, and editorial-governance health signals.
- Added documentation-gap prioritization and explicit operational blockers.
- Added daily product snapshots and product reliability trend history.
- Added checksum-protected CSV and JSON reliability reports.
- Added protected WordPress REST routes and deterministic FastAPI scoring endpoints.
- Preserved the private Contact and Engagement boundary and disabled automatic roadmap or incident decisions.

## 4.3.0 — Repository and Release Synchronization

- Added public GitHub repository mapping for Product taxonomy terms.
- Added README, CHANGELOG, documentation-directory, release-note, and GitHub release inspection.
- Added approval-gated draft creation with published-record overwrite protection.
- Added local-edit, remote-update, and conflict-aware documentation drift detection.
- Added repository URL, source path, commit, external ID, and content-hash provenance.
- Added Product Version and Release taxonomy assignment for synchronized releases.
- Added public-link health checks and bounded synchronization logs.
- Added daily inspection, optional draft ingestion, and signed webhook inspection queues.
- Added FastAPI candidate, drift, release-plan, and link-health evaluators.
- Kept private repository access, automatic approval, and automatic publication disabled.

## 4.2.0 — Documentation Workflow and Editorial Governance

- Added accountable author, reviewer, and approver assignments for Support Articles, Known Issues, and Release Records.
- Added Draft, Submitted, In Review, Changes Requested, Approved, Scheduled, Published, Expired, and Archived workflow states.
- Added configurable separation of author and approver responsibilities.
- Added publication gating so unapproved public records return to Pending Review.
- Added version-specific approval using shared Product Version terms.
- Added change summaries, private editorial comments, and bounded internal audit history.
- Added documentation standards scoring for titles, content, summaries, product context, required sections, provenance, and change summaries.
- Added scheduled publication, content expiration, review-due reminders, and cron health reporting.
- Added an Editorial Governance dashboard, protected REST operations, CSV audit export, and WP-CLI editorial commands.
- Added deterministic FastAPI transition evaluation, standards scoring, and queue summarization.
- Preserved v4.1.1 import reliability and the public/private Contact and Engagement boundary.

## 4.1.1 — Content Operations Reliability Patch

- Added time-limited import-batch rollback that moves created records to WordPress Trash.
- Added automatic strict-validation rollback when any imported record fails.
- Added empty-file, size, UTF-8, null-byte, JSON-shape, and record-count validation.
- Added source SHA-256 checksums and per-record import-integrity hashes.
- Added same-source, normalized-title, and same-product duplicate refinement.
- Added starter-record recovery from Trash and equivalent-starter adoption.
- Added product-version and component relationship integrity validation.
- Added operation job progress, richer import notices, and recovery logs.
- Added a locked daily validation sweep with cron health reporting.
- Added deterministic export ordering, record counts, and SHA-256 integrity metadata.
- Added administrator and multisite capability boundaries.
- Added accessible progress, focus, tables, fieldsets, and rollback confirmation.
- Added FastAPI source inspection, recovery planning, and export verification endpoints.
- Preserved draft-first imports, mandatory human review, and the private Contact and Engagement boundary.

## 4.1.0 — Support Content Operations and Product Onboarding

- Added product onboarding profiles for ownership, repository, documentation, support URL, current version, components, and known-issue review.
- Added deterministic product support-readiness scoring with criteria, blockers, and freshness signals.
- Added idempotent starter drafts for Getting Started, configuration, workflows, troubleshooting, technical reference, and release intelligence.
- Added README, Markdown/text documentation, CHANGELOG, release-notes, and JSON import.
- Added source provenance, version, lifecycle, verification, review-due, supersession, import-batch, and fingerprint metadata.
- Added duplicate, stale-content, missing-context, lifecycle, and supersession validation.
- Added product-scoped JSON export, protected REST routes, and WP-CLI onboarding/validation commands.
- Added optional suppression of empty public support sections until content is published.
- Added FastAPI support-readiness scoring and source-import planning.
- Preserved mandatory human review and the private Contact and Engagement boundary.

## 4.0.2 — Navigation and Embedded Pathway Reliability Patch

- Added in-place Support Center workspace switching through a public, read-only view endpoint.
- Added browser back/forward history and direct-link support for every Support Center view.
- Added anchored no-JavaScript fallbacks so direct navigation returns to `#support-center`.
- Preserved product and survey context while switching views and applying product filters.
- Added dynamic initialization for surveys and preloaded public-ideas, form, and suggestion assets.
- Repaired “Choose another support pathway” cards with protected horizontal text, stable card widths, and responsive one-, two-, and three-column layouts.
- Added loading, accessibility announcement, active-navigation, and REST-failure fallback behavior.
- Preserved v4.0.1 branding controls and the v4.0.0 public/private support boundary.

## 4.0.1 — Embedded Support Center Interface Reliability Patch

- Added a true `mode="embedded"` Support Center rendering mode for use inside designed WordPress pages.
- Added configurable branding presets for the platform default, Sustainable Catalyst, active-theme inheritance, and custom token sets.
- Added admin controls for accent, contrast, ink, muted text, surface, soft surface, border, success, warning, danger, typography, radius, shadow, width, and navigation columns.
- Added shortcode-level branding and layout overrides without requiring page-specific CSS.
- Suppressed duplicate embedded heroes, all-zero status rows, long navigation descriptions, and repeated overview pathways by default.
- Preserved the selected Support Center view when applying product context.
- Scoped application navigation, buttons, forms, cards, Guided Resolution, Knowledge Base, public ideas, suggestions, and surveys against broad theme CSS collisions.
- Added responsive one-, two-, three-, and four-column navigation safeguards plus accessible active-view state and screen-reader headings.
- Added cache-safe `filemtime()` asset versioning so branding changes are not hidden by stale CSS.
- Added branding-token and embedded-render regression tests while preserving the public/private support boundary.

## 4.0.0 — Product Support and Feedback Platform

- Added the unified `[scfs_product_support_center]` public interface and `[scfs_support_center]` legacy alias.
- Combined Guided Resolution, Knowledge Base browsing, Known Issues, Release Intelligence, public ideas, voting, suggestions, surveys, and private-support continuation.
- Added public `sc_release_record` Release Intelligence records with lifecycle, compatibility, highlights, limitations, and typed relationships.
- Added product-aware routing across public support modules.
- Added product-support schema, overview, releases, products, handoff-schema, and protected snapshot REST endpoints.
- Added deterministic FastAPI support-state summarization and release-readiness scoring.
- Preserved Contact and Engagement as the private case, communication, and document system of record.
- Kept voting, survey evidence, search demand, AI, and scoring advisory with mandatory human review.

## 3.4.0 — Documentation and Feature Intelligence

- Added Support Article helpfulness feedback with privacy redaction and aggregate metrics.
- Added Documentation Gap records generated from failed searches and negative article feedback.
- Added deterministic documentation-gap scoring and human-controlled gap workflow states.
- Added protected case-to-article and case-to-suggestion relationship records for Contact and Engagement.
- Added a Support Demand dimension to opportunity scoring.
- Added documentation intelligence REST endpoints, CSV export, dashboard, and FastAPI scoring models.
- Preserved Contact and Engagement as the private support-case system of record.

## 3.3.0 — Search and Guided Resolution

- Added a product-aware guided-resolution workflow with product, version, and component filters.
- Added deterministic error-message, symptom, alias, and exact-phrase matching.
- Prioritized current known issues using lifecycle status, severity, taxonomy context, and editorial controls.
- Grouped results across known issues, support articles, releases, and explicitly public feature suggestions.
- Added global synonym mappings plus per-record aliases, error signatures, promotion, and editorial priority.
- Added privacy-minimized search analytics, failed-search intelligence, and result-view tracking without IP storage.
- Added consent-gated unresolved-search handoff tokens for the private Contact and Engagement workflow.
- Added guided-resolution REST schema, search, handoff, and protected analytics endpoints.
- Added deterministic FastAPI ranking and pinned httpx for reliable TestClient validation.
- Preserved the v3.2.0 Knowledge Base shortcode while upgrading the default archive to Guided Resolution.

## 3.2.0 — Support Knowledge Base Foundation

- Added the public `sc_support_article` Support Article post type.
- Added hierarchical product documentation collections and support article types.
- Added non-destructive Getting Started, How-to, Troubleshooting, Technical Reference, and Known Issue Companion templates.
- Added the public `sc_known_issue` Known Issue post type with status, severity, symptom, workaround, resolution, and lifecycle dates.
- Added the `[scfs_support_knowledge_base]` public search and filtering interface.
- Added public Knowledge Base and Known Issues archive templates.
- Added active known-issue notices and responsive documentation cards.
- Added Support Article, Known Issue, collection, template, and schema REST endpoints.
- Added shared Product, Product Version, Component, Issue Type, and Release relationships to documentation records.
- Added privacy-safe relationships to reviewed feature suggestions; private text and contact data remain protected.
- Added a Knowledge Base administration dashboard and list-table filters.
- Added backend Knowledge Base capability metadata.
- Preserved Contact and Engagement as the private support-case, communication, document, and lifecycle platform.

## 3.1.0 — Product Taxonomy and Platform Integration

- Added shared Product, Product Version, Component, Issue Type, and Release taxonomies.
- Seeded canonical Sustainable Catalyst products, reusable components, and issue classifications.
- Added stable term identifiers, lifecycle status metadata, and product relationship metadata.
- Added idempotent migration of existing suggestions from roadmap, category, AI triage, version, and release metadata.
- Added incremental upgrade-safe migration scheduling, a migration dashboard, coverage reporting, and a WP-CLI migration command.
- Added optional product-context fields to the public suggestion form.
- Added product context to REST records, shared events, AI triage payloads, intelligence filters, dashboards, and CSV exports.
- Added public taxonomy schema and term endpoints.
- Added the `sc-contact-engagement-handoff/1.0` private support handoff contract.
- Added a protected, human-review-gated Contact and Engagement handoff endpoint.
- Preserved the boundary between public product feedback and private support case lifecycle management.

## 3.0.0 — Sustainable Catalyst Feedback and Participation Platform

- Added the unified Platform Command Center.
- Added module health and release-readiness checks.
- Added privacy-oriented governance and retention settings.
- Added contact anonymization and dry-run retention reporting.
- Added platform governance audit history.
- Added administrator-only platform snapshot exports and REST endpoints.
- Added backend platform-capabilities endpoint.
- Consolidated the v2.x feedback, survey, intelligence, participation, and roadmap layers into a stable major release.
- Preserved human approval boundaries for AI, public voting, roadmap states, and retention actions.


## 2.9.0 — Opportunity Scoring and Roadmap Workflow

- Added configurable evidence-weighted opportunity scoring.
- Added demand, impact, alignment, evidence, public-interest, readiness, and effort dimensions.
- Added minimum-evidence gates and configurable roadmap-candidate thresholds.
- Added administrator-controlled roadmap states, owners, target releases, and decision rationales.
- Added versioned score records and per-opportunity audit histories.
- Added protected JSON handoff exports for GitHub, Decision Studio, and Site Intelligence.
- Added authenticated opportunity REST endpoints and shared platform events.
- Preserved mandatory human approval for all roadmap decisions.

## 2.8.0 — Public Ideas, Voting, and Participatory Prioritization

- Added moderation-controlled public idea publication.
- Added advisory support voting with browser/session duplicate protection.
- Added duplicate merging into canonical public ideas.
- Added official responses, roadmap states, and release links.
- Added public ideas REST endpoints and `idea.supported` shared events.
- Preserved human control over publication and prioritization.

# Changelog

## 2.7.0 — Research Librarian Contextual Feedback Integration

- Added contextual route, source-card, grounding, missing-topic, missing-source, and missing-tool feedback.
- Added the `[sc_librarian_feedback]` shortcode and accessible public form.
- Added typed REST schema, submission, handoff, and receipt-protected status endpoints.
- Added a direct WordPress action contract for Research Librarian.
- Added privacy-minimized `librarian.feedback_submitted` shared events.
- Added duplicate protection, stable UUIDs, receipt tokens, context metadata, and administrator review panels.
- Preserved independent plugin operation and mandatory human review.

- Added deterministic survey statistics and completion analysis.
- Added descriptive cross-tabs and scale reliability estimates.
- Added open-text theme coding with confidence and methodology labels.
- Added WordPress Survey Intelligence dashboard, protected REST actions, and JSON exports.
- Added sample-size, missingness, and sparse-cell warnings.
- Added `survey.analysis_completed` shared events.
- Preserved human review and non-inferential research boundaries.

# Changelog

## 2.5.0 — Advanced Surveys and Conditional Logic

- Added reusable form and survey custom post types.
- Added an ordered field builder with 13 foundational field types.
- Added `[sc_feedback_form]` accessible public embeds.
- Added private response storage and per-instrument CSV exports.
- Added published schema and response REST endpoints.
- Added stable response UUIDs, schema versions, and shared platform events.
- Preserved separation between feature suggestions and general form responses.

## 2.3.0

- Added Feedback Intelligence Dashboard.
- Added aggregate workflow, category, platform, feature-type, topic, sentiment, and suggested-action views.
- Added filtered opportunity ranking with explicit human-review boundaries.
- Added privacy-conscious intelligence CSV export.
- Added protected `/scfs/v1/intelligence` REST endpoint.
- Updated backend and plugin versions.


## 2.2.0 - 2026-07-10

- Added a Python/FastAPI AI triage and classification service.
- Added deterministic local classification so the service remains useful without a paid AI provider.
- Added optional Gemini, DeepSeek, and OpenAI provider adapters with structured JSON validation and safe fallback.
- Added topic, feature-type, platform-area, sentiment, urgency, impact, effort, and strategic-alignment analysis.
- Added sensitive-information, possible-secret, medical-information, and abuse flags.
- Added duplicate keys for later near-duplicate clustering.
- Added confidence, rationale, provider, model, analysis version, and mandatory human-review metadata.
- Added WordPress backend URL, service key, timeout, and automatic-analysis settings.
- Added per-submission AI Triage review panel and analyze/reanalyze action.
- Added protected WordPress analysis and AI-status REST endpoints.
- Added `feedback.classified` shared events after successful analysis.
- Added Render deployment blueprint, environment template, backend documentation, and automated tests.
- Preserved original submissions and prohibited automatic roadmap or workflow decisions.

## 2.1.0 - 2026-07-10

- Added REST namespace `scfs/v1` with public health and schema endpoints.
- Added optional public REST feature suggestion submissions with configurable API-key protection.
- Added authenticated administrator endpoints for suggestion listing, detail retrieval, and workflow updates.
- Added stable submission UUIDs, source labels, correlation IDs, and schema-version metadata.
- Added shared `scfs_event` and `sc_platform_event` WordPress event hooks.
- Added privacy-minimized event payloads that exclude names, email addresses, IP hashes, and submission free text.
- Added optional HMAC-SHA256 signed webhook delivery.
- Added a bounded retry queue with exponential backoff and five-minute WordPress cron processing.
- Added a Feature Suggestions → Integration status screen.
- Added REST and webhook settings to the plugin settings page.
- Added integration fields to CSV exports.
- Preserved standalone shortcode, email, moderation, anti-spam, and export behavior when integrations are disabled.

## 2.0.3 - 2026-07-06

- Fixed the invalid submissions page by changing the custom post type slug from `sc_feature_suggestion` to `sc_feature_suggest`, which stays within WordPress's 20-character post type limit.
- Updated all admin links to point to `/wp-admin/edit.php?post_type=sc_feature_suggest`.
- Added notification delivery status metadata to saved suggestions.
- Added a Settings-page test email button to verify WP Mail SMTP / host mail delivery.
- Email notifications now use explicit plain-text UTF-8 headers and return delivery status for diagnostics.
- Added a small legacy migration for any previously inserted/truncated feature suggestion records.

## 2.0.2 - 2026-07-06

- Fixed settings access on WordPress installs where the Plugins screen is available but `manage_options` does not resolve as expected.
- Lowered the settings-page capability to `edit_posts` by default, with a `scfs_settings_capability` filter for stricter site policies.
- Added a hidden standalone settings URL at `/wp-admin/admin.php?page=scfs-settings-standalone` so the plugin-row Settings link does not depend on the custom post type parent menu.
- Kept the visible locations under Feature Suggestions → Settings and Settings → Feature Suggestions.

## 2.0.1

- Added visible plugin-row action links for Submissions, Settings, and Export CSV on the WordPress Plugins screen.
- Added a secondary Settings menu location under WordPress Settings → Feature Suggestions, while keeping the main Feature Suggestions → Settings submenu.
- Clarified that submitted ideas are stored as the `sc_feature_suggestion` custom post type and reviewed under WordPress Admin → Feature Suggestions.

## 2.0.0 - 2026-07-06

- Added full plugin settings screen under **Feature Suggestions → Settings**.
- Added configurable categories, priorities, form copy, consent copy, visible fields, notification email, and saved post status.
- Changed default submission status to **Pending Review** to avoid front-end save failures associated with private post insertion on some WordPress installs.
- Added configurable strict nonce validation, off by default for better compatibility with cached public pages.
- Added honeypot, IP-hash rate limiting, duplicate detection, link limits, blocked terms, minimum field lengths, and configurable abuse controls.
- Added admin workflow metadata: review status, impact score, effort score, roadmap area, GitHub issue URL, and internal notes.
- Added admin filters and sortable columns for category, priority, and workflow status.
- Expanded CSV export with workflow metadata, consent status, referrer, IP hash, and admin notes.
- Added optional front-end fields for success criteria and implementation notes.
- Updated admin and front-end CSS.
- Updated plugin documentation and manifest.

## 0.1.0 - 2026-07-01

- Initial repository package for Sustainable Catalyst Feature Suggestions.
- Added WordPress plugin source.
- Added shortcode: `[sustainable_catalyst_feature_suggestions]`.
- Added private WordPress record storage.
- Added site admin email notification.
- Added CSV export workflow.
- Added page HTML, site CSS, documentation, examples, manifest, and PHP lint workflow.

## 5.2.2
- Repaired duplicate Knowledge Base titles and Astra bylines.
- Added structural theme filters plus a scoped CSS fallback.

## 5.2.7 — 2026-07-18

- Added automatic canonical `/support/` integration when the Support Center shortcode is absent.
- Consolidated standalone Knowledge Base shortcodes into the unified Support Center on the Support page.
- Added early asset loading to prevent unstyled production output.
- Added request-scoped and client-side duplicate Support Center suppression.
- Made `#support-center` the real root ID and added stable section anchors.
- Added abortable REST navigation, dynamic Knowledge Base reinitialization, and hash restoration.
- Added WordPress/Astra width, overflow, tablet, mobile, accessibility, and print hardening.
- Added administrator integration diagnostics.
- Added redirect-loop protection and no-cache headers for legacy Knowledge Base routes.
- Preserved all existing identifiers, data, settings, REST endpoints, shortcodes, CPTs, and Support Article URLs.
