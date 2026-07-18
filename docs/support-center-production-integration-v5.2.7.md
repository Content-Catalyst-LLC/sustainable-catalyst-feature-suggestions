# Support Center Production Integration — v5.2.7

## Public contract

The canonical Support Center is `/support/`. The Support Article browser is an embedded section at `#knowledge-base`; it is not a second public application or landing page.

## Supported page configurations

| Support page source | v5.2.7 behavior |
|---|---|
| Contains `[scfs_product_support_center]` | Preserved as authored |
| Contains `[scfs_support_center]` | Preserved as authored |
| Contains only `[scfs_support_knowledge_base]` | Replaced by the unified Support Center |
| Contains only the legacy Knowledge Base alias | Replaced by the unified Support Center |
| Contains no support shortcode | Unified Support Center appended automatically |
| Reusable block produces a second Support Center | Duplicate request signature suppresses the second render |

## Canonical anchors

- `support-center`
- `guided-resolution`
- `knowledge-base`
- `known-issues`
- `release-intelligence`

## Debugging

Administrators may open `/support/?scfs_support_debug=1` to expose the integration source, active view, and product context. This output is not shown to ordinary visitors.

## Opt-out and multiple instances

Automatic integration can be disabled through the `auto_integrate_support_page` Product Support Platform setting. Duplicate suppression can be disabled globally through `suppress_duplicate_centers`, or locally with `allow_duplicate="1"` and a unique `anchor` shortcode attribute.

## Privacy boundary

Automatic page integration changes only public presentation. It does not create private cases, collect requester identities, expose Contact and Engagement records, or move private documents into the Support platform.
