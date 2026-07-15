# Repository and Release Synchronization

Feature Suggestions v4.3.0 connects the Product Support and Feedback Platform to public product repositories without turning GitHub into an automatic publishing authority.

## Purpose

The synchronization layer maps each Sustainable Catalyst Product term to a public GitHub repository and can inspect:

- repository metadata and the current branch commit;
- README files;
- CHANGELOG files;
- configured documentation directories;
- additional release-note files; and
- published GitHub releases.

Remote sources are converted into reviewable Support Article or Release Record drafts. The WordPress editorial workflow remains the source of truth for approval and publication.

## Administration

Open **Feature Suggestions → Repository Sync**.

For each product, configure:

- public GitHub repository URL;
- default branch;
- README and CHANGELOG paths;
- documentation directories;
- optional release-note files; and
- the source types that should be inspected.

The product onboarding profile repository URL is used as a starting value when available.

## Inspection and synchronization

**Inspect repository** fetches the current remote sources and classifies each item as:

- new;
- unchanged;
- local edit;
- remote update;
- published update; or
- conflict.

**Create review drafts** applies the inspection plan with these safeguards:

- new sources create WordPress drafts;
- remote changes can update untouched drafts;
- published records are never overwritten;
- conflicting remote and local changes create a separate review copy;
- every synchronized item requires human review; and
- no synchronized item is automatically approved or published.

## Provenance

Synchronized records retain:

- repository URL;
- external source identifier;
- source path;
- commit or release reference;
- source URL;
- remote and local content hashes;
- last-seen time; and
- synchronization state.

Product, version, and release taxonomies are assigned where the source provides sufficient context.

## Drift detection

The drift report compares the current WordPress content hash with the hash captured during the last synchronization. It distinguishes local edits, remote updates, and conflicts so editorial work is not silently overwritten.

## Link health

The link checker scans product support content for public HTTP and HTTPS links. It records usable responses, redirects, timeouts, and broken links. Link checks are advisory and should be reviewed before editing public content.

## Scheduled inspection

The daily scheduled task runs repository inspection by default. Administrators can opt into scheduled draft ingestion, but even scheduled ingestion can only create review drafts. Scheduled tasks never approve or publish content.

## GitHub credentials

Public repositories work without a token. An optional GitHub token can be supplied through the `SCFS_GITHUB_TOKEN` PHP constant or environment variable. Tokens are not stored in plugin options, shown in REST responses, or written to synchronization logs.

Private repository synchronization is disabled in v4.3.0.

## Webhook-ready architecture

A signed GitHub webhook endpoint is available at:

`/wp-json/scfs/v1/repository-sync/webhook/github`

Webhook processing is disabled by default. Enable it only after supplying `SCFS_GITHUB_WEBHOOK_SECRET` through a PHP constant or environment variable. Valid push and release events queue a repository inspection; they do not create or publish content directly.

## REST API

Public capability schema:

- `GET /wp-json/scfs/v1/repository-sync/schema`

Protected operations:

- `GET /wp-json/scfs/v1/repository-sync/mappings`
- `POST /wp-json/scfs/v1/repository-sync/preview/{product_id}`
- `POST /wp-json/scfs/v1/repository-sync/apply/{product_id}`
- `GET /wp-json/scfs/v1/repository-sync/drift`
- `POST /wp-json/scfs/v1/repository-sync/link-health/{product_id}`
- `GET /wp-json/scfs/v1/repository-sync/log`

FastAPI advisory endpoints:

- `GET /v1/repository-sync/capabilities`
- `POST /v1/repository-sync/candidates/evaluate`
- `POST /v1/repository-sync/drift/evaluate`
- `POST /v1/repository-sync/releases/plan`
- `POST /v1/repository-sync/link-health/summarize`

The FastAPI service evaluates supplied evidence. It cannot fetch private repositories, edit WordPress, approve content, or publish records.

## Governance boundaries

- WordPress remains the source of truth.
- GitHub is a source, not a publishing authority.
- Human review is mandatory.
- Automatic approval and publication are disabled.
- Published records are never overwritten.
- Private repository access is disabled.
- Tokens and webhook secrets stay outside plugin options and logs.
- Contact and Engagement remains the private support-case system of record.
