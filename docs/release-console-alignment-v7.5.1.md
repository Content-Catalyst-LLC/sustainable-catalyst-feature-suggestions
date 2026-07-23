# Release Console alignment contract — v7.5.1

The terminal Release Console uses `--scfs-release-console-columns` as the single responsive column definition for both `.scfs-release-board__column-labels` and `.scfs-release-board__product-line`.

The prompt, product identity, Version, State, and Source cells have explicit grid positions. The grid adapts when State or Source output is disabled and hides Source at narrower container widths. Release-intelligence badges are nested inside `.scfs-release-board__product-identity`, directly beneath the non-navigating product label.

The footer uses compact named grid areas for diagnostics, last-verified text, and the fixed Release and Support links. Accessibility, reduced-motion, keyboard operation, no-JavaScript output, and legacy layouts remain unchanged.
