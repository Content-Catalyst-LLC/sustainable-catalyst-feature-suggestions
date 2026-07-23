# Canonical plugin mapping — v7.5.2

Plugin Discovery now lets an administrator resolve an installed plugin directly into the Canonical Product Registry.

## Candidate workflow

Each actionable candidate row includes:

- installed plugin name and version;
- plugin file, folder slug, text domain, author, activation scope, and Sustainable Catalyst headers;
- the discovery reason and any suggested canonical match;
- a **Map to canonical product** selector;
- **Leave awaiting review** and **Not a Sustainable Catalyst product** decisions;
- a nonce-protected **Save mapping decision** action.

Only registry records with `version_source=wordpress_plugin` and `discovery_enabled=1` appear in the selector.

## Mapping authority

An administrator mapping is stored separately from automatic discovery and is evaluated first. The selected product remains the identity authority. The installed plugin contributes implementation identifiers and discovered version evidence.

Depending on the existing product record, the plugin file, directory slug, and text domain become either the primary identifier or a legacy alias. The detected display name becomes a legacy name when it differs from the canonical public name.

The installed plugin folder is never renamed.

## Collision and duplicate safeguards

The system refuses a mapping when the candidate's plugin file, slug, or text domain already belongs to another canonical product. A deterministic duplicate candidate may be reassigned because the existing selected winner is retained and the duplicate identifiers are detached before the new mapping is saved.

Every manual mapping stores pre-change identifier snapshots. Removing the mapping restores those snapshots and reruns discovery.

## Ignore and restore

**Not a Sustainable Catalyst product** removes a candidate from the actionable queue without deleting the plugin or registry data. Ignored plugins remain visible to administrators in a separate panel and can be restored to review.

Ignored entries are pruned automatically when the underlying plugin is no longer installed.

## Live and fallback behavior

With JavaScript enabled, decisions are sent to the authenticated `/product-registry/discovery/decision` REST route. The server returns newly rendered fragments for the summary, matched products, review queue, and ignored plugins.

Without JavaScript, the same controls submit to a nonce-protected WordPress admin-post handler and return to the discovery screen with a status notice.

Both paths recalculate the queue immediately. When no actionable candidates remain, the screen displays **No plugins awaiting review**.
