# Research Librarian Contextual Feedback Integration

Version 2.7.0 adds an optional adapter between Research Librarian and Feature Suggestions. The products remain independently deployable.

## Public embed

```text
[sc_librarian_feedback feedback_type="route_incorrect" route_id="infrastructure-resilience" query_topic="Infrastructure resilience"]
```

The form accepts route, source, article-map, destination, session-reference, answer-reference, and prompt-reference context. It deliberately excludes raw conversations, names, email addresses, IP addresses, and API credentials.

## REST API

- `GET /wp-json/scfs/v1/librarian/feedback/schema`
- `POST /wp-json/scfs/v1/librarian/feedback`
- `GET /wp-json/scfs/v1/librarian/feedback/{uuid}/status?receipt_token=...`
- `GET /wp-json/scfs/v1/librarian/handoff`

The submission response includes a UUID and a private receipt token. The token is required for public status lookup and expires after 180 days.

## WordPress action

Research Librarian can submit a typed payload without calling HTTP:

```php
do_action('scfs_research_librarian_feedback', array(
    'feedback_type' => 'missing_tool',
    'details' => 'The visitor expected an infrastructure interdependency calculator.',
    'consent' => true,
    'route_id' => 'infrastructure-resilience',
    'query_topic' => 'Infrastructure resilience',
));
```

## Shared event

Accepted records publish `librarian.feedback_submitted` through `scfs_event`, `sc_platform_event`, and the existing signed webhook queue. Events contain normalized feedback type, rating, route/source identifiers, query topic, UUID, and timestamp—not raw feedback text.

## Review boundary

Contextual records enter the normal Feature Suggestions human-review workflow. They are not automatically approved, rejected, published, scored, or converted to roadmap items.
