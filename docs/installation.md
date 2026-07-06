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
