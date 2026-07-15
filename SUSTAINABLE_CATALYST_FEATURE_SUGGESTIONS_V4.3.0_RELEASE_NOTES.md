# Feature Suggestions v4.3.0 — Repository and Release Synchronization

Feature Suggestions v4.3.0 connects public Sustainable Catalyst repositories to the support-content and editorial-governance systems. It can inspect README files, CHANGELOG files, documentation paths, and GitHub releases while preserving WordPress as the approval and publication authority.

## Product repository mapping

Each Product taxonomy term can be mapped to a public GitHub repository with:

- default branch;
- README and CHANGELOG paths;
- documentation directories;
- additional release-note files; and
- per-source synchronization controls.

The existing product-onboarding repository URL is used as the initial mapping when available.

## Approval-gated synchronization

Repository inspection classifies sources as new, unchanged, locally edited, remotely updated, published updates, or conflicts.

Synchronization follows strict safeguards:

- new sources create drafts;
- untouched drafts can receive remote updates;
- published records are never overwritten;
- local and remote conflicts create separate review copies;
- synchronized content enters the editorial workflow; and
- automatic approval and publication remain disabled.

## Release synchronization

Published GitHub releases and semantic-version CHANGELOG sections can create Release Record drafts with:

- release title and tag;
- source URL;
- release notes;
- publication date;
- preview/current lifecycle suggestion;
- Product, Product Version, and Release relationships; and
- commit or target reference.

Release ingestion never declares a release ready or publishes it automatically.

## Documentation provenance and drift

Synchronized records retain repository URL, external identifier, source path, source URL, commit reference, content hashes, last-seen date, and synchronization state.

The drift report distinguishes:

- aligned content;
- local WordPress edits;
- remote repository updates; and
- local/remote conflicts.

This prevents repository synchronization from silently replacing reviewed editorial work.

## Link health

Product support content can be checked for broken, redirected, or timed-out public links. Results are stored as internal operational metadata and summarized by product.

## Scheduled inspection and webhooks

A daily scheduled inspection is enabled by default. Optional scheduled ingestion can only create review drafts.

A signed GitHub webhook endpoint can queue inspection after push or release events. Webhooks are disabled by default and require `SCFS_GITHUB_WEBHOOK_SECRET`. Webhook events never create public content directly.

## Security and privacy

- Public repositories work without credentials.
- Optional GitHub credentials use `SCFS_GITHUB_TOKEN` from a PHP constant or environment variable.
- Tokens and webhook secrets are not stored in plugin options, REST responses, or synchronization logs.
- Private repository synchronization is disabled in v4.3.0.
- Contact and Engagement remains the system of record for private support cases, communication, and documents.

## Administration

Open **Feature Suggestions → Repository Sync** to configure mappings, inspect sources, create review drafts, review drift, check links, and export synchronization logs.

## FastAPI advisory endpoints

- `GET /v1/repository-sync/capabilities`
- `POST /v1/repository-sync/candidates/evaluate`
- `POST /v1/repository-sync/drift/evaluate`
- `POST /v1/repository-sync/releases/plan`
- `POST /v1/repository-sync/link-health/summarize`

The backend evaluates evidence only. It cannot fetch private repositories, modify WordPress, approve records, or publish content.
