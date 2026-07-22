# Product Support and Feedback Platform v7.3.3

## Release Console Reliability and Presentation Repair

This patch hardens the public Release Console without changing `[sc_release_board]`, its five-screen sequence, or the seven-second default interval.

### Improvements

- Stabilizes the console at the tallest screen height so content and the fixed footer do not jump.
- Repairs desktop, tablet, and mobile control alignment and prevents Astra button styles from leaking into the console.
- Keeps all release groups readable when JavaScript is unavailable while hiding inactive controls.
- Supports multiple shortcode instances, cached markup, and dynamically inserted consoles without duplicate timers.
- Adds Left/Right Arrow, Home, End, and Space keyboard operation.
- Announces manual screen changes and pause/play state without narrating automatic rotation.
- Preserves reduced-motion behavior, hover/focus pausing, label-only products, and footer-only Release and Support navigation.

### Compatibility

- No database migration is required.
- The WordPress directory and text domain remain `sustainable-catalyst-feature-suggestions`.
- `terminal`, `blackboard`, `compact`, and `directory` layouts remain supported.
- Knowledge Library remains visible and non-navigating.
