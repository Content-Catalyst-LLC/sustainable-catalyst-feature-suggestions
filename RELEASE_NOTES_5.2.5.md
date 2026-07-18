# Sustainable Catalyst Product Support and Feedback Platform v5.2.5

## Product Support and Feedback Platform Rebrand, Knowledge Base Rendering Repair, Library Browser Redesign, and Publication-Parity Support Articles

Released: July 18, 2026

Version 5.2.5 makes the platform’s public identity, Knowledge Base, and Support Articles consistent with the wider Sustainable Catalyst publication system. It is a compatibility-preserving release: the plugin folder, text domain, PHP class names, `scfs_*` functions and options, shortcodes, REST namespaces, custom post types, rewrite bases, stored records, and existing article URLs remain intact.

## Public rebrand

The WordPress plugin now appears publicly as **Sustainable Catalyst Product Support and Feedback Platform**. The primary admin navigation label is **Support & Feedback**. Feature Suggestions remains a supported record type and public participation workflow rather than the name of the entire platform.

The following technical identifiers are deliberately unchanged:

- plugin directory and repository slug: `sustainable-catalyst-feature-suggestions`
- text domain: `sustainable-catalyst-feature-suggestions`
- primary class: `Sustainable_Catalyst_Feature_Suggestions`
- suggestion post type: `sc_feature_suggest`
- Support Article post type: `sc_support_article`
- Known Issue post type: `sc_known_issue`
- REST namespace: `scfs/v1`
- existing `scfs_*` options, metadata, hooks, shortcodes, and database records

## Knowledge Base rendering repair

The dedicated `/support/knowledge-base/` route now repairs the failure mode in which `[scfs_support_knowledge_base]` was present only inside an HTML comment and therefore never executed. The release:

- detects an executable current or legacy shortcode before making changes;
- replaces a commented shortcode with the real shortcode at runtime;
- persists the exact comment-placeholder repair during the administrative route check;
- injects the shortcode into the existing `.sc-kb-page__library` region when needed;
- bundles a complete publication-ready Knowledge Base page for new or empty routes; and
- loads the Knowledge Base stylesheet and interaction script early on the dedicated page.

## Compact two-panel library browser

The oversized folder browser has been replaced with a compact library interface.

**Left panel**

- All Products and individual Products
- Versions
- Categories

**Right panel**

- Support Articles heading and result count
- full-text search
- component and article-type filters
- publication-style result rows
- verified-version, reading-time, and updated-date metadata

The browser remains server-rendered and accessible without JavaScript. Existing product, version, section, component, type, and search query parameters are preserved.

## Publication-parity Support Articles

Support Articles now use one editorial renderer instead of two stacked Knowledge Base decorators. This removes duplicate mastheads and dashboard-like metadata boxes.

The article experience now includes:

- Sustainable Catalyst Spartan heading and Montserrat body typography;
- publication masthead, deck, and editorial metadata line;
- product, verified version, component, updated date, and reading time;
- cream “Before you begin” information panel;
- publication heading hierarchy and narrative spacing;
- matching inline code and code-block treatment;
- responsive tables and figures with captions;
- related releases and related known issues;
- related Support Article cards;
- previous and next article navigation;
- existing usefulness and feedback controls inside the publication flow;
- mobile, tablet, desktop, print, and Astra full-width behavior.

## CSS completeness

The v5.2.5 stylesheet contains matching selectors for every class in the bundled Knowledge Base page and every class introduced by the new browser and publication renderer. The validation suite checks this contract automatically.

## Upgrade behavior

No data migration is required. Existing Support Articles, Known Issues, Feature Suggestions, taxonomy terms, options, REST consumers, and URLs continue to work. After activation or administrative access, the route integrity check repairs the dedicated Knowledge Base page when necessary and refreshes rewrite rules only when the route contract version changes.

## Validation

The release validation suite covers:

- PHP syntax across the WordPress plugin;
- JavaScript syntax;
- JSON validity;
- legacy shortcode, post-type, REST, option, and slug compatibility;
- dedicated page shortcode repair;
- two-panel browser structure and query contracts;
- publication metadata and related-content contracts;
- duplicate-decoration prevention;
- Knowledge Base HTML and renderer CSS class coverage;
- existing historical release tests; and
- FastAPI backend tests.
## Packaging repair

The distributable was rebuilt after removing two Markdown trailing-space sequences from the bundled WordPress README. The release now passes both the complete v5.2.5 validation suite and Git whitespace validation before commit. No runtime code, public interface, data contract, shortcode, route, or database behavior changed in this packaging repair.
