# Sustainable Catalyst Feature Suggestions v4.0.1

## Embedded Support Center Interface Reliability Patch

Feature Suggestions v4.0.1 resolves the visual and structural problems that appeared when the unified v4.0.0 Support Center was embedded inside a homepage-style Support page. It also makes branding a supported platform capability rather than a page-specific CSS workaround.

## Recommended shortcode

```text
[scfs_product_support_center mode="embedded" branding="sustainable-catalyst" default_view="resolve"]
```

This is the recommended configuration for the Sustainable Catalyst Support page.

## Configurable branding

The Support Platform administration screen now provides four branding modes:

1. **Platform** — neutral Product Support Platform defaults.
2. **Sustainable Catalyst** — maroon, black, white, cream, Spartan, and Montserrat.
3. **Inherit active theme** — resolves Astra and WordPress global-style variables with safe fallbacks.
4. **Custom** — administrator-defined colors, typography, shape, shadow, width, and navigation density.

Available tokens include:

- accent and accent-contrast;
- ink and muted text;
- surface and soft surface;
- border line;
- success, warning, and danger states;
- body and heading font stacks;
- border radius;
- none, subtle, or raised shadow;
- maximum application width;
- one to four navigation columns.

Shortcode attributes can override saved defaults for a specific page or application instance.

## Embedded rendering

Embedded mode is a separate rendering contract rather than an alias for the earlier compact option. It can:

- hide the duplicate application header;
- hide the status row or only zero-value metrics;
- hide internal navigation descriptions;
- hide the duplicate overview pathway cards;
- use the containing page width;
- start at Guided Resolution;
- preserve product and active-view context;
- retain accessible labels when visual chrome is removed.

The legacy `compact="1"` option remains supported and maps to embedded behavior unless an explicit mode is supplied.

## CSS collision protection

The Support Center navigation, buttons, fields, cards, headings, and child applications now use scoped selectors and validated design tokens. This prevents broad Astra, page-builder, or site CSS from:

- converting every internal tab into a red call-to-action;
- forcing uppercase labels or excessive letter spacing;
- stretching internal links into full-width buttons;
- applying oversized page headings inside the application;
- collapsing cards into narrow or overflowing columns;
- replacing form surfaces and controls with unrelated page styles.

The same token system styles Guided Resolution, Knowledge Base, Known Issues, releases, public ideas, voting, feature suggestions, surveys, and private-support continuation.

## Responsive behavior

Navigation and content grids now collapse predictably from desktop to tablet and mobile. Text can wrap inside narrow cards, fields remain within their container, and internal two-column modules collapse to one column on small screens.

Reduced-motion preferences are respected.

## Cache-safe assets

The plugin now versions public Support Center styles using `filemtime()`. This gives changed CSS a new URL after an update and reduces the chance that a WordPress cache, CDN, or browser continues showing the previous interface.

## Extension points

Developers can use:

```text
scfs_support_branding_tokens
scfs_product_support_center_atts
```

The first filter changes validated design tokens. The second adjusts Support Center attributes before display context is resolved.

## Backward compatibility

The release preserves:

- `[scfs_product_support_center]`;
- `[scfs_support_center]`;
- `[scfs_guided_resolution]`;
- `[scfs_support_knowledge_base]`;
- public Release Intelligence;
- feature suggestions, public ideas, voting, forms, and surveys;
- product-aware REST and FastAPI contracts;
- v4.0.0 public/private platform responsibilities.

## Privacy and governance

Branding and rendering changes do not change the data boundary. Feature Suggestions owns public documentation, known issues, releases, suggestions, voting, surveys, and product intelligence. Contact and Engagement remains the private system of record for requester identity, correspondence, documents, cases, and lifecycle management.

Automatic private-case creation remains disabled. Human review remains required for publishing, prioritization, official responses, roadmap decisions, and release decisions.

## Validation completed

- 22 PHP files passed syntax validation.
- 132 WordPress structure, bootstrap, registration, schema, branding, privacy-boundary, and embedded-render checks passed.
- 16 Python/FastAPI tests passed.
- 18 JSON records passed parsing.
- Installer shell syntax passed.
- Secret scanning and archive-integrity checks passed.
