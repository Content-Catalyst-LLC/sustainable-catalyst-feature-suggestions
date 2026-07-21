# Product Support and Feedback Platform v6.12.0

## Reliability, Security, Privacy, and Production Hardening

v6.12.0 adds the production-control layer required to operate the connected help desk safely: rate limits, abuse-signal review, private security evidence, governed privacy operations, privacy-minimized audit exports, backup integrity records, isolated recovery drills, security-header review, accessibility and performance gates, scheduled hardening snapshots, and human-authorized production release gates.

### Additive private tables

- `scfs_help_desk_rate_limits`
- `scfs_help_desk_security_events`
- `scfs_help_desk_privacy_requests`
- `scfs_help_desk_audit_exports`
- `scfs_help_desk_backup_snapshots`
- `scfs_help_desk_recovery_drills`
- `scfs_help_desk_production_gates`
- `scfs_help_desk_hardening_health_snapshots`

### Human-control boundaries

The platform does not automatically permanently block users, execute destructive privacy operations, restore production systems, declare incidents, deploy releases, or include private correspondence and attachment contents in audit exports.

Contact and Engagement remains authoritative for identity, consent, private files, secure downloads, and customer communication.

### Compatibility

All existing public support and private help-desk records remain unchanged. Activation creates only additive hardening tables, capabilities, schedules, and configuration.
