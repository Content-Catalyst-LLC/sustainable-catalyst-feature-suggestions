#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="7.5.3"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN="$ROOT/wordpress/sustainable-catalyst-feature-suggestions"
PYTHON_BIN="${PYTHON_BIN:-python3}"
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }

printf '==> Product Support and Feedback Platform v%s validation\n' "$VERSION"
printf '==> PHP syntax\n'
php_count="$(find "$PLUGIN" -type f -name '*.php' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.php' -print0 | xargs -0 -P 8 -n 1 php -l >/dev/null || fail "PHP syntax failed."
[ "$php_count" = "50" ] || fail "Expected 50 plugin PHP files, got $php_count."
printf 'PASS - %d plugin PHP files\n' "$php_count"

printf '==> Complete inherited WordPress contract suite\n'
contract_count="$(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | wc -l | tr -d ' ')"
contract_log="$(mktemp "${TMPDIR:-/tmp}/scpsf-v753-contract.XXXXXX")"
while IFS= read -r contract_file; do
  if ! TERM=dumb php "$contract_file" >"$contract_log" 2>&1; then
    cat "$contract_log" >&2
    rm -f "$contract_log"
    fail "PHP contract failed: ${contract_file#$ROOT/}"
  fi
done < <(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | LC_ALL=C sort)
rm -f "$contract_log"
[ "$contract_count" = "273" ] || fail "Expected 273 PHP contracts, got $contract_count."
printf 'PASS - %d PHP contract files executed sequentially\n' "$contract_count"

printf '==> Runtime identity and preserved WordPress slug\n'
php -r '$p=file_get_contents($argv[1]); if(!preg_match("/Plugin Name:[[:space:]]*Sustainable Catalyst Product Support and Feedback Platform/",$p)||!preg_match("/Version:[[:space:]]*7\\.5\\.3/",$p)||!preg_match("/const VERSION = '\''7\\.5\\.3'\'';/",$p)){exit(1);}' "$PLUGIN/sustainable-catalyst-feature-suggestions.php" || fail "Plugin identity mismatch."
[ ! -d "$ROOT/wordpress/sustainable-catalyst-product-support-feedback" ] || fail "Legacy WordPress directory was renamed."
printf 'PASS - v%s runtime with sustainable-catalyst-feature-suggestions preserved\n' "$VERSION"

printf '==> JavaScript syntax\n'
js_count="$(find "$PLUGIN" -type f -name '*.js' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.js' -print0 | xargs -0 -P 8 -n 1 node --check >/dev/null || fail "JavaScript syntax failed."
[ "$js_count" = "38" ] || fail "Expected 38 JavaScript files, got $js_count."
printf 'PASS - %d JavaScript files\n' "$js_count"

printf '==> JSON and Python syntax\n'
read -r json_count python_count <<COUNTS
$("$PYTHON_BIN" - "$ROOT" <<'PY'
from pathlib import Path
import json, py_compile, sys
root=Path(sys.argv[1]); jc=pc=0
ignored={'.git','.venv','venv','__pycache__','.pytest_cache'}
for p in root.rglob('*.json'):
    if any(x in ignored for x in p.parts): continue
    json.loads(p.read_text()); jc += 1
for p in (root/'backend').rglob('*.py'):
    if any(x in ignored for x in p.parts): continue
    py_compile.compile(str(p), doraise=True); pc += 1
print(jc, pc)
PY
)
COUNTS
[ "$json_count" = "210" ] || fail "Expected 210 JSON files, got $json_count."
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
root=Path(sys.argv[1]); failures=[]
ignored={'.git','.venv','venv','__pycache__','.pytest_cache'}
exts={'.php','.py','.js','.css','.json','.md','.txt','.sh','.yml','.yaml','.html','.csv'}
for p in root.rglob('*'):
    if not p.is_file() or any(x in ignored for x in p.parts) or p.suffix.lower() not in exts: continue
    try: text=p.read_text()
    except UnicodeDecodeError: continue
    for i,line in enumerate(text.splitlines(),1):
        if line.rstrip(' \t') != line: failures.append(f'{p.relative_to(root)}:{i}: trailing whitespace')
    if text.endswith('\n\n'): failures.append(f'{p.relative_to(root)}: blank EOF lines')
if failures:
    print('\n'.join(failures)); raise SystemExit(1)
