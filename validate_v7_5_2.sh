#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="7.5.2"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN="$ROOT/wordpress/sustainable-catalyst-feature-suggestions"
PYTHON_BIN="${PYTHON_BIN:-python3}"
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }

printf '==> Product Support and Feedback Platform v%s validation\n' "$VERSION"
printf '==> PHP syntax\n'
php_count="$(find "$PLUGIN" -type f -name '*.php' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.php' -print0 | xargs -0 -P 8 -n 1 php -l >/dev/null || fail "PHP syntax failed."
printf 'PASS - %d plugin PHP files\n' "$php_count"

printf '==> Inherited WordPress contract suite\n'
contract_count="$(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | wc -l | tr -d ' ')"
contract_log="$(mktemp "${TMPDIR:-/tmp}/scpsf-v752-contract.XXXXXX")"
while IFS= read -r contract_file; do
  if ! TERM=dumb php "$contract_file" >"$contract_log" 2>&1; then
    cat "$contract_log" >&2
    rm -f "$contract_log"
    fail "PHP contract failed: ${contract_file#$ROOT/}"
  fi
done < <(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | LC_ALL=C sort)
rm -f "$contract_log"
[ "$contract_count" = "271" ] || fail "Expected 271 PHP contracts, got $contract_count."
printf 'PASS - %d PHP contract files executed sequentially\n' "$contract_count"

printf '==> Runtime identity and compatibility\n'
php -r '$p=file_get_contents($argv[1]); if(!preg_match("/Plugin Name:[[:space:]]*Sustainable Catalyst Product Support and Feedback Platform/",$p)||!preg_match("/Version:[[:space:]]*7\\.5\\.2/",$p)||!preg_match("/const VERSION = '\''7\\.5\\.2'\'';/",$p)){exit(1);}' "$PLUGIN/sustainable-catalyst-feature-suggestions.php" || fail "Plugin identity mismatch."
[ ! -d "$ROOT/wordpress/sustainable-catalyst-product-support-feedback" ] || fail "Legacy WordPress directory was renamed."
printf 'PASS - v%s runtime with legacy plugin slug preserved\n' "$VERSION"

printf '==> JavaScript syntax\n'
js_count="$(find "$PLUGIN" -type f -name '*.js' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.js' -print0 | xargs -0 -P 8 -n 1 node --check >/dev/null || fail "JavaScript syntax failed."
printf 'PASS - %d JavaScript files\n' "$js_count"

printf '==> JSON and Python syntax\n'
read -r json_count python_count <<COUNTS
$("$PYTHON_BIN" - "$ROOT" <<'PY'
from pathlib import Path
import json, py_compile, sys
root=Path(sys.argv[1]); jc=pc=0
ignored={'.git','.venv','venv','__pycache__','.pytest_cache'}
for p in root.rglob('*.json'):
    if any(x in ignored for x in p.parts):
        continue
    json.loads(p.read_text()); jc += 1
for p in (root/'backend').rglob('*.py'):
    if any(x in ignored for x in p.parts):
        continue
    py_compile.compile(str(p), doraise=True); pc += 1
print(jc, pc)
PY
)
COUNTS
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
    if not p.is_file() or any(x in ignored for x in p.parts) or p.suffix.lower() not in exts:
        continue
    try:
        text=p.read_text()
    except UnicodeDecodeError:
        continue
    for i,line in enumerate(text.splitlines(),1):
        if line.rstrip(' \t') != line:
            failures.append(f'{p.relative_to(root)}:{i}: trailing whitespace')
    if text.endswith('\n\n'):
        failures.append(f'{p.relative_to(root)}: blank EOF lines')
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
printf 'PASS - %d balanced CSS layers\n' "$css_count"

