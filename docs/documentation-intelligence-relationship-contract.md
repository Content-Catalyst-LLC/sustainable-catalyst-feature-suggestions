# Contact and Engagement Relationship Contract

Schema: `scfs-documentation-feature-intelligence/1.0`

Endpoint:

```text
POST /wp-json/scfs/v1/documentation-intelligence/relationships
```

Authentication: a WordPress user or integration identity with `edit_posts` capability.

## Request

```json
{
  "case_reference": "support:CASE-2026-0042",
  "relationship_type": "case_article",
  "object_id": 812,
  "source_system": "contact_and_engagement",
  "outcome": "resolved",
  "evidence_weight": 1.5,
  "product_context": {
    "product": "research-librarian",
    "product_version": "v7-0-0",
    "component": "wordpress-endpoint"
  }
}
```

Supported relationship types:

- `case_article`: the private case used or was associated with a published Support Article.
- `case_suggestion`: the private case provides demand evidence for an existing Feature Suggestion.

Supported outcomes: `linked`, `viewed`, `resolved`, `unresolved`, and `escalated`.

The case reference must be opaque and limited to letters, numbers, periods, underscores, colons, and hyphens. The plugin stores no requester contact details or private case content.
