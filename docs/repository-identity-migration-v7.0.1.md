# Repository Identity Migration — v7.0.1

## Canonical identities

| Surface | Identity |
|---|---|
| Public product name | Sustainable Catalyst Product Support and Feedback Platform |
| GitHub repository | `Content-Catalyst-LLC/sustainable-catalyst-product-support-feedback` |
| Local repository folder | `sustainable-catalyst-product-support-feedback` |
| WordPress plugin folder | `sustainable-catalyst-feature-suggestions` |
| WordPress text domain | `sustainable-catalyst-feature-suggestions` |
| REST namespace | `scfs/v1` |

The GitHub and local repository identities now match the public product. The WordPress folder, text domain, class names, data keys, and routes remain unchanged to avoid upgrade and activation regressions.

## Migration sequence

1. Confirm v7.0.0 is committed and tagged.
2. Rename the GitHub repository in GitHub Settings.
3. Place the v7.0.1 release bundle in `~/Downloads`.
4. Run `install_and_push_sustainable_catalyst_product_support_feedback_v7_0_1_macos.sh`.
5. Confirm the installer reports the canonical remote and local directory.
6. Confirm the WordPress package still contains the `sustainable-catalyst-feature-suggestions` top-level plugin folder.

## Installer behavior

- Uses the canonical SSH remote.
- Detects an existing canonical local folder first.
- Detects the legacy local folder second.
- Creates ZIP and Git bundle backups before moving an existing repository.
- Refuses to overwrite a conflicting canonical folder.
- Renames only the local Git repository folder.
- Never renames the WordPress plugin folder.
- Validates source, package layout, repository metadata, and checksums before pushing.

## Rollback

The installer creates a safety ZIP and, when possible, a Git bundle. A local folder can be renamed back without affecting WordPress. GitHub also maintains redirects from the former repository URL unless the old repository name is reused.
