# Sustainable Catalyst Feature Suggestions v5.2.1

## Support Page Library Interface and Automatic Knowledge Base Rendering

This patch turns the assigned Support page into the public documentation library itself.

### Included

- Automatically injects the Knowledge Base on the main Support page when no Knowledge Base shortcode is already present.
- Uses a Library-style product-domain browser as the default discovery view.
- Opens the first product domain by default and keeps the remaining products compact.
- Adds expandable documentation categories beneath each product.
- Preserves generated article counts, search, product filtering, article metadata, and publication-style article pages.
- Adds Library-parity black, cream, white, and red presentation without changing publication pages.
- Prevents duplicate rendering when editors already placed the Knowledge Base shortcode.

### Compatibility

The public page detector recognizes `support`, `product-support`, and `support-center`, plus the optional `scfs_support_page_slug` setting.
