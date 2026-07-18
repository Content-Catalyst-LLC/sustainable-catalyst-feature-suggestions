# Sustainable Catalyst Feature Suggestions v5.2.2

## Compact Support Library, Dedicated Knowledge Base Page, and Shortcode Separation

v5.2.2 separates the Support page entry experience from the complete documentation catalog.

### Compact Support Library

Use `[scfs_support_library_compact]` inside the main Support page where the documentation discovery component should appear. It provides a restrained Knowledge Library-style search command, expandable product rows, generated documentation-category links, guide counts, and a button to the complete Knowledge Base.

The compact component does not replace, prepend, or rearrange the surrounding Support page content.

### Dedicated Knowledge Base

Use `[scfs_support_knowledge_base]` on a dedicated Knowledge Base page, preferably `/support/knowledge-base/`. The full shortcode continues to provide the complete product and category browser, search, generated article lists, article metadata, and expansion controls.

### Placement and routing

- Removed automatic full Knowledge Base injection from the Support page.
- Added separate compact and expanded shortcodes.
- Added direct product and category links from the compact component into the complete catalog.
- Preserved the publication-style Support Article experience.
- Preserved WordPress-first rendering with no Render backend dependency.

### Shortcodes

```text
[scfs_support_library_compact]
[scfs_support_knowledge_base]
```

Optional compact attributes include `title`, `intro`, `products`, `open_first`, `button_label`, and `knowledge_base_url`.
