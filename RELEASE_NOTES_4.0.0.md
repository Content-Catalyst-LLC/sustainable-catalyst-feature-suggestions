# Sustainable Catalyst Feature Suggestions v4.0.0

## Product Support and Feedback Platform

Version 4.0.0 consolidates the Feature Suggestions roadmap into a coherent public product-support platform.

### Unified Support Center

The `[scfs_product_support_center]` shortcode provides one product-aware interface for:

- Guided Resolution;
- the Support Knowledge Base;
- Known Issues;
- Release Intelligence;
- moderated public ideas and advisory voting;
- structured feature suggestions;
- open forms and surveys;
- consent-gated private support continuation.

The legacy `[scfs_support_center]` alias is supported. The Support Knowledge Base archive now opens the unified Support Center.

### Release Intelligence

The new public `sc_release_record` post type adds planned, preview, current, maintenance, superseded, and retired release states; dates; summaries; support notes; highlights; known limitations; documentation and changelog links; product context; and relationships to published documentation, Known Issues, and moderated public ideas.

Only public idea metadata is exposed. Private suggestion text and contact data remain protected.

### Shared product routing

A Support Center product selector carries product context into Guided Resolution, Knowledge Base browsing, Known Issues, releases, public ideas, surveys, and private handoff routing.

### Contact and Engagement boundary

Feature Suggestions owns public documentation, support search, Known Issues, releases, suggestions, voting, surveys, and product intelligence. Contact and Engagement owns requester identity, private cases, correspondence, documents, and lifecycle management.

Automatic private case creation remains disabled. Handoff context requires consent and uses short-lived context rather than copying private case content into Feature Suggestions.

### REST and backend intelligence

New WordPress endpoints expose the platform schema, overview, release records, product summaries, and handoff schema. The protected snapshot endpoint provides administrator-only operational state.

The FastAPI service adds deterministic public-support state summarization and release-readiness scoring. AI and scoring remain advisory and require human review.

### Compatibility

The release is backward-compatible with the v3.1.0 shared taxonomy layer, v3.2.0 Knowledge Base records, v3.3.0 Guided Resolution, and v3.4.0 Documentation and Feature Intelligence.
