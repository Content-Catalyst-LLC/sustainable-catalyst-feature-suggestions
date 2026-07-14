# Sustainable Catalyst Feature Suggestions v3.2.0

## Support Knowledge Base Foundation

Feature Suggestions v3.2.0 begins the transition from a feedback-only plugin into the Sustainable Catalyst Product Support and Feedback Platform.

## Included

### Support Articles

- Public `sc_support_article` WordPress content type.
- Revisions, excerpts, featured images, authorship, and WordPress REST support.
- Shared Product, Product Version, Component, Issue Type, and Release taxonomies.
- Hierarchical Documentation Collections and Article Types.
- Summary, audience, prerequisites, estimated reading time, and last-verified-version metadata.

### Article templates

- Getting Started.
- How-to Guide.
- Troubleshooting.
- Technical Reference.
- Known Issue Companion.
- Templates are inserted only into empty articles and never overwrite existing content.

### Known Issues

- Public `sc_known_issue` WordPress content type.
- Investigating, identified, workaround available, fix planned, resolved, monitoring, and closed states.
- Informational, minor, moderate, major, and critical severity levels.
- Symptoms or error message, workaround, resolution, first-observed date, and resolved date.

### Public Knowledge Base

- `[scfs_support_knowledge_base]` shortcode.
- Compatibility alias `[sustainable_catalyst_support_knowledge_base]`.
- `/support-knowledge-base/` public archive.
- `/known-issues/` public archive.
- Keyword, product, component, collection, and article-type filtering.
- Active known-issue notices.
- Responsive support article and known-issue cards.

### REST API

- `/scfs/v1/knowledge-base/schema`
- `/scfs/v1/knowledge-base/articles`
- `/scfs/v1/knowledge-base/articles/{id}`
- `/scfs/v1/knowledge-base/known-issues`
- `/scfs/v1/knowledge-base/known-issues/{id}`
- `/scfs/v1/knowledge-base/collections`
- `/scfs/v1/knowledge-base/templates`

### Relationships and privacy

- Support Articles and Known Issues can reference shared Release terms.
- Records can reference reviewed Feature Suggestion IDs.
- Public REST and page output include only suggestions explicitly approved for the Public Ideas directory.
- Private suggestion text, names, email addresses, and support-case information are never exposed.
- Contact and Engagement remains a separate private case-management platform.

## Administration

Open **Feature Suggestions → Knowledge Base** after activation. Support Articles and Known Issues also appear as dedicated submenu records.

Use this shortcode on a normal WordPress page when a theme-controlled page layout is preferred:

```text
[scfs_support_knowledge_base]
```

## Upgrade note

Plugin activation refreshes rewrite rules. If the new archive URLs return 404 after an unusual manual file replacement, open **Settings → Permalinks** and save the existing structure once.

## Validation

- All plugin PHP files passed syntax validation.
- 19 v3.2.0 release-structure checks passed.
- 8 lightweight plugin bootstrap checks passed.
- 11 WordPress registration-contract checks passed.
- 5 public Knowledge Base rendering smoke checks passed.
- 7 Python/FastAPI tests passed.
- WordPress and repository ZIP integrity is verified during packaging.
