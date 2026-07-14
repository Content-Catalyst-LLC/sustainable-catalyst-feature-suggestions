# Guided Resolution to Contact and Engagement Handoff

Schema: `sc-contact-engagement-resolution-handoff/1.0`

The handoff carries unresolved public search context into Contact and Engagement after explicit consent. It contains the original query, selected product/version/component, search confidence, resolution state, public records viewed, and the user's reason the guidance was insufficient.

It intentionally excludes name, email, attachments, IP address, and private logs. Contact and Engagement collects and governs those details at the destination. Tokens expire after 30 minutes. Case creation remains disabled until the destination system and an authorized workflow accept the handoff.
