# Sustainable Catalyst Feature Suggestions

A lightweight WordPress plugin and page package for collecting, storing, reviewing, and exporting Sustainable Catalyst platform feature suggestions.

This repository supports the public feature suggestion workflow at:

```text
/platform/feature-suggestions/
```

The plugin gives visitors a structured way to suggest new platform modules, demo improvements, Research Librarian features, repository upgrades, documentation improvements, accessibility fixes, bug reports, and data/export ideas without requiring GitHub knowledge.

## Shortcode

```text
[sustainable_catalyst_feature_suggestions]
```

## What the plugin does

- Saves submissions as private WordPress records.
- Adds a **Feature Suggestions** admin area.
- Emails the site admin when a suggestion is submitted.
- Provides an admin CSV export screen.
- Includes nonce protection, sanitization, a consent checkbox, and a honeypot field.
- Keeps the public workflow aligned with Sustainable Catalyst boundaries: no confidential, proprietary, sensitive personal, medical, legal, financial, or regulated information should be submitted.

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
4. Create a page at `/platform/feature-suggestions/`.
5. Paste the HTML from `docs/feature-suggestions-page.html`.
6. Add the CSS from `docs/feature-suggestions-site.css` to the end of the site CSS.

## Suggested page role

The page should be treated as a public improvement loop for Sustainable Catalyst, not as a support ticket system or professional-advice intake channel.

## Boundaries

Suggestions may inform future development, but submission does not guarantee implementation, attribution, compensation, or response. Visitors should not submit confidential, proprietary, sensitive personal, medical, legal, financial, or regulated information through the form.

## License

GPL-2.0-or-later for WordPress compatibility unless otherwise stated.
