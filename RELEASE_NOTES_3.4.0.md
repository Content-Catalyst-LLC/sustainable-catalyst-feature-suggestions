# Sustainable Catalyst Feature Suggestions v3.4.0

## Documentation and Feature Intelligence

Version 3.4.0 turns support activity into privacy-bounded documentation and product-planning evidence.

### Added

- Helpful/not-helpful feedback on published Support Articles
- Optional feedback reasons and redacted improvement comments
- Article-level helpfulness summaries and administration panels
- Private Documentation Gap records generated from repeated no-match and low-confidence searches
- Documentation-gap scoring using search pressure, negative feedback, and privacy-safe case relationships
- Gap workflow states, article links, suggestion links, recommendations, REST records, and CSV export
- Protected case-to-article and case-to-suggestion relationship registry
- Opaque Contact and Engagement relationship contract with no requester identity or case narrative
- Support Demand opportunity-scoring dimension
- Guided Resolution search attribution on opened support articles
- Relationship callback metadata in unresolved-support handoffs
- Documentation Intelligence administration dashboard
- FastAPI documentation-gap and support-demand scoring endpoints

### Boundaries

- Feature Suggestions does not store private support-case content, contact details, attachments, or correspondence.
- Contact and Engagement remains the system of record for private support cases.
- Article feedback and support-demand scores are advisory evidence.
- Documentation gaps and opportunity scores require human review.
