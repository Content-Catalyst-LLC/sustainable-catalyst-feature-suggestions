# Known Issues and Release Intelligence Integration — v5.4.0

Version 5.4.0 connects the platform’s existing Known Issue, Release Record, Support Article, product-version, component, and changelog structures into one operational support layer. It does not create a replacement issue tracker, change existing URLs, or move private case information into public records.

## Public relationship model

A Known Issue can now identify:

- affected product versions and components through the existing shared taxonomies;
- a public symptom, workaround, resolution, and status note;
- target releases in which a fix is planned;
- fixed releases in which resolution was verified;
- related publication-grade Support Articles;
- a last-verified date and release-specific note.

A Release Record can now present:

- open and resolved Known Issues derived from the current issue status;
- major or critical unresolved issue warnings;
- related Support Articles;
- documentation and changelog links;
- a verification state and last-verified date.

## Bidirectional synchronization

The WordPress editor synchronizes relationships without changing the existing post types or tables. When a Known Issue is linked to a target or fixed release, the release’s existing `_scfs_release_related_issues` relationship is updated. Release issue groupings are then derived into open and resolved collections.

Synchronization is idempotent and can be run from:

`Support & Feedback → Issue & Release Links`

The screen also provides an advisory review queue for records missing affected versions, workarounds, target releases, fixed-release evidence, changelog links, or related Support Articles.

## Public rendering

Known Issue cards in the Support Center now include affected versions and target/fixed release context. Release cards show open and resolved issue counts plus verification status. Single Known Issue and Release pages receive publication-style operational metadata and related-record sections.

The optional shortcode is:

`[scfs_issue_release_intelligence]`

It accepts `product`, `limit`, and `show_summary` attributes.

## APIs

WordPress retains the `scfs/v1` namespace and adds:

- `/issue-release-intelligence/schema`
- `/issue-release-intelligence/issues`
- `/issue-release-intelligence/releases`
- `/issue-release-intelligence/record/{id}`
- `/issue-release-intelligence/sync` for administrators

FastAPI parity is available at:

- `GET /v1/issue-release-intelligence/capabilities`
- `POST /v1/issue-release-intelligence/evaluate`

The versioned contract is `scfs-known-issue-release-intelligence/1.0`.

## Governance boundary

The integration is advisory. It does not automatically:

- declare an incident;
- change a Known Issue or Release status;
- publish or rewrite content;
- block a release;
- create a private support case;
- expose private correspondence or identity data.

WordPress remains the source of truth and human review remains mandatory for operational decisions.
