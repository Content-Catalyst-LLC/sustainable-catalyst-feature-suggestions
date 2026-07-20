# Help Desk Customer Portal and Conversations v6.3.0

v6.3.0 adds the secure requester-facing layer above the v6.1.0 private case foundation and v6.2.0 Agent Workspace. The portal does not expose a public case directory, does not place requester identity into public support records, and does not use WordPress posts as private tickets.

## Access architecture

An authorized agent issues an expiring access link for one private case. The raw access token is shown once and stored only as a SHA-256 hash. Opening the link validates the token, creates a separate short-lived portal session, stores only the session hash, sets an HttpOnly SameSite cookie, and redirects to the clean portal route so the original token is removed from the browser address.

The default portal route is `/support/cases/` and the page should contain:

```text
[scfs_help_desk_customer_portal]
```

The compatibility alias is `[scfs_customer_support_portal]`.

## Customer capabilities

A valid portal session can provide scoped access to:

- the case number, subject, product, version, component, status, and dates;
- participant-visible requester and support messages;
- secure requester replies;
- requester-confirmed resolution;
- reopening a recently resolved case within the configured window;
- private satisfaction feedback;
- public-record relationships that were explicitly marked as public context;
- secure logout and session revocation.

Internal notes, requester references, organization references, assignment history, private audit events, attachment bytes, raw access tokens, and raw session secrets are never returned by the portal payload.

## Additive private tables

v6.3.0 adds five migration-safe tables:

```text
wp_scfs_help_desk_portal_tokens
wp_scfs_help_desk_portal_sessions
wp_scfs_help_desk_portal_events
wp_scfs_help_desk_portal_satisfaction
wp_scfs_help_desk_portal_notifications
```

Existing v6.1.0 case tables and v6.2.0 workspace tables remain unchanged.

## Conversations

Requester replies are written to the existing `wp_scfs_case_messages` table with:

```text
message_type = requester_message
visibility = participants
```

Support replies continue to use `support_reply`. Internal notes continue to use `internal_note` with `visibility = internal`. Portal conversation queries explicitly require `visibility = participants`, so internal notes are excluded at the database query boundary rather than hidden only with CSS.

When a requester replies to a case that is waiting for the requester, the case returns to `open` and an append-only portal and case audit event is recorded.

## Requester status actions

The portal supports two narrow requester actions:

- **Mark resolved** — moves an active case to `resolved`, or confirms a resolved case as `closed`.
- **Reopen case** — moves a `resolved` or `closed` case to `open` when it remains inside the configured reopen window.

No background rule automatically resolves, closes, or reopens a case. Each change is an explicit requester action and creates an audit event.

## Satisfaction feedback

Ratings from 1–5, a resolution indicator, a bounded reason, and optional private comments are stored in the portal satisfaction table. Feedback is not published automatically and requester identity is excluded from public analytics.

## Notification boundary

Portal invitations and requester-reply notifications are queued as delivery intents. The delivery authority remains `contact-engagement`; v6.3.0 does not send directly from the public portal or copy Contact and Engagement identity records into public support data.

## WordPress REST API

The existing `scfs/v1` namespace is retained:

```text
GET  /wp-json/scfs/v1/help-desk/portal/schema
GET  /wp-json/scfs/v1/help-desk/portal/case
GET  /wp-json/scfs/v1/help-desk/portal/conversation
POST /wp-json/scfs/v1/help-desk/portal/reply
POST /wp-json/scfs/v1/help-desk/portal/transition
POST /wp-json/scfs/v1/help-desk/portal/satisfaction
POST /wp-json/scfs/v1/help-desk/portal/logout
POST /wp-json/scfs/v1/help-desk/portal/admin/issue-link
GET  /wp-json/scfs/v1/help-desk/portal/admin/health
```

Customer routes require a valid portal session. Administrative routes require help-desk portal capabilities.

## FastAPI parity

The deterministic backend validates access-link governance, token-to-session exchange, secure-cookie policy, conversation visibility, requester transitions, satisfaction privacy, and SHA-256 report integrity.

## Governance boundaries

v6.3.0 does not:

- create cases automatically;
- disclose whether a case exists without a valid session;
- expose a public case-list API;
- expose internal notes or private attachments;
- store raw access or session secrets;
- publish satisfaction feedback automatically;
- replace Contact and Engagement identity, consent, attachment, or notification authority;
- replace agent review or human case ownership.
