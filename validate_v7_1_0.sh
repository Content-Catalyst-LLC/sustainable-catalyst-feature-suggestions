#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="7.1.0"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN="$ROOT/wordpress/sustainable-catalyst-feature-suggestions"
PYTHON_BIN="${PYTHON_BIN:-python3}"
CONTRACT_EXECUTION_MODE="sequential-state-safe"
CANONICAL_REPOSITORY="Content-Catalyst-LLC/sustainable-catalyst-product-support-feedback"
LEGACY_REPOSITORY="Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions"
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
printf '==> Product Support and Feedback Platform v%s validation\n' "$VERSION"
printf '==> PHP syntax\n'
php_count="$(find "$PLUGIN" -type f -name '*.php' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.php' -print0 | xargs -0 -P 8 -n 1 php -l >/dev/null || fail "PHP syntax failed."
printf 'PASS - %d plugin PHP files\n' "$php_count"
printf '==> WordPress contract suite (%s)\n' "$CONTRACT_EXECUTION_MODE"
contract_count="$(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | wc -l | tr -d ' ')"
contract_log="$(mktemp "${TMPDIR:-/tmp}/scpsf-v710-contract.XXXXXX")"
while IFS= read -r contract_file; do
  if ! php "$contract_file" >"$contract_log" 2>&1; then
    cat "$contract_log" >&2
    rm -f "$contract_log"
    fail "PHP contract failed: ${contract_file#$ROOT/}"
  fi
done < <(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | LC_ALL=C sort)
rm -f "$contract_log"
printf 'PASS - %d PHP contract files executed sequentially\n' "$contract_count"
printf '==> Runtime and WordPress compatibility identity\n'
php -r '$plugin=file_get_contents($argv[1]);if(!preg_match("/^[[:space:]]*\\*[[:space:]]*Plugin Name:[[:space:]]*Sustainable Catalyst Product Support and Feedback Platform[[:space:]]*$/m",$plugin)){exit(1);}if(!preg_match("/^[[:space:]]*\\*[[:space:]]*Version:[[:space:]]*7\\.1\\.0[[:space:]]*$/m",$plugin)){exit(1);}if(!preg_match("/const VERSION = '\''7\\.1\\.0'\'';/",$plugin)){exit(1);}if(!preg_match("/^[[:space:]]*\\*[[:space:]]*Text Domain:[[:space:]]*sustainable-catalyst-feature-suggestions[[:space:]]*$/m",$plugin)){exit(1);}' "$PLUGIN/sustainable-catalyst-feature-suggestions.php" || fail "Plugin identity or compatibility check failed."
[ ! -d "$ROOT/wordpress/sustainable-catalyst-product-support-feedback" ] || fail "WordPress plugin directory must not be renamed."
printf 'PASS - public name and v%s runtime with legacy WordPress directory and text domain preserved\n' "$VERSION"
printf '==> JavaScript syntax\n'
js_count="$(find "$PLUGIN" -type f -name '*.js' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.js' -print0 | xargs -0 -P 8 -n 1 node --check >/dev/null || fail "JavaScript syntax failed."
printf 'PASS - %d JavaScript files\n' "$js_count"
printf '==> JSON and Python syntax\n'
read -r json_count python_count < <("$PYTHON_BIN" - "$ROOT" <<'PYSYNTAX'
from pathlib import Path
import json,py_compile,sys
root=Path(sys.argv[1]);jc=pc=0
for p in root.rglob('*.json'):
    if any(x in {'.git','.venv','venv','__pycache__'} for x in p.parts): continue
    json.loads(p.read_text());jc+=1
for p in (root/'backend').rglob('*.py'):
    if '__pycache__' in p.parts: continue
    py_compile.compile(str(p),doraise=True);pc+=1
print(jc,pc)
PYSYNTAX
)
printf 'PASS - %d JSON files and %d Python files\n' "$json_count" "$python_count"
printf '==> Source whitespace compatibility\n'
"$PYTHON_BIN" - "$ROOT" <<'PYSPACE'
from pathlib import Path
import sys
root=Path(sys.argv[1]);failures=[];exts={'.php','.py','.js','.css','.json','.md','.txt','.sh','.yml','.yaml','.html','.csv'}
for p in root.rglob('*'):
    if not p.is_file() or any(x in {'.git','.venv','venv','__pycache__'} for x in p.parts) or p.suffix.lower() not in exts: continue
    try: text=p.read_text()
    except UnicodeDecodeError: continue
    for i,line in enumerate(text.splitlines(),1):
        if line.rstrip(' \t')!=line: failures.append(f'{p.relative_to(root)}:{i}: trailing whitespace')
    if text.endswith('\n\n'): failures.append(f'{p.relative_to(root)}: blank EOF lines')
if failures:
    print('\n'.join(failures));raise SystemExit(1)