printf '==> v7.5.2 release manifests and examples\n'
"$PYTHON_BIN" - "$ROOT" <<'PY'
from pathlib import Path
import json,sys
r=Path(sys.argv[1])
current=json.loads((r/'feature_suggestions_manifest.json').read_text())
versioned=json.loads((r/'feature_suggestions_manifest-v7.5.2.json').read_text())
release=json.loads((r/'release-manifest-v7.5.2.json').read_text())
discovery_schema=json.loads((r/'schemas/scfs-installed-plugin-discovery-v1.schema.json').read_text())
discovery_example=json.loads((r/'examples/plugin-discovery-status-v7.5.2.json').read_text())
name='Canonical Plugin Mapping and Review Workflow'
assert current == versioned
assert current['version'] == '7.5.2'
assert current['release_name'] == name
assert current['repository'] == 'Content-Catalyst-LLC/sustainable-catalyst-product-support-feedback'
assert current['validation']['php_contract_files'] == 271
assert current['validation']['fastapi_tests'] == 318
assert current['validation']['javascript_files'] == 36
assert current['validation']['css_layers'] == 47
discovery=current['installed_plugin_discovery']
assert discovery['matching_hierarchy'][0] == 'administrator_mapping'
for key in ('canonical_product_dropdown_mapping','administrator_mapping_precedence','canonical_alias_persistence','duplicate_plugin_reassignment','alias_collision_protection','ignored_plugin_restore','manual_mapping_removal','mapping_audit_metadata','ajax_progressive_enhancement','non_javascript_form_fallback','live_queue_and_zero_state_refresh','installed_plugin_folder_preserved'):
    assert discovery[key] is True,key
assert discovery['zero_state_message'] == 'No plugins awaiting review'
assert release['version'] == '7.5.2'
assert release['release_name'] == name
assert release['validation']['php_contract_files'] == 271
assert release['validation']['backend_tests_passed'] == 318
assert release['validation']['mapping_runtime_simulation'] is True
assert release['validation']['rest_fragment_refresh'] is True
assert discovery_schema['properties']['version']['const'] == '7.5.2'
assert 'administrator_mapping' in discovery_schema['$defs']['match']['properties']['match_strategy']['enum']
assert discovery_example['version'] == '7.5.2'
print('PASS - release identity, mapping governance, runtime validation, and compatibility metadata validated')
PY

printf '==> Canonical mapping source contracts\n'
DISCOVERY="$PLUGIN/includes/class-scfs-installed-plugin-discovery.php"
REGISTRY="$PLUGIN/includes/class-scfs-canonical-product-registry.php"
MAPPING_JS="$PLUGIN/assets/plugin-discovery-v7.5.2.js"
MAPPING_CSS="$PLUGIN/assets/plugin-discovery-v7.5.2.css"
for f in "$DISCOVERY" "$REGISTRY" "$MAPPING_JS" "$MAPPING_CSS"; do [ -s "$f" ] || fail "Missing ${f#$ROOT/}."; done
grep -Fq "const VERSION = '7.5.2';" "$DISCOVERY" || fail "Plugin Discovery version mismatch."
grep -Fq "const MAPPINGS_OPTION = 'scfs_installed_plugin_discovery_mappings';" "$DISCOVERY" || fail "Mapping storage missing."
grep -Fq "const IGNORED_OPTION = 'scfs_installed_plugin_discovery_ignored';" "$DISCOVERY" || fail "Ignored candidate storage missing."
grep -Fq 'Map to canonical product' "$DISCOVERY" || fail "Canonical product dropdown missing."
grep -Fq 'Leave awaiting review' "$DISCOVERY" || fail "Review decision missing."
grep -Fq 'Not a Sustainable Catalyst product' "$DISCOVERY" || fail "Ignore decision missing."
grep -Fq 'Save mapping decision' "$DISCOVERY" || fail "Mapping action missing."
grep -Fq 'Remove manual mapping' "$DISCOVERY" || fail "Mapping removal missing."
grep -Fq 'Restore to review' "$DISCOVERY" || fail "Ignored candidate restore missing."
grep -Fq "'administrator_mapping'" "$DISCOVERY" || fail "Administrator mapping precedence missing."
grep -Fq 'scfs_mapping_alias_collision' "$DISCOVERY" || fail "Alias collision guard missing."
grep -Fq 'detach_duplicate_identifiers' "$DISCOVERY" || fail "Duplicate reassignment missing."
grep -Fq 'target_before' "$DISCOVERY" || fail "Identifier rollback snapshot missing."
grep -Fq 'plugin_mapped_to_canonical_product' "$DISCOVERY" || fail "Mapping audit event missing."
grep -Fq '/product-registry/discovery/decision' "$DISCOVERY" || fail "Authenticated mapping route missing."
grep -Fq 'public function decision_action' "$DISCOVERY" || fail "Non-JavaScript mapping fallback missing."
grep -Fq 'private function fragment_payload' "$DISCOVERY" || fail "Live fragment response missing."
grep -Fq 'No plugins awaiting review' "$DISCOVERY" || fail "Zero state missing."
grep -Fq 'aria-live="polite"' "$DISCOVERY" || fail "Accessible live status missing."
grep -Fq 'window.fetch' "$MAPPING_JS" || fail "REST enhancement missing."
grep -Fq 'X-WP-Nonce' "$MAPPING_JS" || fail "REST nonce missing."
grep -Fq "document.addEventListener('submit'" "$MAPPING_JS" || fail "Progressive event delegation missing."
grep -Fq 'prefers-reduced-motion' "$MAPPING_CSS" || fail "Reduced-motion mapping style missing."
grep -Fq "['previous_version'] = '7.5.1';" "$REGISTRY" || fail "Registry release lineage missing."
printf 'PASS - governed dropdown, alias persistence, collision protection, reversible review, REST, and accessibility contracts\n'

