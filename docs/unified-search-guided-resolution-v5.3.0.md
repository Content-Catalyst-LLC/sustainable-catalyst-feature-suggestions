# Unified Search and Guided Resolution — v5.3.0

Version 5.3.0 connects the Support Discovery layer introduced in v5.2.9 with the existing Guided Resolution workflow. One public query now searches current Known Issues, publication-grade Support Articles, release context, and moderated public improvement records, then arranges the evidence into a deterministic resolution journey.

## Public workflow

1. Describe the task, symptom, or exact non-sensitive error fragment.
2. Optionally narrow by product, version, and component.
3. Review current Known Issues first.
4. Follow the highest-confidence verified Support Article.
5. Review release-specific context and limitations.
6. Consider related public improvements only after available support evidence.
7. Continue through the existing consent-gated private support handoff when unresolved.

## WordPress integration

Primary shortcode:

```text
[scfs_unified_support_search]
```

Compatibility alias:

```text
[scfs_unified_guided_resolution]
```

The existing `[scfs_guided_resolution]` shortcode remains available and unchanged. The canonical `/support/` page uses the unified renderer in the overview and resolve views, with the earlier Guided Resolution renderer retained as a runtime fallback.

## Public REST endpoints

```text
/wp-json/scfs/v1/unified-support/schema
/wp-json/scfs/v1/unified-support/search
/wp-json/scfs/v1/unified-support/journey
```

The existing Guided Resolution and Knowledge Base Discovery routes remain available.

## FastAPI parity

```text
GET  /v1/unified-support/capabilities
POST /v1/unified-support/search
POST /v1/unified-support/journey
```

The backend implementation is deterministic. WordPress remains the content and relationship source of truth.

## Ranking

The unified score combines:

- exact title and summary phrases;
- error-language matches;
- Support Discovery token and synonym matching;
- product, version, and component context;
- Known Issue status and severity;
- editorial promotion and priority;
- verified Support Article relevance;
- release context.

The score is advisory. It does not diagnose a product defect, alter content, publish records, declare incidents, change a roadmap, or create a private case.

## Privacy boundary

The public workflow must not receive passwords, API keys, personal data, confidential correspondence, private documents, or complete private logs. Search analytics remain privacy-minimized. Private support context is transferred only through the existing short-lived, consent-gated handoff.
