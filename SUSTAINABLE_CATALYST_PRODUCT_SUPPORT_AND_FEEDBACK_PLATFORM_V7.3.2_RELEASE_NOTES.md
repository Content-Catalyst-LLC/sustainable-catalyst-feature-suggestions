# Product Support and Feedback Platform v7.3.2

## Compact Rotating Release Console

This release renames the public `[sc_release_board]` surface from **Release Telemetry** to **Release Console** while retaining the shortcode and all four layout identifiers.

### Delivered

- Five compact screens in fixed order: Foundation; Research and Intelligence; Data and Analysis; Creation and Systems; Commercial Release.
- Foundation is the initial screen and Commercial Release loops back to Foundation.
- Seven-second default rotation, configurable with `interval="7"` (3–60 seconds).
- Previous, pause/play, and next controls.
- Temporary pause while the console is hovered or contains keyboard focus.
- Reduced-motion users begin paused and receive transition-free presentation.
- Every product name and version is rendered as a non-navigating console label.
- Knowledge Library no longer links away or inherits anchor styling.
- Only `./releases` and `./support` remain navigable, both in the fixed footer.
- `terminal`, `blackboard`, `compact`, and `directory` layouts remain accepted. The default terminal layout rotates; legacy layouts retain their static projections.

### Shortcode

```text
[sc_release_board]
[sc_release_board interval="10"]
[sc_release_board rotate="no" layout="terminal"]
[sc_release_board layout="blackboard"]
```

### Compatibility

No database migration is required. The WordPress plugin directory, text domain, shortcode, canonical registry, public routes, and legacy layouts remain unchanged.
