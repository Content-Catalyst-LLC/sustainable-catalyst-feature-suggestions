# Opportunity Scoring and Roadmap Workflow

Version 2.9.0 adds an administrator-controlled opportunity workflow that converts reviewed feedback into evidence-linked roadmap candidates without allowing votes or AI output to make decisions automatically.

## Scoring dimensions

The default score combines demand, impact, strategic alignment, evidence strength, public-interest value, implementation readiness, and an effort penalty. Administrators can change weights, the minimum evidence requirement, and the candidate threshold.

A score is advisory. The stored record includes the formula version, dimension values, weights, evidence count, confidence, calculation time, and a plain-language explanation.

## Roadmap states

- Unscored
- Roadmap candidate
- Under review
- Approved
- Planned
- In progress
- Released
- Parked
- Declined

State transitions require an authorized WordPress reviewer and are stored in an audit history.

## Handoffs

Each opportunity can export a versioned JSON packet for GitHub planning, Decision Studio, Site Intelligence, or other Sustainable Catalyst tools. The packet includes the original problem and proposal, success criteria, score evidence, AI analysis, public support count, owner, target release, rationale, and existing GitHub issue URL.

## REST API

Authenticated administrators can use:

- `GET /wp-json/scfs/v1/opportunities`
- `GET /wp-json/scfs/v1/opportunities/{id}/handoff`
- `POST /wp-json/scfs/v1/opportunities/{id}/recalculate`

## Governance boundary

Popularity is only one demand signal. AI classifications, scores, thresholds, and automatic candidate recommendations cannot approve, reject, schedule, publish, or release a feature. Human review remains mandatory.


## Support Demand dimension

Version 3.4.0 adds a separate Support Demand dimension based on privacy-safe case-to-suggestion relationships, linked documentation gaps, unresolved-search counts, and Guided Resolution result views. It complements public votes and duplicate suggestions; it does not replace reviewer judgment or automatically change roadmap state.
