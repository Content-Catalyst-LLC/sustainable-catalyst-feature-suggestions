# Embedded Support Center Branding

Feature Suggestions v4.0.2 provides a native embedded mode and a scoped design-token system for integrating the Product Support Center into a page that already has its own hero, content hierarchy, and site shell.

## Recommended Sustainable Catalyst integration

```text
[scfs_product_support_center mode="embedded" branding="sustainable-catalyst" default_view="resolve"]
```

This configuration:

- opens at Guided Resolution;
- removes the duplicate application hero;
- hides the all-zero status row;
- uses compact navigation labels;
- removes the duplicate overview pathway grid;
- lets the containing page own the outer width;
- applies Sustainable Catalyst maroon, black, white, cream, Spartan, and Montserrat tokens.


## Navigation reliability in v4.0.2

Support Center navigation now switches the embedded workspace in place. The URL is updated with `scfs_support_view`, product context is preserved, and browser back/forward buttons restore the corresponding workspace. Direct and no-JavaScript links include the configured anchor, which defaults to `#support-center`.

Optional shortcode controls:

```text
anchor="support-center"
interactive="1"
```

Set `interactive="0"` to force traditional full-page navigation. When interactive navigation cannot reach the read-only REST view endpoint, the browser automatically follows the anchored direct link.

## Admin configuration

Open **Feature Suggestions → Support Platform**.

### Platform settings

Set the Support Center title, introduction, default view, rendering mode, Contact and Engagement destination, visible modules, and release limit.

### Branding preset

- **Platform** uses the neutral plugin design system.
- **Sustainable Catalyst** uses the site-specific brand preset.
- **Inherit active theme** resolves Astra and WordPress preset variables where available and otherwise uses safe fallbacks.
- **Custom** uses the saved design-token controls.

### Design tokens

The settings screen supports:

- accent and accent-contrast colors;
- ink and muted text colors;
- primary and soft surfaces;
- border color;
- success, warning, and danger colors;
- body and heading font stacks;
- border radius;
- shadow depth;
- maximum application width;
- one to four navigation columns.

### Embedded behavior

The administrator can independently suppress:

- the internal Support Center header;
- the status metric row;
- navigation descriptions;
- the overview pathway grid;
- the plugin maximum width.

Zero-value status metrics can also be hidden in either mode.

## Shortcode attributes

Core rendering attributes:

```text
mode="standalone|embedded"
branding="platform|sustainable-catalyst|inherit|custom"
default_view="overview|resolve|knowledge|issues|releases|ideas|suggest|surveys|private"
compact="0|1"
```

Visibility and layout attributes:

```text
show_header="0|1"
show_status="0|1"
show_product_filter="0|1"
show_navigation="0|1"
show_nav_descriptions="0|1"
show_overview_pathways="0|1"
hide_zero_status="0|1"
use_page_width="0|1"
nav_columns="1|2|3|4"
class="additional-class-name"
```

Brand token attributes:

```text
accent="#9b1111"
accent_contrast="#ffffff"
ink="#000000"
muted="#555555"
surface="#ffffff"
soft="#f7f3ea"
line="#d9d2c4"
success="#176b3a"
warning="#8a5a00"
danger="#9b1111"
font_family="Montserrat, Arial, sans-serif"
heading_font_family="Spartan, Montserrat, Arial, sans-serif"
radius="0"
shadow="none|subtle|raised"
max_width="1180"
```

## Custom example

```text
[scfs_product_support_center
  mode="embedded"
  branding="custom"
  default_view="resolve"
  accent="#9b1111"
  accent_contrast="#ffffff"
  ink="#000000"
  muted="#555555"
  surface="#ffffff"
  soft="#f7f3ea"
  line="#d9d2c4"
  font_family="Montserrat, Arial, sans-serif"
  heading_font_family="Spartan, Montserrat, Arial, sans-serif"
  radius="0"
  shadow="none"
  nav_columns="3"
]
```

WordPress accepts multiline shortcodes only when the editor preserves them correctly. A single-line version is safest in the Shortcode block.

## Theme inheritance

The `inherit` preset reads active-site variables rather than copying theme CSS. It supports common Astra variables and WordPress global style presets, then uses accessible plugin fallbacks when a variable is absent.

Because all public rules remain scoped below `.scfs-support-platform`, the plugin does not restyle the surrounding page.

## CSS collision safeguards

Version 4.0.2 explicitly protects application navigation, buttons, fields, cards, and child modules from broad theme rules that apply uppercase text, red backgrounds, large headings, or generic link-button styling.

The branding tokens flow into:

- Guided Resolution;
- Support Knowledge Base;
- Known Issues;
- Release Intelligence;
- public ideas and voting;
- suggestion forms;
- dynamic forms and surveys;
- private-support continuation.

## Extension filters

Developers can modify tokens after validation:

```php
add_filter('scfs_support_branding_tokens', function ($tokens, $preset, $settings, $atts) {
    $tokens['accent'] = '#7a1020';
    return $tokens;
}, 10, 4);
```

Shortcode attributes can also be modified before the display context is resolved:

```php
add_filter('scfs_product_support_center_atts', function ($atts) {
    $atts['mode'] = 'embedded';
    return $atts;
});
```

## Accessibility and privacy

- Active navigation uses `aria-current="page"`.
- A screen-reader heading remains when the visual internal header is removed.
- Responsive navigation collapses from multiple columns to two and then one.
- Reduced-motion preferences are respected.
- Branding changes do not alter the platform boundary: private identity, correspondence, documents, and case lifecycle remain in Contact and Engagement.