PY
printf 'PASS - no trailing whitespace or blank EOF lines\n'

printf '==> CSS structural validation\n'
css_count="$("$PYTHON_BIN" - "$PLUGIN" <<'PY'
from pathlib import Path
import sys
files=sorted(Path(sys.argv[1]).rglob('*.css'))
for p in files:
    t=p.read_text(); assert t.count('{') == t.count('}'), f'Unbalanced CSS: {p}'
print(len(files))
PY
)"
[ "$css_count" = "49" ] || fail "Expected 49 CSS layers, got $css_count."
printf 'PASS - %d balanced CSS layers\n' "$css_count"

printf '==> v7.5.3 release metadata and connection contracts\n'
"$PYTHON_BIN" - "$ROOT" <<'PY'
from pathlib import Path
import json,sys
r=Path(sys.argv[1]); version='7.5.3'; name='Active Plugin and GitHub Console Connections'
current=json.loads((r/'feature_suggestions_manifest.json').read_text())
versioned=json.loads((r/'feature_suggestions_manifest-v7.5.3.json').read_text())
release=json.loads((r/'release-manifest-v7.5.3.json').read_text())
github_schema=json.loads((r/'schemas/scfs-canonical-product-github-sync-v1.schema.json').read_text())
assert current == versioned
assert current['version'] == release['version'] == version
assert current['release_name'] == release['release_name'] == name
assert current['repository'] == 'Content-Catalyst-LLC/sustainable-catalyst-product-support-feedback'
assert current['validation']['php_contract_files'] == 273
assert current['validation']['fastapi_tests'] == 318
assert current['validation']['javascript_files'] == 38
assert current['validation']['css_layers'] == 49
assert current['validation']['json_files'] == 210
for key in ('all_active_plugins_selectable','active_site_plugins_selectable','active_network_plugins_selectable','canonical_plugin_github_console_connection','inactive_plugins_excluded_from_connection_dropdown'):
    assert current['installed_plugin_discovery'][key] is True,key
for key in ('github_webhook_sync','github_hourly_poll_fallback','github_latest_release_drives_console_version','github_commit_evidence_governed'):
    assert current['canonical_product_registry'][key] is True,key
for key in ('repository_footer_link','legacy_releases_footer_removed','repository_footer_resolves_from_canonical_product','github_commit_indicator'):
    assert current['release_board'][key] is True,key
assert github_schema['properties']['version']['const'] == version
print('PASS - release identity, active-plugin mapping, GitHub synchronization, and repository footer metadata')
PY

printf '==> GitHub, active-plugin, and Release Console source contracts\n'
php "$ROOT/tests/test-v753-active-plugin-github-connections.php" >/dev/null || fail "v7.5.3 source contract failed."
php "$ROOT/tests/test-v753-github-sync-runtime.php" >/dev/null || fail "v7.5.3 GitHub runtime contract failed."
printf 'PASS - active plugins, canonical identity, GitHub webhook/polling, console updates, and repository footer\n'

printf '==> Backend test suite\n'
backend_output="$(cd "$ROOT/backend" && PYTHONPATH=. "$PYTHON_BIN" -m pytest -q)" || { printf '%s\n' "$backend_output" >&2; fail "Backend tests failed."; }
printf '%s\n' "$backend_output"
backend_passed="$(printf '%s\n' "$backend_output" | sed -nE 's/^([0-9]+) passed.*/\1/p' | tail -1)"
[ "$backend_passed" = "318" ] || fail "Expected 318 backend tests, got ${backend_passed:-unknown}."
printf 'PASS - 318 backend tests\n'

printf '==> Bash compatibility\n'
if grep -Eq '(^|[[:space:]])(mapfile|readarray)([[:space:]]|$)' "$ROOT/validate_v7_5_3.sh"; then fail "Validator requires Bash 4."; fi
bash -n "$ROOT/validate_v7_5_3.sh" || fail "Validator shell syntax failed."
printf 'PASS - Bash 3.2-compatible validator\n'

printf 'VALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
printf 'COUNTS: php_contracts=%s php_files=%s javascript_files=%s json_files=%s python_files=%s css_files=%s backend_tests=%s\n' "$contract_count" "$php_count" "$js_count" "$json_count" "$python_count" "$css_count" "$backend_passed"
