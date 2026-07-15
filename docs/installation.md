
## v4.1.0 content operations

After activation, open **Feature Suggestions → Content Operations**. Select each Product taxonomy term, save its onboarding profile, create the missing starter records, import repository documentation or release history, and run validation before publishing.

The importer accepts JSON, Markdown, and text files up to 2 MB. Imported records default to draft and require human review.

The public Support Center can hide empty Knowledge Base, Known Issues, Releases, Public Ideas, and Surveys sections until matching public records exist.

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


## Documentation and Feature Intelligence v3.4.0

After upgrading, open **Feature Suggestions → Documentation Intelligence**. The plugin creates the article-feedback and support-relationship tables during activation or the first administrator request. Use **Refresh documentation gaps** after Guided Resolution has collected unresolved-search evidence.


## Product Support and Feedback Platform v4.0.0

Create or update the public support page with:

```text
[scfs_product_support_center]
```

The `/support-knowledge-base/` archive also renders the unified Support Center. Configure the Contact and Engagement destination and visible modules under **Feature Suggestions → Support Platform**. Add public release records under **Feature Suggestions → Releases**.


## Embedded Support Center and branding v4.0.2

For the Sustainable Catalyst Support page, use:

```text
[scfs_product_support_center mode="embedded" branding="sustainable-catalyst" default_view="resolve"]
```

Then open **Feature Suggestions → Support Platform** and configure:

- Default rendering mode: **Embedded**;
- Embedded default view: **Find a resolution**;
- Branding preset: **Sustainable Catalyst**, **Inherit active theme**, or **Custom**;
- embedded header, status, navigation-description, pathway, and page-width behavior;
- colors, fonts, radius, shadow, maximum width, and navigation columns.

After upgrading, clear any WordPress, optimization-plugin, CDN, and browser caches once. Version 4.0.2 uses source-file modification times for future public CSS cache invalidation.


## v4.0.2 navigation validation

After upgrading, clear page/CDN caches once and confirm that selecting Knowledge Base, Known Issues, Releases, Public Ideas, Suggest a Feature, Surveys, and Private Support updates the embedded workspace without jumping to the page top. Browser back and forward should restore prior views. With JavaScript disabled, the same links should reload at `#support-center`.
