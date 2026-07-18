# Sustainable Catalyst Product Support and Feedback Platform v5.2.7

**Release:** Support Center Production Integration and Interface Hardening
**Date:** July 18, 2026

## Overview

Version 5.2.7 stabilizes the unified `/support/` experience introduced in v5.2.6. The release does not add another public landing page. It makes the canonical Support page self-healing, ensures the complete dependency chain is loaded before page output, prevents duplicate Support Center instances, repairs canonical anchors, and hardens the two-panel Support Article browser against WordPress theme and responsive-layout conflicts.

No database migration is required.

## Canonical public architecture

- `/support/` remains the single public Support Center.
- `/support/?scfs_support_view=documentation#knowledge-base` opens the complete Support Article browser.
- `/support/guides/...` remains the publication-style Support Article permalink base.
- `/support/knowledge-base/` and `/support-documentation/` remain permanent compatibility redirects.

## Production Support page integration

The plugin now detects the published WordPress page with the `support` slug and enqueues the full Support Center asset dependency chain during `wp_enqueue_scripts`, before content rendering occurs.

When the canonical Support page content contains:

- `[scfs_product_support_center]` or `[scfs_support_center]`, the existing shortcode remains untouched.
- `[scfs_support_knowledge_base]` or its legacy alias without a Support Center shortcode, the old standalone browser shortcode is replaced with the unified Support Center shortcode.
- no Support Center or Knowledge Base shortcode, the unified Support Center shortcode is appended automatically.

This behavior is controlled by the existing Product Support Platform option record and defaults to enabled through `auto_integrate_support_page`.

## Duplicate rendering prevention

Version 5.2.7 introduces request-scoped server-side render signatures. A repeated Support Center with the same page, anchor, and mode is suppressed before duplicate HTML IDs or duplicate browser interfaces can be emitted.

A client-side root guard provides an additional protection layer for page-builder, reusable-block, and cache-generated duplication. Explicit multiple instances remain possible through the `allow_duplicate="1"` shortcode attribute and distinct anchors.

## Canonical anchors

The rendered Support Center now uses the requested anchor as the real root HTML ID. The default root is:

- `#support-center`

Stable section anchors are also present for:

- `#guided-resolution`
- `#knowledge-base`
- `#known-issues`
- `#release-intelligence`

Navigation, history, direct links, legacy redirects, keyboard focus, responsive scroll offsets, and reduced-motion behavior now use these anchors consistently.

## AJAX and browser reliability

The public navigation script now:

- Cancels stale REST view requests when visitors switch sections quickly.
- Reinitializes Knowledge Base behavior after dynamically loaded views.
- Preserves browser history and direct-link fallback URLs.
- Restores the active product and section state.
- Uses accessible live-region announcements.
- Applies hash navigation after dynamic rendering.
- Falls back to a direct page request when the REST response is unavailable.

## Interface hardening

The v5.2.7 CSS layer adds:

- WordPress and Astra content-width safeguards scoped to the Support page.
- Horizontal-overflow prevention.
- Stable two-panel browser sizing with `min-width: 0` safeguards.
- Three-column tablet navigation for Products, Versions, and Categories.
- Single-column mobile navigation and results.
- Correct Support Article metadata wrapping.
- Canonical anchor scroll margins.
- Loading and administrator diagnostic states.
- Print rules that remove interactive navigation and filters.

## Administrator diagnostics

Administrators can append the following query parameter to the Support page:

```text
?scfs_support_debug=1
```

The rendered Support Center then reports the integration source, active view, and product context. Duplicate instances also produce a visible diagnostic instead of silently appearing twice.

## Legacy route hardening

The v5.2.6 redirects are retained and the route contract advances to `3.1.0`. Redirect requests now send no-cache headers and compare the current normalized request with the target before redirecting, preventing loops caused by proxy or permalink edge cases.

## Backward compatibility

The release preserves:

- `Sustainable_Catalyst_Feature_Suggestions`
- `scfs_*` functions, hooks, metadata, and options
- `sc_feature_suggest`
- `sc_support_article`
- `sc_known_issue`
- `sc_release_record`
- `scfs/v1`
- `[scfs_product_support_center]`
- `[scfs_support_center]`
- `[scfs_support_knowledge_base]`
- `[sustainable_catalyst_support_knowledge_base]`
- `/support/guides/` article URLs
- Existing suggestions, Support Articles, Known Issues, releases, settings, taxonomies, votes, feedback, and survey data

## Validation

The release validation suite covers:

- PHP syntax across the complete WordPress plugin.
- Historical WordPress compatibility contracts.
- v5.2.7 production integration contracts.
- JavaScript syntax.
- JSON and Python syntax.
- CSS block balance and prohibited tag checks.
- Source trailing-whitespace compatibility.
- FastAPI backend tests.
- Manifest and package identity.
- Python 3.12 and 3.13 installer compatibility.
- Python 3.14 rejection before dependency installation.
- Binary-only `pydantic-core` installation.
- WordPress, repository, and bundle ZIP integrity.
