# Sustainable Catalyst Feature Suggestions v3.3.0

## Search and Guided Resolution

Feature Suggestions v3.3.0 turns the v3.2.0 Support Knowledge Base into an actionable product-support workflow. Visitors can describe a task or symptom, paste a short non-sensitive error fragment, select product/version/component context, and receive ranked public guidance before entering a private support process.

## Public resolution workflow

- Added `[scfs_guided_resolution]` and retained `[scfs_support_knowledge_base]`.
- Upgraded the default Support Knowledge Base archive to Guided Resolution.
- Added product, product-version, and component filters.
- Added deterministic query expansion through administrator-managed synonyms.
- Added exact phrase, token overlap, error signature, and taxonomy-context scoring.
- Grouped results into current known issues, support articles, releases, and explicitly public feature suggestions.
- Added visible match reasons and confidence states.

## Known-issue prioritization

- Current investigating, confirmed, workaround-available, and monitoring records receive priority over resolved or closed records.
- Critical and high-severity records receive additional ranking weight.
- Product, version, and component matches are preserved as inspectable ranking signals.

## Editorial search controls

- Added per-article and per-known-issue search aliases.
- Added stable error-signature fields.
- Added editorial promotion and priority controls.
- Added global synonym mappings and promoted-result identifiers.
- Added configurable confidence thresholds and result limits.

## Analytics and documentation intelligence foundation

- Added a dedicated guided-resolution analytics table.
- Stores redacted query text, query hashes, error hashes, selected filters, result counts, confidence, resolution state, viewed public records, source, and timestamps.
- Does not store IP addresses.
- Added top unresolved-search reporting and CSV export.
- Added retention controls for search analytics.
- Creates the failed-search and documentation-gap signals required by v3.4.0.

## Contact and Engagement handoff

- Added `sc-contact-engagement-resolution-handoff/1.0`.
- Unresolved users must explicitly consent before transferring context.
- Handoff tokens expire after 30 minutes.
- Payloads include the original query, product context, search confidence, resolution state, viewed records, and unresolved reason.
- Name, email, attachments, IP addresses, and private logs are not collected by Feature Suggestions.
- Contact and Engagement remains responsible for identity, communication, private documents, case lifecycle, and human review.
- Automatic case creation remains disabled.

## REST and backend

- Added guided-resolution schema, search, handoff-schema, token retrieval, and protected analytics endpoints.
- Added deterministic FastAPI candidate ranking and capability endpoints.
- Pinned `httpx==0.28.1` to prevent the missing TestClient dependency encountered during v3.2.0 installation.
- Installer selects Python 3.12 or 3.13 and rejects Python 3.14.

## Validation

- All WordPress PHP files pass syntax checks.
- 21 release-structure checks pass.
- 9 plugin bootstrap checks pass.
- 10 registration-contract checks pass.
- 7 public-render checks pass.
- 9 Python/FastAPI tests pass.
- JSON examples parse successfully.
- WordPress plugin and repository archives pass ZIP integrity checks.
