# Sustainable Catalyst Product Support and Feedback Platform v7.7.0

## Canonical Product Registry Administration

v7.7.0 turns the Canonical Product Registry into a governed administrative product catalog while preserving the existing product IDs, WordPress plugin mappings, GitHub connections, Release Console shortcode, support routes, accessibility behavior, and historical plugin folder.

### Added

- Search and filters for product name, canonical ID, family, lifecycle state, visibility, plugin identity, and repository identity.
- Governed product families for Foundation, Research and Intelligence, Data and Analysis, Creation and Systems, and Commercial Release.
- Drag-and-drop Release Console ordering with keyboard-accessible Alt+Arrow controls.
- Planned, Experimental, Active, Maintenance, Deprecated, Superseded, and Retired lifecycle states.
- New canonical product creation with immutable canonical IDs.
- Duplicate-product merging that transfers names and plugin identifiers to the authoritative target and archives the superseded source.
- Alias-collision review based on the registry integrity report.
- Non-destructive archive and restore controls.
- Administrator identity and UTC timestamps for consequential registry changes.
- Governed JSON export.
- Required dry-run validation before registry import.
- Automatic pre-mutation backups and one-click backup restoration.
- WP-CLI history and backup metadata reports.

### Governance boundaries

- Canonical IDs cannot be renamed through the administration interface.
- Imports cannot be applied until a valid dry run has completed.
- Product merges preserve the source canonical ID as an archived superseded record.
- Archived products are removed from public registry and Release Console output.
- Registry exports do not contain GitHub credentials or webhook secrets.
- Public publication and irreversible deletion remain disabled.

### Compatibility

- WordPress plugin folder remains `sustainable-catalyst-feature-suggestions`.
- `[sc_release_board]` and all inherited blackboard, compact, directory, terminal, support, and feedback shortcodes remain available.
- Existing canonical IDs, aliases, active-plugin mappings, GitHub repository connections, footer destinations, and support records are preserved.
- Existing accessibility and reduced-motion behavior remains intact.
