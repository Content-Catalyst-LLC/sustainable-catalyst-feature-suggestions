# Product Support and Feedback Platform v6.0.0 — R4 Installer Repair

Installer revision: `V6_0_0_R4_ARCHIVE_PRECEDENCE_LOCK`

This installer-only repair prevents an older `REPAIRED.zip` archive from being selected when the required R3/R4 package is also available.

## Repairs

- Accepts only `REPAIRED-R4` or `REPAIRED-R3` v6.0.0 repository and bundle archives.
- Ignores the older `REPAIRED.zip`, original repository ZIP, and original release bundle.
- Prioritizes all R4/R3 repository and bundle candidates before extraction.
- Searches nested release bundles only for R4/R3 repository ZIPs.
- Verifies `backend/requirements-validation.txt` before creating the virtual environment.
- Provides a preflight mode through `SCFS_PREFLIGHT_ONLY=1` for archive-selection diagnostics.
- Retains the Bash 3.2 CSS validation repair, explicit pytest dependency installation, Python 3.12/3.13 selection, and remote-sync-safe Git push behavior.

The WordPress plugin version remains v6.0.0 and its runtime source is unchanged.
