# WordPress Plugin Notes

## Shortcode

```text
[sustainable_catalyst_feature_suggestions]
```

Optional category preselection:

```text
[sustainable_catalyst_feature_suggestions category="Research Librarian feature"]
```

## Public behavior

The shortcode renders a structured feature suggestion form with configurable categories, priority, problem statement, suggested feature, optional success criteria, optional beneficiaries, optional implementation notes, optional page/repository reference, optional contact fields, follow-up permission, consent, and honeypot spam protection.

## Admin behavior

The plugin registers a private admin post type for suggestions and adds:

- **Feature Suggestions** list table
- **Settings** screen
- **Export CSV** screen
- workflow fields on each suggestion
- category, priority, and workflow filters
- impact and effort scoring
- roadmap area and GitHub issue URL fields
- internal notes

## Version 2 save behavior

New submissions are saved as **Pending Review** by default. This is more reliable for public front-end submission than forcing private records at insertion time. The saved status can be changed in **Feature Suggestions → Settings**.

Strict WordPress nonce validation is configurable and off by default because page caching can serve stale nonces to visitors. The plugin still uses honeypot protection, IP-hash rate limiting, duplicate detection, link limits, blocked terms, minimum field lengths, and sanitization.

## Suggested settings path

```text
Feature Suggestions → Settings
```

Use this screen to configure form copy, categories, priorities, visible fields, notification email, saved status, spam controls, and privacy-related metadata collection.
