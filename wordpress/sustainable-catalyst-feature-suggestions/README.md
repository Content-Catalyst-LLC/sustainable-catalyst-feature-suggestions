# Sustainable Catalyst Feature Suggestions

Version 2.0.0 is an advanced WordPress plugin for collecting, storing, triaging, and exporting structured Sustainable Catalyst feature suggestions.

## Shortcode

```text
[sustainable_catalyst_feature_suggestions]
```

Optional category preselection:

```text
[sustainable_catalyst_feature_suggestions category="Research Librarian feature"]
```

## What v2 does

- Renders a configurable public feature suggestion form.
- Saves submissions as WordPress Feature Suggestion records.
- Adds a real **Feature Suggestions → Settings** screen.
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
