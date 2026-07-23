#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="7.7.0"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN="$ROOT/wordpress/sustainable-catalyst-feature-suggestions"
PYTHON_BIN="${PYTHON_BIN:-python3}"
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
printf '==> Product Support and Feedback Platform v%s validation\n' "$VERSION"
printf '==> PHP syntax\n'
php_count="$(find "$PLUGIN" -type f -name '*.php' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.php' -print0 | xargs -0 -P 8 -n 1 php -l >/dev/null || fail "PHP syntax failed."
[ "$php_count" = "54" ] || fail "Expected 54 plugin PHP files, got $php_count."
printf 'PASS - %d plugin PHP files\n' "$php_count"
printf '==> Complete inherited WordPress contract suite\n'
contract_count="$(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | wc -l | tr -d ' ')"
contract_log="$(mktemp "${TMPDIR:-/tmp}/scpsf-v770-contract.XXXXXX")"
while IFS= read -r contract_file; do
  if ! TERM=dumb php "$contract_file" >"$contract_log" 2>&1; then
    cat "$contract_log" >&2
    rm -f "$contract_log"
    fail "PHP contract failed: ${contract_file#$ROOT/}"
  fi
done < <(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | LC_ALL=C sort)
rm -f "$contract_log"
[ "$contract_count" = "285" ] || fail "Expected 285 PHP contracts, got $contract_count."
printf 'PASS - %d PHP contract files executed sequentially\n' "$contract_count"
printf '==> Runtime identity and preserved WordPress slug\n'
php -r '$p=file_get_contents($argv[1]); if(!preg_match("/Plugin Name:[[:space:]]*Sustainable Catalyst Product Support and Feedback Platform/",$p)||!preg_match("/Version:[[:space:]]*7\\.7\\.0/",$p)||!preg_match("/const VERSION = '\''7\\.7\\.0'\'';/",$p)){exit(1);}' "$PLUGIN/sustainable-catalyst-feature-suggestions.php" || fail "Plugin identity mismatch."
[ ! -d "$ROOT/wordpress/sustainable-catalyst-product-support-feedback" ] || fail "Legacy WordPress directory was renamed."
printf 'PASS - v%s runtime with sustainable-catalyst-feature-suggestions preserved\n' "$VERSION"
printf '==> JavaScript syntax\n'
js_count="$(find "$PLUGIN" -type f -name '*.js' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.js' -print0 | xargs -0 -P 8 -n 1 node --check >/dev/null || fail "JavaScript syntax failed."
[ "$js_count" = "57" ] || fail "Expected 57 JavaScript files, got $js_count."
printf 'PASS - %d JavaScript files\n' "$js_count"
printf '==> JSON and Python syntax\n'
read -r json_count python_count <<COUNTS
$("$PYTHON_BIN" - "$ROOT" <<'PY'
from pathlib import Path
import json,py_compile,sys
root=Path(sys.argv[1]);jc=pc=0;ignored={'.git','.venv','venv','__pycache__','.pytest_cache'}
for p in root.rglob('*.json'):
    if any(x in ignored for x in p.parts): continue
    json.loads(p.read_text());jc+=1
for p in (root/'backend').rglob('*.py'):
    if any(x in ignored for x in p.parts): continue
    py_compile.compile(str(p),doraise=True);pc+=1
print(jc,pc)
PY
)
COUNTS
[ "$json_count" = "271" ] || fail "Expected 271 JSON files, got $json_count."
[ "$python_count" = "76" ] || fail "Expected 76 Python files, got $python_count."
printf 'PASS - %d JSON files and %d Python files\n' "$json_count" "$python_count"
printf '==> Canonical registry reconciler tests\n'
reconciler_output="$(cd "$ROOT/tools/canonical-product-registry" && "$PYTHON_BIN" -m unittest discover -s tests -q 2>&1)" || { printf '%s\n' "$reconciler_output" >&2; fail "Canonical registry reconciler tests failed."; }
printf '%s\n' "$reconciler_output"
printf 'PASS - canonical registry migration utility\n'
printf '==> Source whitespace compatibility\n'
"$PYTHON_BIN" - "$ROOT" <<'PY'
from pathlib import Path
import sys
root=Path(sys.argv[1]);failures=[];ignored={'.git','.venv','venv','__pycache__','.pytest_cache'};exts={'.php','.py','.js','.css','.json','.md','.txt','.sh','.yml','.yaml','.html','.csv'}
for p in root.rglob('*'):
    if not p.is_file() or any(x in ignored for x in p.parts) or p.suffix.lower() not in exts: continue
    try:text=p.read_text()
    except UnicodeDecodeError:continue
    for i,line in enumerate(text.splitlines(),1):
        if line.rstrip(' \t')!=line:failures.append(f'{p.relative_to(root)}:{i}: trailing whitespace')
    if text.endswith('\n\n'):failures.append(f'{p.relative_to(root)}: blank EOF lines')
