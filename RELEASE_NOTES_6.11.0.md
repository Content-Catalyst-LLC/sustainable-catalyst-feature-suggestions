# Product Support and Feedback Platform v6.11.0

## API, Webhooks, and External Integrations

v6.11.0 adds a private integration control plane for scoped case-summary APIs, signed outbound webhooks, retry and dead-letter operations, and human-authorized links to GitHub, repositories, monitoring systems, Contact and Engagement, and institutional systems.

### Added

- Nine additive private integration tables.
- Least-privilege API scopes.
- HTTPS-only signed outbound webhook subscriptions.
- HMAC-SHA256 event signatures and payload fingerprints.
- Exponential retry scheduling and dead-letter review.
- Credential references and fingerprints without raw secret storage.
- Human-authorized external-system relationships.
- Integration checkpoints and append-only audit evidence.
- Private WordPress REST, FastAPI, WP-CLI, CSS, and JavaScript operations.

### Governance

The release does not add a public case API or public inbound webhook. It does not expose requester identity, private messages, internal notes, attachment content, credentials, or secure URLs. External issue creation, case transitions, and customer communication remain human-controlled.

### Compatibility

All existing public support, private case, portal, evidence, workflow, email, analytics, and institutional workspace records remain unchanged. Activation adds only the v6.11.0 integration tables and capabilities.
