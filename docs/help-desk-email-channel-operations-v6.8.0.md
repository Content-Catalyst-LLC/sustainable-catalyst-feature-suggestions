# Help Desk Email and Channel Operations v6.8.0

## Architecture

The module is a private orchestration layer between help-desk cases and Contact and Engagement.

1. Contact and Engagement receives and authenticates an email.
2. The help desk receives a scoped message reference, participant reference, sanitized content, content SHA-256, and attachment references.
3. The matcher checks an explicit `SC-YYYY-000001` case number, then in-reply-to references, then provider thread references.
4. A unique match may append a participant-visible case message. An unmatched or ambiguous email enters review and never creates a case automatically.
5. Outbound content is prepared as a customer-safe draft with the case number in the subject.
6. Contact and Engagement performs delivery and returns append-only delivery events.
7. Live troubleshooting requests are handed to Contact and Engagement for Microsoft Teams scheduling after consent and agent approval.

## Private tables

- `scfs_help_desk_email_channels`
- `scfs_help_desk_email_threads`
- `scfs_help_desk_email_messages`
- `scfs_help_desk_email_delivery_events`
- `scfs_help_desk_channel_authorizations`
- `scfs_help_desk_channel_handoffs`

## Privacy and security boundaries

Raw channel secrets are never stored. Attachment bytes remain outside the help desk. Requester identity stays represented by private references. Public support APIs expose no case or channel records.
