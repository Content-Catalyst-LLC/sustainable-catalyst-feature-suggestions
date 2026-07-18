# Unified Support Center — v5.2.6

Version 5.2.6 makes `/support/` the canonical public destination for product guidance, troubleshooting, known issues, releases, feedback, and private-support continuation.

## Canonical page integration

Use the existing Support Center shortcode:

```text
[scfs_product_support_center mode="embedded" branding="sustainable-catalyst" default_view="resolve" anchor="support-center"]
```

The resolve and overview workspaces now include the complete Support Article browser automatically. A separate Knowledge Base page block is not required.

## Canonical Support Article location

The browser is available at:

```text
/support/?scfs_support_view=documentation#knowledge-base
```

Individual Support Articles remain at their existing `/support/guides/<article>/` permalinks.

## Legacy routes

The plugin permanently redirects `/support/knowledge-base/` and `/support-documentation/` to the embedded browser. Existing Knowledge Base query filters are carried to the new destination.

## Compatibility

The dedicated Knowledge Base shortcodes remain supported for third-party layouts and internal integrations. The route consolidation changes only the canonical Sustainable Catalyst public landing location.
