# Sustainable Catalyst Product Support and Feedback Platform v6.8.0

## Email and Channel Operations

### Purpose

Connect the private help desk to governed email and Microsoft Teams workflows without duplicating the identity, transport, attachment, and scheduling responsibilities of Contact and Engagement.

### Included capabilities

- Authenticated inbound email registration
- Case-number, in-reply-to, and provider-thread matching
- Review queue for messages without an authoritative case match
- Customer-safe outbound draft preparation
- Contact and Engagement transport handoffs
- Delivery, deferral, bounce, complaint, and failure evidence
- Least-privilege channel authorizations with hash-only secrets
- Microsoft Teams live-support handoffs
- Agent Workspace channel summaries
- Private REST API, WP-CLI, and FastAPI parity

### Governance

No public inbound webhook is created. Email cannot create a case, send a reply, or close a case automatically. Bounce and complaint events create private review evidence. Zoom and Google Meet are not supported; Microsoft Teams remains the only live-session provider.
