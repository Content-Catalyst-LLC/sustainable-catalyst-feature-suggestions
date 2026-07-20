# Help Desk Knowledge-Assisted Case Resolution — v6.6.0

v6.6.0 connects private cases to the public Support Article, Known Issue, release, and Guided Resolution layers without placing private correspondence into the knowledge index.

## Resolution lifecycle

1. An agent generates a recommendation run for a private case.
2. The system creates a privacy-minimized fingerprint from product, version, component, subject, and normalized diagnostic terms.
3. Public support records and privacy-safe case signatures are ranked deterministically.
4. Recommendations remain internal and pending.
5. An authorized agent approves, rejects, or applies a recommendation.
6. Only an approved customer-safe recommendation may be sent to the requester.
7. Recurring evidence may create a draft Documentation Gap or another governed promotion request.

## Privacy boundary

Recommendation records do not persist requester identity, private message bodies, private attachments, or raw case descriptions. Similar-case matches expose case numbers and privacy-safe resolution summaries only to authorized agents. Customer Portal payloads contain only approved public guidance.

## Human authority

The platform does not automatically send replies, merge duplicate cases, resolve cases, create Known Issues, publish Support Articles, or publish feature suggestions. Promotion creates a reviewable draft request, never a public record without editorial approval.
