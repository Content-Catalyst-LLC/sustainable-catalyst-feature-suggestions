# Sustainable Catalyst Feature Suggestions v4.0.1

**Embedded Support Center Interface Reliability Patch**

Version 4.0.1 makes the unified Product Support Center reliable inside a designed WordPress page and introduces a first-party branding system that no longer depends on increasingly specific page CSS.

## Primary Support page integration

```text
[scfs_product_support_center mode="embedded" branding="sustainable-catalyst" default_view="resolve"]
```

Administrators can also set Embedded mode and the Sustainable Catalyst preset as defaults under **Feature Suggestions → Support Platform**, allowing the existing `[scfs_product_support_center]` shortcode to inherit those settings.

## Embedded mode

- Removes the duplicate internal Support Center hero when the page already provides one.
- Defaults to Guided Resolution rather than another overview screen.
- Hides all-zero status counters by default.
- Uses compact navigation labels and configurable one- to four-column navigation.
- Suppresses the repeated overview pathway grid.
- Lets the containing page control outer width and spacing.
- Preserves the current support view when product context changes.
- Retains an accessible screen-reader heading when visual application chrome is removed.

## Branding controls

New presets:

- Platform
- Sustainable Catalyst
- Inherit active theme
- Custom

Administrators can configure accent and contrast colors, text colors, surfaces, borders, state colors, body and heading fonts, radius, shadow, maximum width, and navigation columns.

Every setting can also be overridden per shortcode. Validated CSS custom properties are scoped to the individual Support Center instance.

## Interface reliability

The public stylesheet now protects the Support Center from broad theme and page rules that previously caused red navigation blocks, forced uppercase text, oversized headings, narrow cards, and inconsistent form controls.

The scoped design system flows into Guided Resolution, Knowledge Base, Known Issues, Release Intelligence, public ideas and voting, feature suggestions, forms, surveys, and private-support continuation.

## Cache reliability

Public assets now use source-file modification times for versioning. CSS changes therefore receive new cache keys after each release instead of being hidden behind a static plugin version or stale optimization cache.

## Compatibility and governance

Version 4.0.1 preserves:

- the v4.0.0 Product Support and Feedback Platform architecture;
- all existing public shortcodes;
- standalone Support Center rendering;
- public product, version, component, issue, and release context;
- the consent-gated Contact and Engagement handoff;
- the prohibition on automatic private-case creation;
- mandatory human review for roadmap, release, voting, survey, search, AI, and scoring evidence.

## Upgrade note

After updating, clear WordPress, optimization-plugin, CDN, and browser caches once. Future CSS updates use cache-safe file modification versions.

## Validation completed

- 22 PHP files passed syntax validation.
- 132 WordPress structure, bootstrap, registration, schema, branding, privacy-boundary, and embedded-render checks passed.
- 16 Python/FastAPI tests passed.
- 18 JSON records passed parsing.
- Installer shell syntax passed.
- WordPress package root and archive integrity passed.
