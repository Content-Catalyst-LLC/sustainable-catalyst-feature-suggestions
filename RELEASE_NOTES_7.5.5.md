# Product Support and Feedback Platform v7.5.5

## GitHub Tag Fallback and Unified Console Administration

Version 7.5.5 closes the remaining gap between a healthy GitHub repository and the public Release Console. A mapped repository no longer needs a published GitHub Release before its version can synchronize. The synchronization authority is now explicit:

1. Latest published GitHub Release
2. Highest semantic-version Git tag
3. Existing governed registry version when neither exists

Ordinary untagged pushes update repository time and commit evidence but do not create or change a public release version.

The GitHub Connection screen now also contains the Release Console repository and support footer controls, an automatic-sync health indicator, and a direct Sync now action for each mapped repository. Public repositories can be tested without a token; private repositories continue to use the encrypted WordPress token or the existing server-level override.

All canonical product identities, active-plugin mappings, legacy shortcodes, accessibility behavior, REST routes, GitHub webhook verification, and the historical `sustainable-catalyst-feature-suggestions` WordPress plugin folder are preserved.
