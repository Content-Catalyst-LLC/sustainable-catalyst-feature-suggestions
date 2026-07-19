# Public API, Embeds, and Institutional Support Integration

## Public API

The WordPress REST namespace remains `scfs/v1`. Public routes are read-only and return only published Support Articles, Known Issues, release records, and product contracts.

## Embed shortcode

```text
[scfs_support_embed product="decision-studio" version="2.0.1" component="briefing" view="compact" limit="6"]
```

Supported views are `compact`, `standard`, `articles`, `issues`, and `releases`.

## Version verification

Version verification matches a requested product version against public release records. A non-match is reported as `not-found`; it is never represented as an unsupported or defective release without human review.

## Institutional contracts

Institutional contracts describe transport, authentication, stability, data classification, and privacy boundaries. Administrator routes expose contract and health metadata but not private support records.

## Access governance

Sites may keep the public API open, require a site-managed public key, restrict browser origins, set cache duration, and define a request-governance target. Public APIs remain read-only.
