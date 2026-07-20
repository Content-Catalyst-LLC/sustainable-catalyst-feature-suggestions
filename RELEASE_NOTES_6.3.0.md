# Product Support and Feedback Platform v6.3.0

**Customer Support Portal and Conversations**

v6.3.0 adds the secure requester-facing help-desk portal above the private case foundation and Agent Workspace.

## Added

- Token-to-session secure access exchange with hash-only token and session storage.
- Customer portal shortcode and compatibility alias.
- Participant-visible case conversation with requester replies.
- Requester-confirmed resolution and bounded reopening.
- Private satisfaction feedback.
- Contact and Engagement notification queue authority.
- Agent controls for issuing and revoking portal access.
- Five additive private portal tables.
- Authenticated customer REST routes and protected administrative routes.
- FastAPI validation parity, JSON Schema, examples, tests, CSS, JavaScript, and WP-CLI commands.

## Privacy

The portal does not expose internal notes, requester or organization references, private attachment bytes, assignment history, or a public case list. Raw access tokens and session secrets are never stored.

## Compatibility

All existing `scfs_*` identifiers, public Support Center behavior, Support Article URLs, CPTs, taxonomies, REST namespace, private cases, queues, assignments, and Contact and Engagement boundaries remain intact.
