# Product Support and Feedback Platform v7.6.1

## Release Operations Stabilization

Version 7.6.1 advances the Canonical Product Registry from connection setup into day-to-day release administration. Administrators now have one operational screen showing how every non-retired canonical product connects to its active WordPress implementation, mapped GitHub repository, governed release version, and public Release Console state.

### Release Operations

The new **Support & Feedback → Release Operations** screen provides:

- product, plugin, repository, release, console, and last-sync columns
- summary counts for active plugins, connected repositories, current products, available updates, errors, and stale synchronization
- filters for current, update available, error, stale, and unconfigured products
- per-product **Sync now**
- bulk synchronization for selected products
- bulk synchronization for all connected products
- controlled clearing of resolved GitHub error messages
- an integrity audit for duplicate repository and plugin mappings, missing repositories, stale synchronization, and active-plugin gaps
- a non-secret JSON operational export
- the `wp scfs products operations-report` WP-CLI command

### Freshness governance

Synchronization is classified as:

- **Current** within two hours
- **Aging** between two and 24 hours
- **Stale** after 24 hours
- **Never synchronized** when no run is recorded
- **Synchronization error** when GitHub reports an error
- **Update available** when the installed plugin is behind the governed GitHub release or semantic tag
- **Repository not connected** when the canonical product has no GitHub mapping

### Security and compatibility

Operational exports omit GitHub tokens, webhook secrets, encryption payloads, and raw private option data. GitHub Release-first authority, semantic-tag fallback, untagged-push behavior, signed webhooks, scheduled polling, active-plugin mappings, canonical aliases, editable footer destinations, all legacy shortcodes, reduced-motion behavior, and the historical `sustainable-catalyst-feature-suggestions` plugin folder remain preserved.
