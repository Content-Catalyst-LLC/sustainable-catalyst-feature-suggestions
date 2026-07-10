# Feature Suggestions v2.1 Integration API

## REST namespace

```text
/wp-json/scfs/v1
```

Public endpoints:

```text
GET  /health
GET  /schema
POST /suggestions
```

Administrative endpoints require an authenticated WordPress user with `edit_posts`:

```text
GET   /suggestions
GET   /suggestions/{id}
PATCH /suggestions/{id}
POST  /suggestions/{id}
```

## Public submission example

```json
{
  "title": "Add infrastructure interdependency calculator",
  "category": "New platform module",
  "problem": "The current research path explains interdependence but cannot test scenarios.",
  "suggestion": "Add a Workbench calculator with dependency matrices and failure propagation.",
  "priority": "High",
  "consent": true,
  "source": "research_librarian",
  "correlation_id": "rl-session-opaque-id"
}
```

When API-key protection is enabled, send:

```text
X-SCFS-API-Key: configured-secret
```

## Shared WordPress events

The plugin publishes both hooks when shared events are enabled:

```php
do_action('scfs_event', $event_type, $event);
do_action('sc_platform_event', $event);
```

Current event types:

```text
feedback.submitted
feedback.reviewed
feedback.status_changed
```

## Privacy boundary

Shared and webhook event payloads include identifiers, taxonomy, workflow state, source, correlation ID, roadmap area, and impact/effort scores. They deliberately exclude names, email addresses, IP hashes, user agents, and free-text problem/suggestion bodies.

## Webhook signing

When a webhook secret is configured, the plugin sends:

```text
X-SCFS-Signature: sha256=<hex HMAC digest>
```

The digest is HMAC-SHA256 over the exact JSON request body. Failed deliveries enter a bounded retry queue and are retried by WordPress cron with exponential backoff.
