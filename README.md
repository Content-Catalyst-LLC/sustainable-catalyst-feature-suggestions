# Sustainable Catalyst Feature Suggestions

An advanced WordPress plugin and page package for collecting, storing, reviewing, triaging, and exporting Sustainable Catalyst platform feature suggestions.

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

## Version 2 highlights

- Real **Feature Suggestions → Settings** screen.
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
7. Optionally paste the surrounding page HTML from `docs/feature-suggestions-page.html` and site CSS from `docs/feature-suggestions-site.css`.

## Important v2 save fix

The previous version had no settings page and saved public submissions as private records by default. On some WordPress installs that can fail or behave inconsistently from the public front end. Version 2 defaults to **Pending Review**, assigns a safe author when possible, and adds configurable nonce behavior for cached pages.

## Suggested page role

The page should be treated as a public improvement loop for Sustainable Catalyst, not as a support ticket system or professional-advice intake channel.

## Boundaries

Suggestions may inform future development, but submission does not guarantee implementation, attribution, compensation, or response. Visitors should not submit confidential, proprietary, sensitive personal, medical, legal, financial, or regulated information through the form.

## License

GPL-2.0-or-later for WordPress compatibility unless otherwise stated.
