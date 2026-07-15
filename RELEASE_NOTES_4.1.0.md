# Sustainable Catalyst Feature Suggestions v4.1.0

## Support Content Operations and Product Onboarding

Feature Suggestions v4.1.0 turns the Product Support and Feedback Platform from a stable public shell into an operational support-publishing environment. Administrators can onboard each Sustainable Catalyst product, create a complete starter documentation set, import existing repository documentation and release history, validate freshness and relationships, and measure whether a product is ready for public support.

### Product onboarding

A new **Feature Suggestions → Content Operations** screen stores a product profile with:

- onboarding state and responsible owner;
- repository, documentation, and support URLs;
- current product version;
- reusable product components;
- known-issue review confirmation;
- source and editorial notes.

Missing version and component taxonomy terms are created and linked to the selected product without replacing existing terms.

### Support-readiness scoring

Each product receives a deterministic 0–100 readiness score based on:

- onboarding profile status;
- current version and component mapping;
- Getting Started, How-to, Troubleshooting, and Technical Reference coverage;
- release intelligence;
- known-issue review;
- content freshness.

The score exposes blockers and remains advisory. It never publishes content or marks a product ready without editorial review.

### Starter support content

The onboarding workflow can create missing drafts for:

1. Getting Started;
2. Installation and Configuration;
3. Common Workflows;
4. Troubleshooting;
5. Technical Reference;
6. Current Release.

Starter creation is idempotent and never overwrites an existing starter record.

### Documentation and release import

The importer accepts files up to 2 MB in JSON, Markdown, or text format. It can:

- turn a README into a Getting Started article;
- import documentation as Support Articles;
- split semantic-version CHANGELOG headings into Release Records;
- import structured article, Known Issue, and release records from JSON;
- assign product, version, component, issue, release, and collection context;
- skip exact duplicates by fingerprint;
- create records as drafts or pending review by default.

Imports never include private Contact and Engagement case content and never publish automatically.

### Lifecycle and validation

Support Articles, Known Issues, and Release Records now share operations metadata for lifecycle, source kind, source reference, source version, fingerprint, verification date, review date, supersession, and import batch.

Validation identifies:

- missing product context;
- duplicate content;
- stale or unverified records;
- invalid supersession relationships;
- WordPress status and editorial lifecycle mismatches.

### Public Support Center operations

When enabled, empty Knowledge Base, Known Issues, Releases, Public Ideas, and Surveys navigation is hidden until corresponding public records exist. Guided Resolution, suggestion submission, and private-support continuation remain available.

### REST and CLI

Protected WordPress REST routes provide readiness, validation, JSON import, and export. WP-CLI commands support product onboarding and validation. The FastAPI service adds deterministic support-readiness scoring and source-import planning.

### Governance boundaries

- Human review remains mandatory.
- Starter content does not overwrite existing records.
- Imports do not publish automatically.
- Contact and Engagement remains the private case, correspondence, identity, and document system of record.
- Feature Suggestions imports no private case content.
