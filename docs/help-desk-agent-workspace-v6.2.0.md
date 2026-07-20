# Help Desk Agent Workspace v6.2.0

v6.2.0 adds the private operating console above the v6.1.0 Help Desk Case Foundation. It does not expose private cases publicly and does not convert public Support Articles, Known Issues, releases, or feature suggestions into tickets.

## Agent workspace

The WordPress administration menu now includes **Support & Feedback → Agent Workspace**. The workspace provides built-in queues, product and priority filters, private search, saved views, workload summaries, assignment controls, bulk operations, and a case workspace with internal notes and requester-visible replies.

## Built-in queues

- My open cases
- Unassigned
- New
- Waiting for support
- Waiting for requester
- Escalated
- P1 and P2
- Recently updated
- Recently resolved
- All active cases
- Dynamic team queues

## Assignment governance

Assignments are always explicit human actions. Every assignment closes the previous active assignment record, creates a new assignment-history record, updates the case owner, and appends an audit event. v6.2.0 does not implement automatic routing.

## Saved views

Saved views retain filters only. They may not store requester identities, private message bodies, correspondence, attachment contents, or credentials. Private views belong to one user; shared views require elevated permission.

## Private data boundaries

Requester identity and uploaded-file authority remain with the Contact and Engagement Platform. The Agent Workspace displays private case references only to authenticated users with help-desk capabilities. No public shortcode or public case API is added.

## Additive tables

- `wp_scfs_help_desk_saved_views`
- `wp_scfs_help_desk_teams`
- `wp_scfs_help_desk_team_members`

The eight v6.1.0 private case tables remain unchanged.
