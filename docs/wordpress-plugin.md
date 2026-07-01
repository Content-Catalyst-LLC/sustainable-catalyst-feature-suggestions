# WordPress Plugin Notes

## Shortcode

```text
[sustainable_catalyst_feature_suggestions]
```

## Public behavior

The shortcode renders a structured feature suggestion form with categories, priority, page/repository reference, contact fields, follow-up permission, consent, and honeypot spam protection.

## Admin behavior

The plugin registers a private post type for suggestions and provides a CSV export submenu.

## Security notes

The plugin uses WordPress nonce validation and sanitization. It is still intended as a lightweight first version and should be reviewed before high-traffic use.
