# Help Desk Reliability, Security, Privacy, and Production Hardening v6.12.0

## Purpose

This layer turns production readiness into explicit, auditable evidence rather than an informal checklist. It governs rate limits, abuse review, privacy requests, backup integrity, recovery exercises, HTTP security controls, accessibility and performance budgets, and release authorization.

## Authority boundaries

The help desk records metadata and decisions. Contact and Engagement remains authoritative for requester identity, consent, secure attachment storage, secure download delivery, and customer communication. Infrastructure operators remain authoritative for backup creation, malware scanning, secret rotation, and restoration execution.

## Release gate

A production release cannot reach `ready` unless source validation, package validation, database migration review, current backups, a recovery drill, security controls, privacy review, rollback documentation, and change authorization all pass. Accessibility, performance, and monitoring are separately reported and may produce a conditional state.

## Privacy operations

Access, export, rectification, restriction, deletion, and retention-review requests are tracked with hashed requester references. Destructive actions require verified identity, legal-hold review, and authorized human approval. The platform records the plan and completion evidence but never silently deletes records.

## Recovery

Recovery drills run in isolated staging environments. Their evidence includes database restore, file restore, integrity checks, smoke tests, recovery time, and recovery point. A successful drill does not authorize a production restore.
