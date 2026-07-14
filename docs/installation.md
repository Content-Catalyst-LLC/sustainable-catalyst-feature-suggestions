# Installation

## Plugin

Upload the plugin zip from:

```text
dist/sustainable-catalyst-feature-suggestions.zip
```

Then activate it in WordPress.

## Configure v2

After activation, open:

```text
Feature Suggestions → Settings
```

Recommended starting settings:

- Default saved status: **Pending Review**
- Strict WordPress nonce validation: **Off** if the page may be cached
- Email notifications: **On**
- Max submissions per hour: **5**
- Max submissions per day: **20**
- Duplicate detection window: **24 hours**
- Maximum links: **4**

## Page

Create the page:

```text
/platform/feature-suggestions/
```

Add the shortcode:

```text
[sustainable_catalyst_feature_suggestions]
```

Optionally paste the surrounding HTML from:

```text
docs/feature-suggestions-page.html
```

Add the CSS from:

```text
docs/feature-suggestions-site.css
```

to the end of the site CSS if you are using the provided page wrapper.

## Support Knowledge Base v3.2.0

After activation, open:

```text
Feature Suggestions → Knowledge Base
```

You can use the automatic archive at:

```text
/support-knowledge-base/
```

or create a normal WordPress page and add:

```text
[scfs_support_knowledge_base]
```

Known Issues use:

```text
/known-issues/
```

If either archive returns a 404 after a manual server-side plugin replacement, open **Settings → Permalinks** and save the current permalink structure once.


## Search and Guided Resolution v3.3.0

After upgrading, visit **Feature Suggestions → Guided Resolution** and configure the Contact and Engagement destination URL. The activation routine creates the privacy-minimized search analytics table. Add `[scfs_guided_resolution]` to a page or use the default `/support-knowledge-base/` archive.

The Python validation environment must use Python 3.12 or 3.13 and install `backend/requirements.txt`, which now pins `httpx` for FastAPI TestClient compatibility.
