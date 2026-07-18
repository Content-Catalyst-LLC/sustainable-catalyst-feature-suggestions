# Sustainable Catalyst Feature Suggestions v5.2.4

## Knowledge Base Route Integrity and Dedicated Page Recovery

- Moves Support Article permalinks from `/support/{article}/` to `/support/guides/{article}/`, preventing the article rewrite rule from capturing the dedicated `/support/knowledge-base/` page.
- Moves the post-type archive to `/support-documentation/` so it cannot collide with the dedicated page.
- Provisions or repairs the published child page at `/support/knowledge-base/` without overwriting authored page content.
- Adds an upgrade-safe one-time rewrite flush for active-plugin replacements.
- Resolves compact Knowledge Base buttons from the stored page ID and real permalink.
- Preserves the compact and expanded shortcodes and all dynamic article queries.
