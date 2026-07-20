# Product Support and Feedback Platform v6.2.0

## Agent Workspace, Queues, and Assignment

v6.2.0 adds the private help-desk operating console on top of the v6.1.0 case foundation.

### Added

- Agent Workspace administration screen
- Built-in and dynamic team queues
- Private case search and filters
- Queue counts and workload summaries
- Explicit agent and team assignment
- Assignment history and audit events
- Saved personal and governed shared views
- Bulk claim, assign, unassign, status, and priority actions
- Complete case workspace with internal notes and requester-visible replies
- Agent role and scoped capabilities
- Authenticated WordPress REST routes
- FastAPI queue, assignment, workload, and saved-view contracts
- WP-CLI queue, workload, assignment, and saved-view commands

### Privacy

No public case API or shortcode is introduced. Requester identity and attachment authority remain with the Contact and Engagement Platform. Automatic assignment remains disabled.

### Compatibility

All public support records, URLs, shortcodes, REST namespaces, taxonomies, settings, and v6.1.0 private case records remain compatible. The database change is additive.
