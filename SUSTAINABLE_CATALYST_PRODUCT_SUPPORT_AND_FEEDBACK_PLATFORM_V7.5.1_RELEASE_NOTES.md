# Sustainable Catalyst Product Support and Feedback Platform v7.5.1

## Release Console Alignment and Plugin Discovery Status Repair

v7.5.1 is a focused presentation and administrator-status repair built on the validated v7.5.0 repository.

### Release Console alignment

- Aligns the System, Version, State, and Source headings and product values through one shared responsive grid contract.
- Keeps release-intelligence badges and change summaries directly beneath each product name instead of allowing them to drift beneath the full row.
- Preserves source and state column placement when either shortcode option is disabled.
- Retains responsive source suppression and compact mobile stacking without changing semantic list output.
- Tightens footer padding and row gaps while keeping Release and Support as the only fixed-footer links.
- Preserves the terminal, blackboard, compact, and directory layouts, the seven-second rotating console, keyboard controls, hover and focus pause, reduced-motion behavior, multiple-instance handling, and the no-JavaScript fallback.

### Plugin Discovery status repair

- Reconciles displayed review counts against the current actionable candidate queue instead of trusting a stale cached count.
- Shows **Pending private review** only when genuine unmatched or malformed Catalyst plugin candidates exist.
- Separates deterministic duplicate matches into a dedicated **Duplicate mapping review** section.
- Adds actionable candidate rows with the plugin path, version state, activation scope, review reason, suggested registry match, selected duplicate winner, and a direct review action.
- Clears stale discovered plugin evidence when a previously matched product is no longer found during a rescan.
- Returns the refreshed candidate queue from the authenticated rescan endpoint.
- Shows **No plugins awaiting review** when the actionable candidate count is zero.

### Compatibility

- Preserves `[sc_release_board]` and all inherited public and administrative shortcodes.
- Preserves the WordPress plugin folder and text domain.
- Preserves canonical Product Registry IDs, manual version governance, discovery locks, private plugin-path boundaries, and human review requirements.
- Requires no destructive database migration.
