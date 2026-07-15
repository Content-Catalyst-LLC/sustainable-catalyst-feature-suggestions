# Product Support and Feedback Platform v4.0.2

Feature Suggestions v4.0.2 is the unified public product-support layer for Sustainable Catalyst, with a site-native embedded mode and configurable branding.

## Public responsibilities

The platform owns:

- Guided Resolution and product-aware search;
- Support Articles and documentation collections;
- Known Issues, status, workarounds, and resolution relationships;
- Release Intelligence and compatibility notes;
- feature suggestions and moderated public ideas;
- advisory voting and official responses;
- forms, surveys, and product-research participation;
- documentation and support-demand intelligence.

Use the public Support Center shortcode:

```text
[scfs_product_support_center]
```

The legacy alias `[scfs_support_center]` is also supported. The `/support-knowledge-base/` archive now renders the unified Support Center.


## Embedded Support Center and branding

For a designed page that already supplies its own hero and introduction, use:

```text
[scfs_product_support_center mode="embedded" branding="sustainable-catalyst" default_view="resolve"]
```

Embedded mode defaults to Guided Resolution, suppresses duplicate application chrome, hides all-zero status metrics, shortens internal navigation, and lets the containing page own the outer width and spacing.

Branding presets:

- `platform` — neutral Product Support Platform defaults;
- `sustainable-catalyst` — black, white, maroon, cream, Spartan, and Montserrat;
- `inherit` — uses active-theme/Astra and WordPress preset variables where available;
- `custom` — uses saved or shortcode-supplied design tokens.

Custom shortcode example:

```text
[scfs_product_support_center mode="embedded" branding="custom" default_view="resolve" accent="#9b1111" accent_contrast="#ffffff" ink="#000000" muted="#555555" surface="#ffffff" soft="#f7f3ea" line="#d9d2c4" radius="0" shadow="none" nav_columns="3"]
```

Configure defaults under **Feature Suggestions → Support Platform**. See `embedded-support-center-branding.md` for all attributes and extension filters.

## Private responsibilities

Contact and Engagement remains the system of record for:

- requester identity and contact information;
- private support cases;
- sender communication and case correspondence;
- private documents and controlled downloads;
- internal case lifecycle management.

Feature Suggestions can create a short-lived, consent-gated context handoff. It does not create a private case automatically and does not store private case content.

## Release Intelligence

The public `sc_release_record` post type records:

- planned, preview, current, maintenance, superseded, and retired states;
- public release date and summary;
- support and compatibility notes;
- release highlights and known limitations;
- documentation and changelog URLs;
- shared product, version, component, issue, and release taxonomy context;
- related Support Articles and Known Issues;
- privacy-safe relationships to moderated public ideas.

Private feature-suggestion text and contact data are never exposed through release records.

## REST foundation

Public endpoints:

```text
GET /wp-json/scfs/v1/product-support/schema
GET /wp-json/scfs/v1/product-support/overview
GET /wp-json/scfs/v1/product-support/releases
GET /wp-json/scfs/v1/product-support/products
GET /wp-json/scfs/v1/product-support/handoff-schema
```

Administrator endpoint:

```text
GET /wp-json/scfs/v1/product-support/snapshot
```

## Decision boundaries

Voting, surveys, search demand, article feedback, AI classification, documentation gaps, and support demand are advisory evidence. Publishing, official responses, release status, roadmap state, and private case creation require authorized human action.
