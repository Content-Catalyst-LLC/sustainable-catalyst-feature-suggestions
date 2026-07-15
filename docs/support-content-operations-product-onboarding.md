# Support Content Operations and Product Onboarding

Feature Suggestions v4.1.0 adds an operational publishing layer for the Product Support and Feedback Platform.

## Administration

Open **Feature Suggestions → Content Operations** and select a Product taxonomy term.

The workflow is intentionally product-by-product:

1. save an onboarding profile;
2. map the current version and reusable components;
3. create the missing starter documentation set;
4. import existing README, documentation, CHANGELOG, release-notes, or JSON sources;
5. review the generated drafts;
6. run validation;
7. publish Support Articles, Known Issues, and Release Records;
8. confirm the readiness score and remaining blockers.

## Recommended starter set

Every product should normally have:

- Getting Started;
- Installation and Configuration;
- Common Workflows;
- Troubleshooting;
- Technical Reference;
- a current Release Record.

The generator is idempotent. A starter key is stored on each generated record, and a repeated run skips the existing record instead of overwriting editorial work.

## Imports

Supported files are JSON, Markdown, and text up to 2 MB.

- `README.md` becomes a Getting Started article.
- Documentation Markdown/text becomes a Support Article.
- `CHANGELOG.md` and release notes are split at semantic-version level-two headings when possible.
- JSON can provide article, `known_issue`, and release records with taxonomy context.

Imports default to draft. Publishing is an explicit editor action.

## Lifecycle and provenance

Managed records share:

- `_scfs_content_lifecycle`;
- `_scfs_source_kind`;
- `_scfs_source_reference`;
- `_scfs_source_version`;
- `_scfs_source_fingerprint`;
- `_scfs_verified_at`;
- `_scfs_review_due_at`;
- `_scfs_superseded_by`;
- `_scfs_import_batch_id`.

## Readiness model

The score is bounded from 0 to 100 and uses product profile, current version, components, required article types, release records, known-issue review, and freshness. A score is evidence for editorial review; it is not an automated publication or roadmap decision.

## Empty Support Center sections

When **Hide empty support sections** is enabled, Knowledge Base, Known Issues, Releases, Public Ideas, and Surveys are removed from the public Support Center navigation until at least one matching public record exists. Direct routes remain valid for administrators and future links.

## REST API

Protected operations routes:

```text
GET  /wp-json/scfs/v1/content-operations/readiness?product=<term-id>
POST /wp-json/scfs/v1/content-operations/validate
POST /wp-json/scfs/v1/content-operations/import
GET  /wp-json/scfs/v1/content-operations/export?product=<term-id>
```

Public capability schema:

```text
GET /wp-json/scfs/v1/content-operations/schema
```

## WP-CLI

```bash
wp scfs support-onboard --product=workbench --version=v5.0.0
wp scfs support-validate --product=workbench
```

## Privacy boundary

This system manages public support publishing. It does not import requester identity, private correspondence, secure documents, or private case narratives from Contact and Engagement.
