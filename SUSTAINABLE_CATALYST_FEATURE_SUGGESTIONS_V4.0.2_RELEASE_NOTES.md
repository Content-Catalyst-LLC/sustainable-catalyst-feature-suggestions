# Sustainable Catalyst Feature Suggestions v4.0.2

## Navigation and Embedded Pathway Reliability Patch

Feature Suggestions v4.0.2 repairs the remaining navigation and card-layout problems in the embedded Product Support Center. It preserves the v4.0.1 branding system and v4.0.0 platform architecture while making the Support Center behave as one application inside the designed Support page.

## In-place support navigation

Support Center navigation now changes the active workspace without reloading the entire WordPress page. Guided Resolution, Knowledge Base, Known Issues, Releases, Public Ideas, Suggest a Feature, Surveys, and Private Support all use the same client-side routing layer.

The browser URL remains meaningful. Each view updates `scfs_support_view`, product and survey context remain present, and browser back/forward controls restore the matching workspace.

## Direct links and resilient fallback

Every internal Support Center link remains a normal same-origin URL and includes the configured Support Center anchor. The default fallback is `#support-center`.

When JavaScript is disabled, blocked, or unable to reach the read-only view endpoint, the browser follows the direct URL and returns to the embedded Support Center rather than the top of the page.

## Product context preservation

Applying a product filter no longer returns the visitor to the default workspace. The selected product and active view are preserved across navigation, direct links, browser history, releases, and surveys.

## Repaired pathway cards

The optional “Choose another support pathway” cards now use:

- protected horizontal text flow;
- normal word-breaking rather than letter-by-letter wrapping;
- stable minimum card widths;
- equal-height desktop cards;
- responsive three-, two-, and one-column layouts;
- compact, site-native pathway actions.

These rules are strongly scoped beneath the Product Support Center so Astra and page-level card or link styles cannot collapse the interface.

## Dynamic child-module reliability

The Support Center preloads the styles and scripts needed by public ideas, suggestion forms, and surveys. Dynamically inserted survey forms are initialized after each workspace change, while public voting continues to use delegated event handling.

## Accessibility

- Active views retain `aria-current="page"`.
- The workspace reports `aria-busy` while loading.
- View changes are announced through a polite live region.
- Modified clicks, new tabs, and standard direct links remain available.
- Reduced-motion preferences remain respected.

## Public view endpoint

The WordPress REST API adds:

```text
GET /wp-json/scfs/v1/product-support/view
```

The endpoint returns rendered public Support Center workspace HTML for an allowed view and product context. It does not expose requester identity, private case content, correspondence, or documents.

## Recommended Support page shortcode

```text
[scfs_product_support_center mode="embedded" branding="sustainable-catalyst" default_view="resolve" anchor="support-center"]
```

Set `interactive="0"` only when traditional full-page navigation is preferred.

## Governance boundary preserved

Feature Suggestions remains the public documentation, known-issue, release, suggestion, voting, survey, and product-intelligence platform. Contact and Engagement remains the private identity, correspondence, document, and support-case system of record.
