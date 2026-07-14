# Documentation and Feature Intelligence

Feature Suggestions v3.4.0 connects the public Support Knowledge Base to product-planning evidence without moving private support cases into Feature Suggestions.

## Evidence sources

- Helpful and not-helpful responses on published Support Articles
- Privacy-minimized no-match and low-confidence Guided Resolution searches
- Public records viewed during guided resolution
- Opaque case-to-article and case-to-suggestion relationships sent by Contact and Engagement
- Existing public votes, duplicate suggestions, reviewer evidence, and AI triage metadata

## Documentation feedback

Published Support Articles display a compact usefulness form. Responses may include an optional reason and short improvement comment. The plugin removes common email, credential, secret, and payment-number patterns before storage. It does not store IP addresses or contact details.

Public endpoints:

- `POST /wp-json/scfs/v1/knowledge-base/articles/{id}/feedback`
- `GET /wp-json/scfs/v1/knowledge-base/articles/{id}/feedback-summary`

## Documentation gaps

The private `sc_doc_gap` record type materializes repeated unresolved searches and negative article-feedback patterns. Each gap includes:

- Normalized query or article-quality key
- Product, version, and component context when available
- Search, no-match, low-confidence, negative-feedback, and case counts
- Deterministic 0–100 gap score
- Open, planned, documented, or dismissed status
- Optional linked Support Article and Feature Suggestion
- Human-authored recommendation

Refresh gaps from **Feature Suggestions → Documentation Intelligence** or through the protected REST endpoint.

## Case relationships

Contact and Engagement remains the owner of private support cases. It may send a relationship record containing only:

- Opaque case reference
- `case_article` or `case_suggestion`
- Public record ID
- Source system
- Product context
- Outcome and evidence weight

Do not send requester identity, email, documents, private case narrative, credentials, or sensitive logs.

## Opportunity scoring

The roadmap score now includes a distinct Support Demand dimension. It considers privacy-safe case-to-suggestion relationships, linked documentation gaps, unresolved-search evidence, and Guided Resolution views. The signal is advisory and cannot approve, schedule, or release a feature automatically.
