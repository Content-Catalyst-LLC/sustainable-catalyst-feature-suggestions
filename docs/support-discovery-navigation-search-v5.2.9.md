# Support Discovery, Navigation, and Search Quality — v5.2.9

Version 5.2.9 turns the embedded Support Article browser into a relevance-ranked, version-aware publication discovery system.

## Search behavior

Search ranks exact title phrases first, followed by summaries, product and version context, taxonomy terms, and article body matches. A deterministic synonym map recognizes common support language such as broken/error, install/setup, API/endpoint, export/download, and mobile/responsive.

## Navigation

The browser keeps product, version, category, component, article type, search, and sort state in ordinary URL parameters. Breadcrumbs and removable active-filter chips make the current scope explicit. Existing Support Article URLs remain unchanged.

## Recovery

A no-results state offers narrow-filter removal, recent guidance, related query suggestions, and recently updated articles in the current product or category.

## Accessibility and privacy

The results region announces count changes, search can be focused with the `/` key, Escape clears the search field, and every feature works without JavaScript. Search queries remain browser request parameters; this release does not create a personal search-history database.
