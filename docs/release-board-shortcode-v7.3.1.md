# Release Telemetry v7.3.1

The Product Support and Feedback Platform now presents the governed product registry as **Release Telemetry**: a compact terminal-inspired homepage surface designed to complement Live Intelligence.

## Default homepage telemetry

```text
[sc_release_board]
```

The shortcode now defaults to:

- `layout="terminal"`
- `context="homepage"`
- `title="Release Telemetry"`
- status, source, header, footer, and last-sync metadata enabled

Only products approved for public homepage display are included. Inactive products remain hidden by default.

## Explicit terminal configuration

```text
[sc_release_board
  layout="terminal"
  context="homepage"
  title="Release Telemetry"
  show_header="yes"
  show_footer="yes"
  show_source="yes"
  show_status="yes"
  show_updated="yes"
  dense="yes"
]
```

## What the terminal surface communicates

- current public version
- governed release state
- plugin or manual version source
- product family
- registry/discovery state
- displayed-system counts
- last verified or discovery synchronization time
- links to release history and Support

## Required homepage products

The v7.3.1 registry migration ensures that **Knowledge Library** remains public and homepage-visible. The required foundation set is:

- Sustainable Catalyst Core
- Product Support and Feedback Platform
- Contact and Engagement Platform
- Knowledge Library

## Analytics R naming

The canonical identifier remains `catalyst-analytics-r`, but the public name is now **Catalyst Analytics R** and the telemetry label is **Analytics R**. The previous `Catalyst AnalyticsR` spelling is retained only as a legacy alias.

## Selected groups

```text
[sc_release_board groups="foundation,research,data,systems,commercial"]
```

Aliases map to canonical families: `research` to `research-intelligence`, `data` to `data-analysis`, and `systems` to `creation-systems`.

## Selected products

```text
[sc_release_board products="sustainable-catalyst-core,product-support-feedback,contact-engagement,knowledge-library,catalyst-analytics-r,catalyst-intelligence"]
```

## Alternative layouts

```text
[sc_release_board layout="blackboard"]
[sc_release_board layout="compact" limit="12"]
[sc_release_board layout="directory" context="directory" inactive="show"]
```

## Supported attributes

- `layout`: `terminal`, `blackboard`, `compact`, or `directory`
- `context`: `homepage`, `directory`, or `generic`
- `groups`: comma-separated family names or aliases
- `products`: comma-separated canonical product identifiers
- `limit`: maximum product count; `0` means no limit
- `show_status`: `yes` or `no`
- `show_updated`: `yes` or `no`
- `show_links`: `yes` or `no`
- `show_header`: `yes` or `no`
- `show_footer`: `yes` or `no`
- `show_source`: `yes` or `no`
- `dense`: `yes` or `no`
- `inactive`: `hide` or `show`
- `title`: public heading
- `heading_level`: integer from 2 through 6

## Version authority

Installed WordPress products use governed plugin discovery. Catalyst Intelligence and Analytics R can remain manually governed until controlled package or remote-manifest sources are enabled. The public projection never exposes plugin file paths, absolute server paths, or private repository metadata.

## Cache behavior

Rendered telemetry uses a short transient cache. Registry saves and completed plugin-discovery scans advance the cache epoch. Cached markup replaces its instance placeholder on every render so multiple telemetry surfaces on one page retain unique accessible IDs.
