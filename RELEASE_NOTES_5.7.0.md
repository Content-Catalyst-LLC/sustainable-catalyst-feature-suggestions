# Release Notes — v5.7.0

## Support Analytics and Documentation Effectiveness

Version 5.7.0 adds an administrator-only analytics layer that measures support discovery, article usefulness, publication integrity, content freshness, Known Issue guidance coverage, release documentation coverage, and Documentation Gap resolution.

### Added

- Support & Feedback → Support Analytics dashboard
- Product-level documentation-effectiveness scoring
- Search success and search-to-guidance engagement metrics
- Support Article helpfulness and integrity analysis
- Freshness and reverification reporting
- Known Issue and release documentation coverage
- Documentation Gap closure and linkage metrics
- Daily snapshots and 30-point trend history
- CSV export, REST APIs, WP-CLI, and FastAPI parity
- Deterministic SHA-256 report verification

### Governance

Analytics are privacy-minimized and advisory. No requester identities, raw search text, private case content, or uploaded documents are exposed. The system cannot publish content, resolve issues, change releases, or reprioritize the roadmap automatically.

### Compatibility

No database migration is required. All existing URLs, post types, shortcodes, REST routes, options, metadata, and records remain compatible.
