# Changelog

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