if failures:print('\n'.join(failures));raise SystemExit(1)
PY
printf 'PASS - no trailing whitespace or blank EOF lines\n'
printf '==> CSS structural validation\n'
css_count="$("$PYTHON_BIN" - "$PLUGIN" <<'PY'
from pathlib import Path
import sys
files=sorted(Path(sys.argv[1]).rglob('*.css'))
for p in files:
 t=p.read_text();assert t.count('{')==t.count('}'),f'Unbalanced CSS: {p}'
print(len(files))
PY
)"
[ "$css_count" = "68" ] || fail "Expected 68 CSS layers, got $css_count."
printf 'PASS - %d balanced CSS layers\n' "$css_count"
printf '==> v7.7.0 Canonical Product Registry Administration metadata\n'
"$PYTHON_BIN" - "$ROOT" <<'PY'
from pathlib import Path
import json,sys
r=Path(sys.argv[1]);version='7.7.0';name='Canonical Product Registry Administration'
current=json.loads((r/'feature_suggestions_manifest.json').read_text());versioned=json.loads((r/'feature_suggestions_manifest-v7.7.0.json').read_text());release=json.loads((r/'release-manifest-v7.7.0.json').read_text());registry_schema=json.loads((r/'schemas/scfs-canonical-product-registry-v2.1.schema.json').read_text());admin_schema=json.loads((r/'schemas/scfs-canonical-product-registry-admin-v1.schema.json').read_text())
assert current==versioned
assert current['version']==release['version']==version
assert current['release_name']==release['release_name']==name
assert registry_schema['properties']['schema']['const']=='scfs-canonical-product-registry/2.1'
assert registry_schema['properties']['version']['const']==version
assert admin_schema['properties']['version']['const']==version
assert current['validation']['php_contract_files']==285
assert current['validation']['php_source_files']==54
assert current['validation']['javascript_files']==57
assert current['validation']['json_files']==271
assert current['validation']['css_layers']==68
admin=current['canonical_product_registry_administration']
for key in ('searchable_registry','family_and_lifecycle_filters','drag_drop_console_ordering','keyboard_ordering','create_product','merge_duplicates','alias_collision_review','archive_restore','administrator_history','governed_export','dry_run_import','automatic_backups','backup_restore','canonical_id_immutable','legacy_shortcodes_preserved'):
 assert admin[key] is True,key
print('PASS - Canonical Product Registry Administration release metadata')
PY
printf '==> Canonical Product Registry Administration contracts\n'
php "$ROOT/tests/test-v770-canonical-registry-administration.php" >/dev/null || fail "Registry administration source contract failed."
php "$ROOT/tests/test-v770-canonical-registry-runtime.php" >/dev/null || fail "Registry administration runtime contract failed."
printf 'PASS - search, ordering, lifecycle, merge, archive, import, backup, and history controls\n'
printf '==> Backend test suite\n'
backend_output="$(cd "$ROOT/backend" && PYTHONPATH=. PYTEST_ADDOPTS="--color=no" "$PYTHON_BIN" -m pytest -q)" || { printf '%s\n' "$backend_output" >&2; fail "Backend tests failed."; }
printf '%s\n' "$backend_output"
backend_passed="$(printf '%s\n' "$backend_output" | sed -nE 's/^([0-9]+) passed.*/\1/p' | tail -1)"
[ "$backend_passed" = "318" ] || fail "Expected 318 backend tests, got ${backend_passed:-unknown}."
printf 'PASS - 318 backend tests\n'
printf '==> Bash compatibility\n'
if grep -Eq '(^|[[:space:]])(mapfile|readarray)([[:space:]]|$)' "$ROOT/validate_v7_7_0.sh"; then fail "Validator requires Bash 4."; fi
bash -n "$ROOT/validate_v7_7_0.sh" || fail "Validator shell syntax failed."
printf 'PASS - Bash 3.2-compatible validator\n'
printf 'VALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
printf 'COUNTS: php_contracts=%s php_files=%s javascript_files=%s json_files=%s python_files=%s css_files=%s backend_tests=%s\n' "$contract_count" "$php_count" "$js_count" "$json_count" "$python_count" "$css_count" "$backend_passed"
