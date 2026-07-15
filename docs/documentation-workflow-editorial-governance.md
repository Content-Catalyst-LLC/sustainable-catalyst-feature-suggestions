# Documentation Workflow and Editorial Governance

Feature Suggestions v4.2.0 adds an internal editorial operating system for Support Articles, Known Issues, and Release Records. WordPress remains the source of truth. The workflow does not expose private editorial notes or bypass human approval.

## Workflow states

1. **Draft** — content is being written.
2. **Submitted for Review** — the author has requested editorial review.
3. **In Review** — an assigned reviewer is evaluating the record.
4. **Changes Requested** — revisions are required before approval.
5. **Approved** — the record passed the configured governance gate.
6. **Scheduled** — approved content is queued for a future publication time.
7. **Published** — the record is publicly available.
8. **Expired** — the configured expiration date has passed and the public record is withdrawn.
9. **Archived** — the record is retained internally but no longer active.

Transitions are explicit and auditable. Direct publication can be changed to Pending Review when approval is required.

## Assignments and separation of duties

Each governed record can have an author, reviewer, and approver. The default policy requires an approver who is different from the author. Administrators can delegate editorial and approval capabilities through WordPress filters without changing the public support boundary.

## Documentation standards

The standards engine evaluates title quality, substantive content, summaries, product context, expected sections, provenance, and change summaries. Default section standards are included for:

- Getting Started articles;
- How-to guides;
- Troubleshooting articles;
- Technical references;
- Known Issues; and
- Release Records.

A standards score is advisory until the administrator enables publication blocking. Human review remains mandatory even when a record receives a high score.

## Version-specific approval

When Product Version terms are assigned, an editor can identify the versions covered by the approval. With version approval required, at least one assigned version must be explicitly approved before the record can move to Approved, Scheduled, or Published.

## Review reminders and expiration

The hourly governance task:

- publishes approved records when their scheduled time arrives;
- moves expired public records out of public view;
- creates review reminders before the review due date; and
- optionally emails assigned authors and reviewers.

The task uses a lock to prevent overlapping runs and reports its last and next run in the governance dashboard.

## Editorial comments and audit history

Editorial comments are stored as private `sc_editorial_note` records. They are not public, do not appear in the Support Center, and are excluded from public REST records. Workflow transitions, assignment changes, comments, publication blocks, reminders, and settings changes are recorded in a bounded internal audit log that can be exported as CSV.

## Administration

Open **Feature Suggestions → Editorial Governance** to review:

- workflow counts;
- records awaiting review;
- overdue reviews;
- records expiring soon;
- documentation standards blockers;
- assignments and due dates;
- governance settings;
- documentation standards; and
- recent audit events.

The record editor includes the same assignments, dates, standards status, transition controls, version approvals, and private comments.

## REST API

Protected WordPress endpoints are available under `/wp-json/scfs/v1/editorial-governance/*` for the queue, individual records, standards assessments, transitions, audit history, and scheduled task execution. The schema endpoint is public and contains no private content.

The FastAPI service provides deterministic advisory endpoints:

- `GET /v1/editorial-governance/capabilities`
- `POST /v1/editorial-governance/transitions/evaluate`
- `POST /v1/editorial-governance/standards/score`
- `POST /v1/editorial-governance/queue/summarize`

These endpoints evaluate evidence; they do not publish records or approve content.
