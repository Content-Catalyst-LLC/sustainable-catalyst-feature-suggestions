# Cross-Product Support Orchestration

Feature Suggestions v4.5.0 adds a public coordination layer for issues and support journeys that span more than one Sustainable Catalyst product.

## Scope

The orchestration layer manages:

- public Platform Incident records;
- product dependency and shared-component relationships;
- multi-product Support Articles, Known Issues, and Release Records;
- dependency-aware issue and release context;
- related-product recommendations;
- product handoff pathways;
- cross-product resolution journeys; and
- platform incident summaries inside the Product Support Center.

It does not store requester identities, private correspondence, private documents, or private support-case narratives. Contact and Engagement remains the source of truth for private case management.

## Platform Incidents

`sc_platform_incident` is a public, editorially governed post type. Each incident can carry:

- investigating, identified, monitoring, or resolved status;
- low, moderate, high, or critical severity;
- public summary and workaround;
- start and resolution timestamps;
- multiple product, version, component, issue-type, and release relationships; and
- deterministic advisory impact scoring.

Publishing and incident declaration always require human review.

## Dependency graph

Administrators configure one relationship per line:

```text
source-product | relationship | target-product | component | criticality | active
```

Supported relationships are `depends_on`, `integrates_with`, `shares_component`, `routes_to`, and `provides_data_to`.

## Public integration

The Product Support Center gains a **Platform status** workspace. The standalone shortcode is:

```text
[scfs_cross_product_support product="research-librarian"]
```

Public REST records are available under `/wp-json/scfs/v1/cross-product/`.

## Governance boundary

The orchestration engine may recommend related products, rank dependency impact, and build public resolution journeys. It cannot automatically declare an incident, block a release, change the roadmap, publish content, or create a private case.
