# Canonical Product Registry Reconciler

This utility turns the Canonical Product Registry into the single product-identity authority for the Sustainable Catalyst platform.

It maps legacy identifiers into 17 governed product records, including:

- `platform-core` → `sustainable-catalyst-core`
- `feature-suggestions` and `product-support-platform` → `product-support-feedback`
- `contact-and-engagement` → `contact-engagement`
- `research-library` and `foundations` → `knowledge-library`
- `research-guidance-platform` → `research-librarian`
- `catalyst-narrative-risk` → `narrative-risk`
- `research-lab` → `sustainable-catalyst-lab`
- `catalyst-intelligence-platform` → `catalyst-intelligence`

The remaining approved products receive the same complete identity, discovery, support, documentation, release-intelligence, ownership, visibility, and verification field set.

## Safety model

Dry-run is the default. The script:

1. Reads the current registry.
2. Matches records by canonical ID, legacy ID, name, repository slug, plugin slug, plugin file, and text domain.
3. Merges duplicate legacy records into the approved canonical record.
4. Preserves versions, discovery results, release metadata, administrator notes, URLs, and timestamps.
5. Seeds missing approved products.
6. Preserves unknown records unless `--drop-unknown` is explicitly used.
7. Stops on alias collisions.
8. In WordPress apply mode, creates a JSON backup, writes the option, rescans installed plugins, validates the registry, and restores the backup if either step fails.

## Recommended live-site workflow

From Terminal:

```bash
cd /path/to/canonical-product-registry

./reconcile_canonical_product_registry_macos.sh \
  --wordpress-path "/path/to/wordpress"
```

Review:

- `canonical-product-registry-dry-run.json`
- `canonical-product-registry-dry-run-report.json`

Then apply:

```bash
./reconcile_canonical_product_registry_macos.sh \
  --wordpress-path "/path/to/wordpress" \
  --apply
```

To populate manual/package versions from product repositories in a local parent folder:

```bash
./reconcile_canonical_product_registry_macos.sh \
  --wordpress-path "/path/to/wordpress" \
  --products-root "$HOME/Downloads" \
  --apply
```

The version scanner recognizes WordPress plugin headers, `release-manifest*.json`, `package.json`, R `DESCRIPTION`, and Python `pyproject.toml` files.

## Offline mode

Export the registry or use a saved JSON file:

```bash
./reconcile_canonical_product_registry_macos.sh \
  --input canonical-product-registry.json
```

Use `--apply` in offline mode only to mark the generated output as the intended applied artifact. It does not contact WordPress.

## Important behavior

The script does **not** delete or rewrite legacy shortcodes. Legacy names and slugs are retained as aliases so existing integrations can continue to resolve while public and administrative surfaces use canonical IDs.
