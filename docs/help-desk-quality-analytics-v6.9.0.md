# Help Desk Quality Assurance, Analytics, and Support Intelligence v6.9.0

## Purpose

The quality layer provides decision support for help-desk operations without turning private customer records into a surveillance or personnel-scoring system.

## Operating model

1. Private case events remain authoritative in the Help Desk Case Foundation.
2. Service-level, workflow, knowledge, evidence, and channel modules retain their own records.
3. The analytics layer reads approved operational fields and derives privacy-safe aggregates.
4. Cohorts below the configured minimum are suppressed.
5. Quality reviews create append-only findings and proposed improvement actions.
6. Signals require human review and cannot directly change product, case, incident, or personnel state.
7. Snapshots and exports include SHA-256 integrity evidence.

## Metrics

- Active backlog and oldest active case age
- Reopen rate
- Escalation rate
- SLA compliance
- Documentation-assisted resolution rate
- Quality-review coverage and average score
- Product, version, component, queue, and team pressure when cohorts are large enough

## Data minimization

Analytics exclude requester names, email addresses, raw correspondence, internal note bodies, attachment content, access secrets, and secure download URLs.

## Tables

- `scfs_help_desk_quality_reviews`
- `scfs_help_desk_quality_findings`
- `scfs_help_desk_analytics_snapshots`
- `scfs_help_desk_metric_series`
- `scfs_help_desk_support_signals`
- `scfs_help_desk_quality_actions`
