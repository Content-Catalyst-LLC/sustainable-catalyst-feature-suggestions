# Sustainable Catalyst Product Support and Feedback Platform v7.5.3

## Active Plugin and GitHub Console Connections

v7.5.3 connects each Canonical Product Registry record to two operational sources: a currently active WordPress plugin and a GitHub repository. The canonical product remains the public identity shown by the Release Console.

### Active plugin selection

- Adds a connection row for every non-retired canonical product.
- Populates each plugin dropdown from all currently active site and network plugins, not only unmatched discovery candidates.
- Excludes inactive plugins from new connection selections.
- Shows plugin name, installed version, activation scope, and existing mapping owner.
- Preserves administrator mappings, legacy plugin identifiers, collision protection, audit records, and reversible mapping behavior.

### GitHub synchronization

- Stores a governed GitHub repository URL on the canonical product record.
- Uses the latest non-draft GitHub release as the public console version and release evidence.
- Retains the active plugin version as installed-state evidence.
- Marks a product `update_available` when the installed plugin is behind the latest GitHub release.
- Records the latest release URL, release date, release name, default branch, repository update time, and commit SHA.
- Supports signed GitHub webhooks for immediate refresh and hourly WordPress polling as a fallback.
- Supports public repositories without credentials and private repositories through `SCFS_GITHUB_TOKEN`.
- Keeps the webhook secret outside WordPress options through `SCFS_GITHUB_WEBHOOK_SECRET`.

### Release Console

- Replaces the public `./releases` footer destination with `./repository`.
- Resolves the repository link from the canonical product's mapped GitHub URL.
- Opens external repository links safely in a new tab.
- Adds GitHub commit and repository-update indicators beneath the product name.
- Preserves the shared responsive Version, State, and Source grid, rotating screens, reduced-motion behavior, and all legacy shortcodes.

### Validation

- Complete inherited PHP contract suite plus new active-plugin and GitHub synchronization contracts.
- Backend, migration utility, PHP, JavaScript, JSON, Python, CSS, package, checksum, and installer validation.
