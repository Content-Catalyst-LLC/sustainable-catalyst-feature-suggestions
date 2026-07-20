# Help Desk Secure Evidence Architecture — v6.5.0

## Boundary

The Product Support and Feedback Platform is the case and evidence-governance authority. Contact and Engagement is the private identity, consent, storage, secure-download, and deletion-execution authority. WordPress Media Library storage is prohibited.

## Evidence lifecycle

1. An authorized agent requests evidence for a private case.
2. The system creates an expiring intake record with purpose, classification, allowed MIME types, size limit, and consent requirement.
3. Contact and Engagement receives a handoff event and performs secure upload intake.
4. After malware scanning, the authority registers attachment metadata and an opaque external reference.
5. Agents review scan and redaction state before issuing a short-lived access grant.
6. Retention actions enter human review and are handed to the storage authority for execution.
7. Every transition creates append-only case and evidence events.

## Diagnostic bundles

Diagnostic manifests contain product, version, environment, file names, sizes, MIME types, and SHA-256 checksums. Credential-like fields and production datasets are rejected. Bundles require scan and redaction review before use.

## Privacy and security controls

- No raw attachment bytes in this plugin.
- No Media Library records.
- No raw access secrets or download URLs stored.
- No unauthenticated public upload endpoint.
- No automatic redaction or deletion.
- Legal holds block deletion.
- Quarantined evidence cannot receive an access grant.
- Customer portal payloads contain only active evidence-intake instructions and privacy statements.
