# Sustainable Catalyst Feature Suggestions v5.1.0

## Integrated Knowledge Base and Documentation Library

Version 5.1.0 turns the Product Support Center into a modern documentation experience while preserving the existing support, feedback, governance, reliability, and cross-product operations platform.

## Included

### Modern expandable Knowledge Base

- One clean **Browse the Knowledge Base** disclosure modeled on the Sustainable Catalyst Library interaction pattern.
- Nested product and documentation-section folders using native accessible `details` and `summary` controls.
- Product descriptions, article counts, reading times, empty states, and expand/collapse-all controls.
- Search by task, feature, error message, or topic with optional product filtering.
- Persistent Knowledge Base navigation even when the article count is temporarily zero.

### First-party documentation corpus

- 96 detailed HTML Support Articles covering 16 Sustainable Catalyst products.
- Six standardized guides per product: Start Here, Setup and Configuration, Tools and Features, Worked Examples, Troubleshooting, and Technical Reference.
- 283 feature demonstrations with synthetic inputs, procedures, expected results, verification guidance, limitations, and troubleshooting.
- 32 bundled CSV and JSON sample files delivered directly from the plugin without Media Library MIME dependencies.

### Article experience

- Breadcrumb navigation from Knowledge Base to product, documentation section, and article.
- Previous and next guide navigation within each product.
- Related product guides.
- Print-friendly article action.
- Direct sample-data downloads.
- Product return path.

### Visitor usefulness ratings

- Anonymous **Was this article useful?** Yes / Not yet rating.
- Optional reason and comment fields.
- Existing duplicate protection, privacy-minimized visitor keys, and rate controls retained.
- Helpfulness percentage displayed after a minimum response threshold.
- Existing documentation intelligence and editorial reporting remain the source of truth.

### Governance and reliability

- Idempotent first-party content migration and repair.
- Existing manual edits are preserved.
- Product, version, component, issue type, article type, and hierarchical collection relationships are repaired on repeat runs.
- A narrowly scoped editorial publication bypass applies only during the validated integrated documentation migration.
- All imported records receive published lifecycle and editorial metadata.
- Existing Support Articles not owned by the integrated corpus are never overwritten.

## Architectural decision

No legacy KnowledgeBuilder runtime, authentication, database, or browser code is included. The release recreates the strongest traditional Knowledge Base behaviors using current WordPress records, taxonomies, REST interfaces, editorial governance, and Sustainable Catalyst design patterns.

## Upgrade

1. Back up WordPress and the Feature Suggestions repository.
2. Upload the v5.1.0 WordPress ZIP and replace the current plugin.
3. Activate the plugin if WordPress does not keep it active.
4. Open **Feature Suggestions → Knowledge Base Library**.
5. Confirm the bundled corpus validates as 96 articles, 16 products, and 32 samples.
6. Run **Import or Repair Documentation** if the automatic migration has not already completed.
7. Deactivate the separate Sustainable Catalyst Support Knowledge Base content-pack plugin after confirming the integrated articles are present.
8. Clear page and object caches. Save **Settings → Permalinks** once only if an article route returns 404.

## Validation target

- PHP syntax for all WordPress and test files.
- JavaScript syntax for all frontend and administrator scripts.
- JSON parsing for manifests, examples, and the 96-article corpus.
- Existing WordPress contract suites.
- New v5.1.0 structure, corpus, governance, and bootstrap checks.
- Existing FastAPI test suite.
- Single-root WordPress distribution ZIP integrity.
