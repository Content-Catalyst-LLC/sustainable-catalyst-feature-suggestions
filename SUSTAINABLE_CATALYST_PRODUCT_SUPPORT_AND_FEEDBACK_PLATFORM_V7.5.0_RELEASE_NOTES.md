# Product Support and Feedback Platform v7.5.0

## Release Intelligence and Console Copy Controls

v7.5.0 makes the public Release Console more informative and makes its presentation language editable without weakening Product Registry governance.

### Release intelligence

Each governed product may now carry a previous version, release date, concise change summary, validation state, documentation state, and known-issue count. The console can also identify recently updated, maintenance, and superseded records. These fields remain registry facts and cannot be replaced through presentation-copy settings or shortcode text overrides.

### Console copy controls

A new **Release Console Copy** WordPress administration page controls the public title, introductions, five screen headings, column and summary labels, previous/pause/play/next controls, accessibility labels, footer labels, intelligence labels, and empty or unavailable messages.

The copy layer uses three ordered sources:

1. Built-in safe defaults.
2. WordPress settings stored in `scfs_release_console_copy`.
3. Shortcode overrides for common page-specific presentation text.

Developers may use the `scfs_release_console_copy` filter for controlled presentation customization. Product names, versions, lifecycle states, validation evidence, and release intelligence remain governed by the canonical Product Registry.

### Shortcode compatibility

`[sc_release_board]` remains unchanged. Terminal, blackboard, compact, and directory layouts remain supported. New optional attributes include `show_intelligence`, `intro`, screen-label overrides, control-label overrides, footer-label overrides, and fallback messages.

### Accessibility and resilience

The existing seven-second configurable interval, previous/pause/play/next controls, hover and keyboard-focus pausing, reduced-motion handling, multiple-instance protection, no-JavaScript fallback, stable screen height, and fixed footer behavior are preserved.
