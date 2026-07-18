# Sustainable Catalyst Feature Suggestions v5.2.3

## Dynamic Documentation Records, Permalink Integrity, and Knowledge Base Interface Refinement

This patch turns the Support Library interface into a live WordPress documentation browser.

### Dynamic records

Both shortcodes now query published `sc_support_article` records. Product and category counts are calculated from those records rather than from destination-page assumptions.

### Compact Support Library

`[scfs_support_library_compact]` now expands categories inline and displays real article titles, summaries, verification metadata, update dates, and canonical WordPress permalinks. The full-catalog link preserves the selected product and category.

### Dedicated Knowledge Base

`[scfs_support_knowledge_base]` now uses a restrained two-column interface with a compact product navigator, category tabs, and editorial article rows. Search and filters are server rendered and remain functional without JavaScript.

### Link integrity

Article links are emitted only when WordPress returns a valid permalink for a published Support Article. Product and category navigation use query parameters on the dedicated Knowledge Base page, so no nonexistent landing pages are required.

### Backend

The Render backend remains compatible but is not required for documentation browsing, filtering, or keyword search.
