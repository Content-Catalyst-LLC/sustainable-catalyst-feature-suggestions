# Release Blackboard Shortcode v7.3.0

The Product Support and Feedback Platform now owns the public Catalyst release board through the canonical product registry.

## Basic homepage board

```text
[sc_release_board]
```

The default context is `homepage`, so only products approved for public homepage display are included. Inactive products are hidden.

## Selected groups

```text
[sc_release_board groups="foundation,research,data,systems,commercial"]
```

Aliases map to the canonical families: `research` to `research-intelligence`, `data` to `data-analysis`, and `systems` to `creation-systems`.

## Selected products

```text
[sc_release_board products="sustainable-catalyst-core,product-support-feedback,contact-engagement,knowledge-library"]
```

## Compact and directory layouts

```text
[sc_release_board layout="compact" limit="12"]
[sc_release_board layout="directory" context="directory" inactive="show"]
```

## Supported attributes

- `layout`: `blackboard`, `compact`, or `directory`
- `context`: `homepage`, `directory`, or `generic`
- `groups`: comma-separated family names or aliases
- `products`: comma-separated canonical product identifiers
- `limit`: maximum product count; `0` means no limit
- `show_status`: `yes` or `no`
- `show_updated`: `yes` or `no`
- `show_links`: `yes` or `no`
- `inactive`: `hide` or `show`
- `title`: board heading
- `heading_level`: integer from 2 through 6

## Version authority

Installed WordPress products use governed plugin discovery. Catalyst Intelligence remains a manual commercial-product record until a later remote-manifest release. The public projection never exposes plugin file paths, server paths, or private repository metadata.

## Cache behavior

Rendered boards use a short transient cache. Registry saves and completed plugin-discovery scans advance the release-board cache epoch, so public output refreshes without requiring homepage edits.
