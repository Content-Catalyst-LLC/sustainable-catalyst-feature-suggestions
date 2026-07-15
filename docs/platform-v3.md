# Sustainable Catalyst Product Support and Feedback Platform v4.0.2

Version 4.0.2 preserves the v4.0.0 unified platform and adds embedded rendering, configurable branding, theme inheritance, responsive safeguards, and cache-safe assets. The platform unifies Guided Resolution, documentation, Known Issues, Release Intelligence, feature suggestions, public voting, surveys, and privacy-bounded private support handoff in one product-aware public Support Center.

## Embedded integration

Use `[scfs_product_support_center mode="embedded" branding="sustainable-catalyst" default_view="resolve"]` inside a page that already provides its own hero, pathways, and explanatory content. Branding and layout defaults are managed under **Feature Suggestions → Support Platform**.

## Unified Support Center

Use `[scfs_product_support_center]` to provide one public entry point for support search, documentation, Known Issues, releases, ideas, voting, suggestions, surveys, and private-support continuation.

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


## v3.4.0 Documentation and Feature Intelligence

The platform now treats article feedback, failed searches, documentation gaps, and privacy-safe support relationships as reviewable evidence. The Support Demand scoring dimension informs feature prioritization without exposing private case content or automating roadmap decisions.