PYSPACE
printf 'PASS - source tree contains no trailing whitespace or blank EOF lines\n'
printf '==> CSS structural validation\n'
css_count="$("$PYTHON_BIN" - "$PLUGIN" <<'PYCSS'
from pathlib import Path
import sys
files=sorted(Path(sys.argv[1]).rglob('*.css'))
for p in files:
    t=p.read_text();assert t.count('{')==t.count('}'),f'Unbalanced CSS: {p}'
print(len(files))
PYCSS
)"
printf 'PASS - %d balanced CSS layers\n' "$css_count"
printf '==> Canonical product registry and release manifests\n'
"$PYTHON_BIN" - "$ROOT" <<'PYMANIFEST'
from pathlib import Path
import json,sys
r=Path(sys.argv[1])
current=json.loads((r/'feature_suggestions_manifest.json').read_text())
versioned=json.loads((r/'feature_suggestions_manifest-v7.1.0.json').read_text())
historical=json.loads((r/'feature_suggestions_manifest-v7.0.1.json').read_text())
release=json.loads((r/'release-manifest-v7.1.0.json').read_text())
example=json.loads((r/'examples/canonical-product-registry-v7.1.0.json').read_text())
schema=json.loads((r/'schemas/scfs-canonical-product-registry-v1.schema.json').read_text())
assert current==versioned
assert current['version']=='7.1.0'
assert current['repository']=='Content-Catalyst-LLC/sustainable-catalyst-product-support-feedback'
registry=current['canonical_product_registry']
assert registry['schema']=='scfs-canonical-product-registry/1.0'
assert registry['seeded_product_count']==17
assert registry['active_version_sources']==['wordpress_plugin','manual']
assert registry['automatic_plugin_discovery'] is False
assert registry['automatic_publication'] is False
assert registry['private_repository_fields_publicly_exposed'] is False
assert registry['human_review_required'] is True
assert historical['version']=='7.0.1'
assert release['version']=='7.1.0'
assert release['release_name']=='Canonical Product Registry'
assert release['compatibility']['database_migration_required'] is False
assert release['compatibility']['existing_product_taxonomies_preserved'] is True
assert example['schema']==registry['schema']
assert schema['properties']['schema']['const']==registry['schema']
print('PASS - canonical registry, manual Catalyst Intelligence source, compatibility, schema, and example contracts validated')
PYMANIFEST
printf '==> Product registry source contract\n'
REGISTRY_CLASS="$PLUGIN/includes/class-scfs-canonical-product-registry.php"
[ -f "$REGISTRY_CLASS" ] || fail "Missing canonical product registry class."
grep -Fq "const VERSION = '7.1.0';" "$REGISTRY_CLASS" || fail "Registry class version mismatch."
grep -Fq "const OPTION_KEY = 'scfs_canonical_product_registry';" "$REGISTRY_CLASS" || fail "Registry option key missing."
for product_id in sustainable-catalyst-core product-support-feedback contact-engagement knowledge-library catalyst-intelligence; do
  grep -Fq "'$product_id'" "$REGISTRY_CLASS" || fail "Required product missing: $product_id"
done
grep -Fq "'public_version' => '0.23.1'" "$REGISTRY_CLASS" || fail "Catalyst Intelligence manual version missing."
grep -Fq "'automatic_publication' => false" "$REGISTRY_CLASS" || fail "Automatic-publication boundary missing."
grep -Fq "'private_repository_fields_publicly_exposed' => false" "$REGISTRY_CLASS" || fail "Private repository boundary missing."
printf 'PASS - registry source contract and foundation products validated\n'
printf '==> Installer contract\n'
INSTALLER="$ROOT/install_and_push_sustainable_catalyst_product_support_feedback_v7_1_0_macos.sh"
[ -f "$INSTALLER" ] || fail "Missing v7.1.0 installer."
bash -n "$INSTALLER" || fail "Invalid installer shell syntax."
grep -Fq 'git@github.com:Content-Catalyst-LLC/sustainable-catalyst-product-support-feedback.git' "$INSTALLER" || fail "Installer does not use canonical remote."
grep -Fq 'sustainable-catalyst-product-support-feedback' "$INSTALLER" || fail "Installer does not recognize canonical local folder."
grep -Fq 'sustainable-catalyst-feature-suggestions' "$INSTALLER" || fail "Installer does not preserve legacy local folder and WordPress slug support."
grep -Fq 'SHA256SUMS' "$INSTALLER" || fail "Installer does not require the checksum manifest."
grep -Fq 'shasum -a 256 -c' "$INSTALLER" || fail "Installer does not verify release checksums."
if grep -Eq '(^|[[:space:]])(mapfile|readarray)([[:space:]]|$)' "$INSTALLER" "$ROOT/validate_v7_1_0.sh"; then fail "Release scripts require Bash 4."; fi
printf 'PASS - canonical installer and Bash 3.2 compatibility validated\n'
printf '==> FastAPI backend tests\n'
(cd "$ROOT/backend" && "$PYTHON_BIN" -m pytest -q)
printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
