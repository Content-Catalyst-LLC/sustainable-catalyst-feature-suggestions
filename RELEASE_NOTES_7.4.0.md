# v7.4.0 — Product Registry Governance

v7.4.0 makes the Canonical Product Registry the governed authority for product identity, Release Console placement, version selection, lifecycle state, and verification provenance.

## Governance additions

- Upgrades the registry contract to `scfs-canonical-product-registry/2.0`.
- Preserves immutable canonical product IDs while separating public, short, internal, and private repository identities.
- Adds explicit Release Console screen assignments independent of broader product family classification.
- Adds active, planned, maintenance, superseded, and retired lifecycle states.
- Adds manual, discovered, and installed version-precedence policies.
- Records verification source, source verification time, record update time, and migration history.
- Detects stale verification evidence after a configurable governance threshold of 90 days.
- Detects duplicate canonical IDs, duplicate public aliases, duplicate screen order, missing versions, invalid visibility, and invalid supersession targets.
- Adds authenticated integrity and migration REST routes plus `wp scfs products validate` and `wp scfs products migrate` commands.
- Excludes internal names, repository slugs, plugin paths, and administrative notes from public Release Console records.

## Release Console behavior

The `[sc_release_board]` shortcode remains unchanged. The console now uses each record's governed `console_screen` assignment and hides retired or superseded products by default. Terminal, blackboard, compact, and directory layouts remain compatible.

## Compatibility

The WordPress plugin directory, text domain, REST namespace, public support routes, existing registry option, discovery records, manual overrides, and all legacy shortcodes are preserved. The registry schema migration is additive and runs through the existing WordPress option.
