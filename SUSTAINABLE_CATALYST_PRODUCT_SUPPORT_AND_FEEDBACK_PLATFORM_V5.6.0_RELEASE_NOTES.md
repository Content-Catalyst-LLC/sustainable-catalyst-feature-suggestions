# Sustainable Catalyst Product Support and Feedback Platform

## v5.6.0 — Feedback Intelligence and Product Signals

This release connects the platform's public feedback and support evidence into a single governed product-signal system.

## Product signal sources

The new layer aggregates:

1. Feature suggestions and product taxonomy
2. Advisory public support votes
3. Support Article helpfulness feedback and review reasons
4. Unresolved Guided Resolution searches
5. Low-confidence and failed public resolution paths
6. Open Documentation Gaps
7. Active Known Issues and severity
8. Privacy-safe support relationships

## Product review records

Each product receives a deterministic record containing:

- evidence count
- signal score from 0–100
- signal state
- demand and quality dimensions
- common components and versions
- feedback-reason distributions
- recommended review actions

The state can be insufficient evidence, monitor, emerging, elevated, or critical review. A critical-review state is an advisory review signal, not an incident declaration.

## Evidence clusters

The system prioritizes unresolved patterns such as missing steps, outdated guidance, unmatched support searches, open Documentation Gaps, and repeated product demand. Search clusters use hashes and aggregate context rather than raw query text.

## Operations

Administrators can review and export the dashboard from **Support & Feedback → Product Signals**. Daily refresh is supported through WordPress cron, with WP-CLI and protected REST alternatives.

## Human authority

The system cannot automatically:

- accept, reject, schedule, or release a feature
- change roadmap status
- declare or resolve an incident
- change a Known Issue state
- change release state
- publish content
- create a private support case

All decisions remain with authorized human reviewers.

## Backward compatibility

The release preserves the plugin directory, text domain, PHP class, `scfs_*` identifiers, all existing post types and records, the `scfs/v1` REST namespace, all public shortcodes, the canonical `/support/` page, legacy redirects, and `/support/guides/` article URLs.
