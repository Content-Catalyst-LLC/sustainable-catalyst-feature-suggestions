# Support Article Content Integrity — v5.2.8

Sustainable Catalyst Product Support and Feedback Platform v5.2.8 adds a publication-readiness layer for the existing `sc_support_article` post type. It does not replace articles, change their URLs, or automatically publish content.

## Validation scope

Each Support Article can be checked for:

- a specific title and sufficient article length;
- an excerpt or Knowledge Base summary;
- product, supported-version, component, Article Type, and collection assignments;
- a last verified version that matches assigned version terms;
- valid heading hierarchy and required editorial sections;
- unreplaced template placeholder text;
- empty, unsafe, missing-anchor, and unresolved internal links;
- image alternative text, figure captions, and table headers;
- release, known-issue, related-article, and reviewed-suggestion context;
- stale modification dates and overdue editorial reviews.

## Readiness states

- **Publication ready:** score of 90 or higher with no errors.
- **Review recommended:** score of 75–89 with no errors.
- **Needs work:** one or more errors while the score remains at least 50, or a low advisory score.
- **Publication blocked:** one or more errors and a score below 50.
- **Not validated:** no stored assessment exists yet.

These states are advisory. Existing WordPress capabilities and Editorial Governance remain the publication authority.

## WordPress administration

Open **Support & Feedback → Article Integrity** to:

- validate all Support Articles;
- filter by readiness state;
- inspect scores, freshness, and top issues;
- export a CSV integrity report;
- open each article for correction.

The Support Article editor includes a Publication Readiness panel and a manual validation action.

## REST and CLI

Administrative REST endpoints are available under `scfs/v1/support-article-integrity`. The public schema endpoint reveals capabilities but not private drafts or editorial notes.

WP-CLI examples:

```bash
wp scfs article-integrity scan
wp scfs article-integrity scan --product=decision-studio
wp scfs article-integrity article 812
```

## Safety boundaries

The validator does not rewrite article content, change URLs, publish records, create private cases, or expose private editorial information. Human review remains required.
