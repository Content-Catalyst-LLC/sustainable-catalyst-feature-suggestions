# Cross-Product Support Graph and Platform Handoffs

Version 5.8.0 adds a governed support graph to the Sustainable Catalyst Product Support and Feedback Platform. The graph connects products, versions, components, capabilities, public documentation, Known Issues, release intelligence, worked examples, troubleshooting guidance, and related-product handoffs.

## Purpose

The Support Center already resolves product-specific questions. The graph adds the next layer: it helps a reader recognize when the answer lives in another Sustainable Catalyst product and carries product context into that destination rather than forcing the reader to start over.

The graph is not a general navigation map. It is a support contract whose nodes and edges are grounded in public product records and explicit platform relationships.

## Canonical node contract

Each product node includes:

- Product slug and public name
- Product route
- Filtered Support Center route
- Supported capabilities
- Related products
- Published Support Article count
- Known Issue count
- Release record count
- Example coverage
- Troubleshooting coverage
- Deterministic coverage score and state

The built-in catalog covers Decision Studio, Catalyst Canvas, Catalyst Data, Catalyst Finance, Catalyst Grit, Catalyst Narrative Risk, Site Intelligence, Sustainable Catalyst Lab, Workbench, Knowledge Library, Research Librarian, and Platform Core and Infrastructure. WordPress product-taxonomy terms can extend the catalog, and the `scfs_cross_product_support_catalog` filter allows a product module to provide its own contract without direct plugin coupling.

## Graph edges

Supported edge types are:

- `depends_on`
- `integrates_with`
- `shares_component`
- `routes_to`
- `provides_data_to`

Catalog handoffs create default `routes_to` edges. Existing v5.1.0 cross-product dependency records remain authoritative and are merged into the v5.8.0 graph.

## Platform handoffs

A handoff request may carry:

- Starting product
- Product version
- Component
- Public task or symptom description

The planner compares the public context with product capabilities and explicit graph edges. It returns ranked related products, support links, product links, coverage state, and transparent ranking reasons. It never redirects automatically.

## Public interface

Use:

```text
[scfs_cross_product_support_graph]
```

Compatibility alias:

```text
[scfs_platform_support_graph]
```

The interface displays a product catalog when no product is selected and a product-aware handoff view after selection.

## Administration

Open:

```text
Support & Feedback → Support Graph
```

The administration screen shows graph coverage, product capabilities, record counts, edge totals, and integrity findings. Administrators can refresh the graph or export a deterministic JSON snapshot.

## API

WordPress endpoints use the existing `scfs/v1` namespace under `/support-graph/`. FastAPI parity uses `/v1/support-graph/`.

## Privacy boundary

The graph uses public support records only. It excludes requester identities, contact details, raw search text, private case correspondence, uploaded private documents, and private Contact and Engagement records. Private support remains a separate terminal handoff.

## Governance boundary

The graph cannot publish content, resolve a Known Issue, change release status, reprioritize the roadmap, create a private case, or redirect a visitor automatically. Human review remains required for product relationships and handoff guidance.
