# Sustainable Catalyst Product Support and Feedback Platform v5.2.6

## Unified Support Center, Embedded Knowledge Base Browser, and Legacy Knowledge Base Route Consolidation

Release date: 2026-07-18

## Summary

Version 5.2.6 removes the need for a separate public Knowledge Base landing page. The complete two-panel Support Article browser now appears directly inside the main Product Support and Feedback Platform on `/support/`.

The former `/support/knowledge-base/` page and the historical `/support-documentation/` archive remain recognized for backward compatibility, but public requests are permanently redirected to the Support Articles section of the unified Support Center. Existing Support Article permalinks under `/support/guides/` are unchanged.

## Unified Support Center

The Support Center now presents product resolution and documentation as one continuous workflow:

- Guided Resolution remains the first troubleshooting path.
- The full two-panel Knowledge Base browser appears immediately below Guided Resolution.
- The browser is also included in the Support overview.
- The Support Articles navigation item opens the same embedded browser instead of a second page.
- Known Issues, Release Intelligence, public ideas, feature suggestions, surveys, and private-support handoffs remain available through the same Support interface.
- The redundant “Browse documentation” pathway card was removed because the complete browser is already visible.

The embedded browser retains:

- Products, Versions, and Categories in the left panel.
- Search, component filters, article-type filters, counts, and results in the right panel.
- Product-aware filtering inherited from the Support Center.
- Real WordPress Support Article permalinks.
- Server-rendered operation when JavaScript is unavailable.
- Responsive and publication-parity styling from v5.2.5.

## Legacy route consolidation

Version 5.2.6 recognizes and redirects these former landing routes:

- `/support/knowledge-base/`
- `/support-documentation/`

Both redirect with HTTP status 301 to:

- `/support/?scfs_support_view=documentation#knowledge-base`

Existing Knowledge Base filters are preserved during the redirect, including search, product, version, category, component, article type, and pagination values.

The managed legacy WordPress page remains published as a compatibility target. Its content is replaced with a minimal “Support Articles have moved” fallback for environments where redirect handling is disabled. It no longer contains `[scfs_support_knowledge_base]` and therefore cannot render a duplicate browser.

## Support Article continuity

The following are unchanged:

- Support Article post type: `sc_support_article`
- Support Article permalink base: `/support/guides/`
- Known Issue post type: `sc_known_issue`
- Suggestion post type: `sc_feature_suggest`
- REST namespace: `scfs/v1`
- Main settings option: `scfs_settings`
- Repository and plugin directory slug: `sustainable-catalyst-feature-suggestions`
- Text domain: `sustainable-catalyst-feature-suggestions`
- Existing Support Article records, metadata, taxonomies, feedback controls, and relationships
- Publication-parity Support Article rendering introduced in v5.2.5

## Shortcode compatibility

All existing shortcodes remain available:

- `[scfs_product_support_center]`
- `[scfs_support_center]`
- `[scfs_support_knowledge_base]`
- `[sustainable_catalyst_support_knowledge_base]`
- `[scfs_support_library_compact]`
- `[sustainable_catalyst_support_library_compact]`
- `[scfs_guided_resolution]`
- Existing feature suggestion, survey, public-idea, and cross-product support shortcodes

`[scfs_support_knowledge_base]` remains a supported standalone renderer for custom integrations. The canonical Sustainable Catalyst public location is now the browser embedded in `/support/`.

## Accessibility and HTML structure

The right-hand results pane now uses an embeddable region rather than a nested `<main>` landmark. This prevents invalid nested page landmarks when the browser is rendered inside the main Support page.

The release also adds:

- Stable `#knowledge-base` anchor targeting.
- Responsive embedded-browser spacing.
- Focus-safe route fallback styling.
- Reduced duplicate navigation and page chrome.

## Upgrade behavior

No database migration is required.

On upgrade, the plugin:

1. Registers the v3.0.0 Knowledge Base route contract.
2. Finds or preserves the `/support/knowledge-base/` child page.
3. Replaces plugin-managed duplicate Knowledge Base page content with the compact fallback notice.
4. Flushes rewrite rules once when the route contract version changes.
5. Redirects public legacy landing-route requests to the unified Support Center.
6. Leaves Support Article URLs and content untouched.

## Validation

The release validation suite checks:

- PHP syntax across the WordPress plugin.
- All historical and v5.2.6 PHP contract tests.
- JavaScript syntax.
- JSON and Python syntax.
- Source whitespace compatibility.
- CSS block balance and forbidden HTML tags.
- FastAPI backend tests.
- Unified Support Center browser inclusion.
- Legacy route and archive redirects.
- Filter preservation.
- Support Article URL continuity.
- Legacy shortcodes, REST routes, options, CPTs, and identifiers.
- Release manifest integrity.

## Installer compatibility repair (R2)

The initial v5.2.6 macOS installer could select Python 3.14 because its interpreter check accepted every Python release newer than 3.12. The backend dependency set pins Pydantic 2.11.5 and its matching `pydantic-core` release. On the affected Python 3.14 environment, pip attempted to compile `pydantic-core` locally with Rust and the validation installation stopped before any Git commit or push.

Installer revision `V5_2_6_R2_PYTHON_314_COMPATIBILITY_REPAIR` corrects the release workflow without changing plugin behavior:

- Selects Python 3.13 first and Python 3.12 second.
- Explicitly rejects Python 3.14 for the v5.2.6 validation environment.
- Detects Homebrew, `/usr/local`, Python.org Framework, and shell-path installations.
- Requires a binary `pydantic-core` wheel so the installer cannot silently fall back to a local Rust build.
- Provides the exact Homebrew recovery command when no compatible interpreter is available.
- Prioritizes repaired v5.2.6 archives when older copies remain in `~/Downloads`.

No WordPress, database, REST, shortcode, route, Support Article, or public-interface code changed in this installer repair.
