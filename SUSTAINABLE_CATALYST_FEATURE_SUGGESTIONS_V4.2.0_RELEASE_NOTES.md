# Feature Suggestions v4.2.0 — Documentation Workflow and Editorial Governance

Feature Suggestions v4.2.0 turns support-content publishing into a controlled editorial workflow. It adds accountable assignments, explicit review and approval states, documentation standards, version-specific approval, scheduled publication, expiration and review reminders, private editorial comments, and internal audit history while retaining the v4.1.1 content-operations safeguards.

## Editorial workflow

Support Articles, Known Issues, and Release Records now move through:

- Draft
- Submitted for Review
- In Review
- Changes Requested
- Approved
- Scheduled
- Published
- Expired
- Archived

Transitions are explicit, validated, and recorded. Direct publication can be returned to Pending Review when approval is required.

## Assignments and approval

Each managed record supports:

- author assignment;
- reviewer assignment;
- approver assignment;
- optional separation of author and approver;
- version-specific approval;
- change summaries;
- approval notes and requested-change rationale; and
- review, publication, expiration, and next-review dates.

## Documentation standards

A deterministic standards engine evaluates:

- title quality;
- substantive content;
- summaries or symptoms;
- Product context;
- expected article, issue, or release sections;
- provenance and verification evidence; and
- editorial change summaries.

Default standards are included for Getting Started, How-to, Troubleshooting, Technical Reference, Known Issue, and Release content. Administrators can edit the expected sections and configure the minimum score used by the publication gate.

## Editorial comments and audit history

Private `sc_editorial_note` records preserve editorial discussion without exposing it in the public Support Center or public REST records. Assignment changes, transitions, comments, publication blocks, reminders, and governance-setting changes are recorded in a bounded internal audit log with CSV export.

## Scheduling, expiration, and reminders

The hourly governance task:

- publishes approved records at their scheduled time;
- withdraws expired public content;
- creates review-due reminders;
- optionally emails assigned editors; and
- reports last-run and next-run health.

A lock prevents overlapping scheduled runs.

## Administration

Open **Feature Suggestions → Editorial Governance** for:

- workflow and standards totals;
- editorial queues;
- overdue reviews;
- records expiring soon;
- assigned authors, reviewers, and approvers;
- governance controls;
- documentation standards;
- manual scheduled-task execution; and
- audit history and export.

The same controls appear within Support Article, Known Issue, and Release Record editors.

## WordPress REST API

New protected endpoints under `/wp-json/scfs/v1/editorial-governance/*` provide:

- governance summary;
- review queues;
- individual editorial records;
- standards assessments;
- controlled transitions;
- internal audit history; and
- manual scheduled-task execution.

The public schema endpoint contains capabilities only and does not expose editorial comments or private workflow content.

## FastAPI advisory endpoints

- `GET /v1/editorial-governance/capabilities`
- `POST /v1/editorial-governance/transitions/evaluate`
- `POST /v1/editorial-governance/standards/score`
- `POST /v1/editorial-governance/queue/summarize`

These endpoints evaluate supplied evidence. They cannot approve, schedule, or publish WordPress records.

## Governance boundaries

- Human review remains mandatory.
- Automatic approval is disabled.
- Editorial comments remain private.
- Contact and Engagement remains the private support-case, communication, and document system of record.
- Import rollback, malformed-source validation, checksum integrity, and administrator capability boundaries from v4.1.1 remain intact.
