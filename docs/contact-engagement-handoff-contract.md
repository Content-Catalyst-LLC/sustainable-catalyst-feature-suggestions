# Contact and Engagement Handoff Contract

Feature Suggestions v3.1.0 defines, but does not automatically execute, the handoff from public product feedback to a private Contact and Engagement support case.

## Architectural boundary

**Feature Suggestions / Product Support and Feedback** owns:

- public suggestions
- public documentation and known issues in later releases
- product, version, component, issue, and release context
- voting, surveys, and product intelligence

**Contact and Engagement** owns:

- private support cases
- sender communication
- private documents
- internal case status and lifecycle management

## Contract

Schema identifier:

```text
sc-contact-engagement-handoff/1.0
```

The contract includes:

- source suggestion identity and correlation metadata
- shared product context
- requester name, email, and follow-up consent when stored
- problem, requested change, success criteria, and relevant URL
- relationships to GitHub, roadmap area, and target release
- private classification and personal-data flags
- destination queue and human-review requirement

## Safety and privacy behavior

- The schema endpoint is public so integrations can validate compatibility.
- The actual suggestion handoff endpoint requires an authenticated user with `edit_posts`.
- Automatic case creation is disabled.
- The payload is classified as private.
- Public events and public taxonomy endpoints do not disclose requester details or free text.

Schema endpoint:

```text
/wp-json/scfs/v1/contact-engagement/handoff-schema
```

Protected payload endpoint:

```text
/wp-json/scfs/v1/suggestions/{id}/contact-engagement-handoff
```

Integration extensions can adjust the final authorized payload with:

```php
add_filter('scfs_contact_engagement_handoff_payload', function ($payload, $suggestion_id) {
    return $payload;
}, 10, 2);
```
