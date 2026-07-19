# Sustainable Catalyst Product Support and Feedback Platform v5.2.9

## Support Discovery, Navigation, and Search Quality

Version 5.2.9 improves the unified Support Center without changing its public architecture or existing data contracts.

### Weighted and version-aware search

- Exact title and summary phrases receive the strongest relevance weight.
- Product, component, article type, collection, and version context participate in ranking.
- Common support synonyms connect terms such as broken/error, install/setup, API/endpoint, export/download, and mobile/responsive.
- Results can be sorted by best match, recently updated, or title.

### Clear navigation state

- Publication-library breadcrumbs describe the active product, version, and category.
- Active filters appear as individually removable chips.
- Product, version, category, component, type, search, and sort remain deep-linkable query parameters.
- The existing `/support/#knowledge-base` location and all `/support/guides/` article URLs are preserved.

### No-results recovery

- Narrow filters can be removed in one action.
- Related query suggestions are generated deterministically.
- Recently updated articles from the active product or category are surfaced.
- The reader can return to the complete recent-guidance catalog.

### Accessibility and progressive enhancement

- Results use an ARIA live region.
- `/` focuses Support Article search and Escape clears it.
- The browser remains fully functional as server-rendered HTML without JavaScript.
- Responsive and print styles cover the new discovery components.

### API and analytical parity

- New WordPress REST routes expose discovery schema, search, and suggestions under `scfs/v1`.
- FastAPI adds deterministic support-discovery scoring and search endpoints.
- Search does not create a personal search-history database.

### Compatibility

No database migration is required. All existing `scfs_*` identifiers, post types, REST namespace, shortcodes, Support page integration, legacy redirects, settings, records, and article URLs remain intact.
