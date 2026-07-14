# Sustainable Catalyst Product Support and Feedback Platform v3.3.0

Version 3.3.0 extends the Support Knowledge Base foundation into a guided product-support workflow with error matching, known-issue prioritization, search analytics, and private support handoffs.

## Support Knowledge Base

Support Articles and Known Issues are first-class public records that share product, version, component, issue, and release context with Feature Suggestions. Documentation collections and article types provide the public information architecture.

The Knowledge Base exposes a responsive archive and `[scfs_support_knowledge_base]` shortcode, plus published-content REST endpoints under `/scfs/v1/knowledge-base/*`.

## Platform boundary

The Product Support and Feedback Platform owns public documentation, known issues, feature suggestions, voting, surveys, release relationships, and product intelligence. Contact and Engagement continues to own private support cases, sender communication, documents, and lifecycle management.

## Platform command center

The WordPress command center reports record counts, module readiness, roadmap distribution, and release-readiness checks across feature intake, AI triage, forms, surveys, Research Librarian feedback, public ideas, and opportunity workflow.

## Governance and retention

Administrators can configure contact anonymization, response retention, completed-suggestion retention, audit limits, and public-decision rationale requirements. Retention is disabled by default and supports a dry run before execution.

## Data portability

An administrator-only JSON snapshot provides versioned counts, module status, governance settings, event schema information, and human-review boundaries. Existing CSV and analysis exports remain available.

## Safety and decision boundaries

AI and voting remain advisory. Publishing, roadmap state changes, release decisions, retention execution, and official responses require authorized human action.
