# Product Support and Feedback Platform v6.3.0 — Installer Repair R2

This repair corrects the macOS install-and-push script for v6.3.0.

The original v6.3.0 installer selected the correct v6.3.0 source archive but invoked `validate_v6_2_0.sh`. That validator correctly rejected the v6.3.0 plugin header as a version mismatch.

R2 changes only the release and installation tooling:

- requires the repaired R2 repository ZIP or release bundle;
- selects `validate_v6_3_0.sh` for packaged-source, installed-source, rebase, and push-retry validation;
- verifies that the selected validator declares version 6.3.0;
- verifies that the selected plugin source declares version 6.3.0;
- retains Python 3.13/3.12 selection, pytest installation, macOS Bash 3.2 compatibility, safety backups, remote synchronization, and non-force main-branch pushing.

The WordPress plugin functionality and private customer-portal schema are unchanged.
