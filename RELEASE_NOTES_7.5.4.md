# Sustainable Catalyst Product Support and Feedback Platform v7.5.4

## Administrator GitHub Connection and Console Link Controls

v7.5.4 removes the need to edit `wp-config.php` for ordinary private-repository synchronization. Administrators can now store a GitHub token from WordPress, test that token against every mapped repository, see exact repository-specific failures, and synchronize all mapped products from one screen.

## GitHub Connection

A new **Support & Feedback → GitHub Connection** screen provides:

- encrypted, non-autoloaded storage for a GitHub token;
- masked credential fields that never display saved secrets;
- preserved `SCFS_GITHUB_TOKEN` and environment-variable overrides;
- optional encrypted webhook-secret storage;
- a mapped-repository access test using the same credential as synchronization;
- a one-click **Sync all repositories now** action;
- clear status for repositories that are accessible but have no published GitHub Release.

The token is encrypted with a site-derived key using AES-256-GCM before it is stored in the WordPress options table. It is not returned through the settings form, schema output, REST responses, audit records, or diagnostic tables.

## Exact synchronization diagnostics

Plugin Discovery now displays the actual GitHub error beneath the affected product connection. HTTP failures include their status code, such as `GitHub returned HTTP 404: Not Found`.

A private repository with no GitHub Releases is treated as a successful repository connection. Repository and commit evidence are synchronized while the public release version remains unchanged until a release is published.

## Editable Release Console footer links

**Support & Feedback → Release Console Copy** now includes a dedicated **Footer links** section with editable fields for:

- repository label;
- repository destination;
- support label;
- support destination.

Leaving the repository destination blank uses the GitHub repository mapped to the canonical `product-support-feedback` record. The support destination defaults to `/support/` and accepts a site-relative path or complete URL.

## Compatibility

- The WordPress plugin folder remains `sustainable-catalyst-feature-suggestions`.
- The text domain remains `sustainable-catalyst-feature-suggestions`.
- All canonical product IDs and existing mappings are preserved.
- All legacy shortcodes and Release Console layouts remain supported.
- Active site and network plugins remain selectable in Canonical Product Connections.
- Keyboard controls, reduced-motion behavior, semantic regions, and live-status announcements remain intact.
