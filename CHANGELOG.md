# 2.6.0 — Survey Analysis and Python Research Intelligence

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