printf '==> Legacy Release Console and shortcode contracts\n'
BOARD="$PLUGIN/includes/class-scfs-release-board.php"
BOARD_CSS="$PLUGIN/assets/release-board-v7.5.2.css"
BOARD_JS="$PLUGIN/assets/release-console-v7.5.2.js"
grep -Fq "const SHORTCODE = 'sc_release_board';" "$BOARD" || fail "Legacy release board shortcode missing."
for layout in terminal blackboard compact directory; do grep -Fq "'$layout'" "$BOARD" || fail "Legacy layout missing: $layout"; done
grep -Fq -- '--scfs-release-console-columns:' "$BOARD_CSS" || fail "Shared Release Console grid missing."
grep -Fq 'grid-template-columns: var(--scfs-release-console-columns);' "$BOARD_CSS" || fail "Release Console alignment contract missing."
grep -Fq 'MutationObserver' "$BOARD_JS" || fail "Dynamic console initialization missing."
grep -Fq 'prefers-reduced-motion' "$BOARD_CSS" || fail "Release Console reduced-motion behavior missing."
printf 'PASS - inherited Release Console, shortcode, layout, and accessibility contracts\n'

printf '==> Backend test suite\n'
backend_output="$(cd "$ROOT/backend" && PYTHONPATH=. "$PYTHON_BIN" -m pytest -q)" || { printf '%s\n' "$backend_output" >&2; fail "Backend tests failed."; }
printf '%s\n' "$backend_output"
backend_passed="$(printf '%s\n' "$backend_output" | sed -nE 's/^([0-9]+) passed.*/\1/p' | tail -1)"
[ "$backend_passed" = "318" ] || fail "Expected 318 backend tests, got ${backend_passed:-unknown}."
printf 'PASS - 318 backend tests\n'

printf '==> Bash compatibility\n'
if grep -Eq '(^|[[:space:]])(mapfile|readarray)([[:space:]]|$)' "$ROOT/validate_v7_5_2.sh"; then fail "Validator requires Bash 4."; fi
bash -n "$ROOT/validate_v7_5_2.sh" || fail "Validator shell syntax failed."
printf 'PASS - Bash 3.2-compatible validator\n'

printf 'VALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
printf 'COUNTS: php_contracts=%s php_files=%s javascript_files=%s json_files=%s python_files=%s css_files=%s backend_tests=%s\n' "$contract_count" "$php_count" "$js_count" "$json_count" "$python_count" "$css_count" "$backend_passed"
