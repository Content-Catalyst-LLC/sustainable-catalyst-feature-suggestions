# Sustainable Catalyst Product Support and Feedback Platform v7.0.1

## Repository Identity Migration

v7.0.1 moves the active source repository to the canonical Product Support and Feedback identity while preserving every WordPress compatibility identifier. It is intentionally a repository and release-engineering patch rather than a feature or database release.

## What changed

- Canonical GitHub repository: `Content-Catalyst-LLC/sustainable-catalyst-product-support-feedback`.
- Canonical local repository directory: `sustainable-catalyst-product-support-feedback`.
- Added a migration-aware macOS installer that recognizes the legacy local directory and safely renames it.
- Updated current manifests, documentation, release packaging, and validation to the canonical repository identity.
- Added explicit legacy repository metadata for historical traceability.
- Bumped the WordPress plugin and FastAPI runtime identity to v7.0.1.

## What did not change

- WordPress plugin directory: `sustainable-catalyst-feature-suggestions`.
- WordPress main plugin file: `sustainable-catalyst-feature-suggestions.php`.
- WordPress text domain: `sustainable-catalyst-feature-suggestions`.
- PHP class names, database tables, post types, REST namespace, shortcodes, option keys, and public routes.
- Existing public or private support data.

## Required GitHub step

After v7.0.0 is present on `main` and tagged, rename the GitHub repository to `sustainable-catalyst-product-support-feedback`. Then run the v7.0.1 installer. The installer verifies that the canonical remote is reachable before changing the local repository.

## Compatibility

No WordPress reactivation or database migration is required. The legacy WordPress identifiers remain authoritative compatibility identifiers. Historical release files continue to describe the repository identity that existed when those releases were built.
