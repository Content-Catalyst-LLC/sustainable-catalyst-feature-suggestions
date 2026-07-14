# Feature Suggestions v3.3.0 — Search and Guided Resolution

Version 3.3.0 turns the Support Knowledge Base into a guided support-resolution workflow while preserving Contact and Engagement as the private case-management platform.

## Public workflow

1. Describe the task, symptom, or problem.
2. Add a short, non-sensitive error fragment when available.
3. Narrow the search by product, product version, and component.
4. Review current known issues first.
5. Review suggested support articles, releases, and related public feature ideas.
6. If unresolved, consent to carry the search context into Contact and Engagement.

## Ranking signals

The WordPress ranking engine is deterministic and inspectable. It uses exact phrase matches, token overlap, configured synonyms, per-record aliases, error signatures, taxonomy context, issue lifecycle status, issue severity, and editorial promotion. The engine does not make implementation or support-case decisions automatically.

## Privacy boundary

Search analytics store a redacted query, query hash, filter context, result count, confidence, resolution state, and viewed public record identifiers. IP addresses are not stored. Error text is retained only as a hash. Search text is redacted for common email and secret patterns before storage.

Feature Suggestions does not store a private support case. An unresolved user can create a short-lived handoff token after explicit consent. Contact and Engagement retrieves that token and collects any identity, communication, documents, or private case details in its own workflow.

## Shortcodes

```text
[scfs_guided_resolution]
[scfs_support_knowledge_base]
```

The first shortcode is the default support archive experience. The second remains available for direct documentation browsing.

## REST API

- `GET /wp-json/scfs/v1/guided-resolution/schema`
- `GET|POST /wp-json/scfs/v1/guided-resolution/search`
- `GET /wp-json/scfs/v1/guided-resolution/handoff-schema`
- `GET /wp-json/scfs/v1/guided-resolution/handoffs/{token}`
- `GET /wp-json/scfs/v1/guided-resolution/analytics` — authenticated editors only

## Administration

The Guided Resolution screen provides synonym mappings, global promoted results, confidence thresholds, result limits, analytics retention, the Contact and Engagement destination URL, unresolved-search counts, and a CSV export. Individual articles and known issues include aliases, stable error signatures, promotion, and editorial priority controls.
