# Sustainable Catalyst Product Support and Feedback Platform v5.3.0

## Unified Search and Guided Resolution

Version 5.3.0 joins the publication-oriented Support Discovery layer with the existing Guided Resolution workflow. Visitors can now describe a task, symptom, or non-sensitive error fragment once and receive an ordered public resolution journey across current Known Issues, verified Support Articles, release context, and moderated public improvement records.

## Unified public workflow

The canonical `/support/` experience now:

1. accepts one support question with optional product, version, and component context;
2. searches current public support evidence through a deterministic ranking pipeline;
3. places active Known Issues before general guidance when they are relevant;
4. routes readers to the strongest verified Support Articles;
5. surfaces related release context and known limitations;
6. shows public improvement records only as secondary context; and
7. recommends the existing consent-gated private support handoff when public evidence is absent or insufficient.

The workflow reports its recommended starting point, confidence, result counts, and ranking reasons. It remains useful without JavaScript and reinitializes after dynamic Support Center navigation.

## WordPress implementation

New public shortcodes:

```text
[scfs_unified_support_search]
[scfs_unified_guided_resolution]
```

The second shortcode is a compatibility alias. Existing shortcodes, including `[scfs_guided_resolution]`, `[scfs_support_knowledge_base]`, and `[scfs_product_support_center]`, remain available.

New class and assets:

```text
SCFS_Unified_Support_Search
assets/unified-support-search.css
assets/unified-support-search.js
```

The Support Center overview and resolve views use the unified renderer, while the previous Guided Resolution renderer remains a runtime fallback.

## Public API

WordPress routes under the existing `scfs/v1` namespace:

```text
/wp-json/scfs/v1/unified-support/schema
/wp-json/scfs/v1/unified-support/search
/wp-json/scfs/v1/unified-support/journey
```

FastAPI parity:

```text
GET  /v1/unified-support/capabilities
POST /v1/unified-support/search
POST /v1/unified-support/journey
```

Versioned contracts:

```text
scfs-unified-support-search/1.0
scfs-support-resolution-journey/1.0
```

WordPress remains the source of truth for public articles, Known Issues, releases, suggestions, taxonomies, and relationships.

## Ranking and resolution logic

The deterministic scoring system considers:

- exact title and summary phrases;
- non-sensitive error-fragment matches;
- Support Discovery token and synonym expansion;
- product, version, and component alignment;
- current Known Issue status and severity;
- editorial promotion and priority;
- verified Support Article relevance; and
- release-specific context.

Low-confidence or empty results do not fabricate guidance. They instead recommend further public exploration or the existing private support pathway.

## Privacy and governance

The unified workflow:

- searches public support records only;
- does not create a personal search-history database;
- does not automatically create a private case;
- does not publish or rewrite content;
- does not declare incidents or diagnose defects autonomously;
- does not change roadmap or suggestion status; and
- retains human review as the final authority.

Users are instructed not to enter passwords, API keys, personal information, confidential correspondence, private documents, or complete private logs. Private support remains separate and consent-gated.

## Compatibility

No database migration is required. The release preserves:

- the repository, plugin directory, and text-domain slug;
- the `Sustainable_Catalyst_Feature_Suggestions` PHP class;
- all existing `scfs_*` functions, hooks, options, and metadata;
- REST namespace `scfs/v1`;
- `sc_feature_suggest`, `sc_support_article`, `sc_known_issue`, and `sc_release_record`;
- all existing Support Article URLs under `/support/guides/`;
- legacy Knowledge Base redirects and filter preservation;
- existing suggestions, articles, issues, releases, votes, surveys, settings, and relationships; and
- existing Guided Resolution and Support Discovery endpoints.

## Validation

The release validation suite covers:

- 25 WordPress plugin PHP files;
- 76 PHP contract files;
- 11 JavaScript files;
- 68 JSON files;
- 28 Python files;
- 84 FastAPI tests;
- four public CSS layers, including the 504-line unified interface stylesheet;
- source whitespace and Git diff compatibility;
- WordPress ZIP root and version integrity;
- repository and nested plugin package parity; and
- release-bundle SHA-256 verification.
