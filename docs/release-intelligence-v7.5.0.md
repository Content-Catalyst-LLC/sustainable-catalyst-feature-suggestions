# Release Intelligence v7.5.0

The canonical Product Registry now supports these public release-intelligence fields:

- `previous_version`
- `release_date`
- `change_summary`
- `validation_state`
- `documentation_state`
- `known_issue_count`
- derived `recently_updated`

Validation states are `validated`, `partial`, `pending`, `failed`, and `unavailable`. Documentation states are `ready`, `partial`, `missing`, and `unavailable`. A release is considered recently updated for 45 days after its governed release date.

All values require human-controlled registry maintenance. Presentation copy cannot override them.
