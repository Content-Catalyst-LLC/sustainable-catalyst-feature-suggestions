#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="7.5.0"
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
contract_log="$(mktemp "${TMPDIR:-/tmp}/scpsf-v750-contract.XXXXXX")"
while IFS= read -r contract_file; do
  if ! TERM=dumb php "$contract_file" >"$contract_log" 2>&1; then
    cat "$contract_log" >&2
    rm -f "$contract_log"
    fail "PHP contract failed: ${contract_file#$ROOT/}"
  fi
done < <(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | LC_ALL=C sort)
rm -f "$contract_log"
printf 'PASS - %d PHP contract files executed sequentially\n' "$contract_count"

printf '==> Runtime identity and compatibility\n'
php -r '$p=file_get_contents($argv[1]); if(!preg_match("/Plugin Name:[[:space:]]*Sustainable Catalyst Product Support and Feedback Platform/",$p)||!preg_match("/Version:[[:space:]]*7\\.5\\.0/",$p)||!preg_match("/const VERSION = '\''7\\.5\\.0'\'';/",$p)){exit(1);}' "$PLUGIN/sustainable-catalyst-feature-suggestions.php" || fail "Plugin identity mismatch."
[ ! -d "$ROOT/wordpress/sustainable-catalyst-product-support-feedback" ] || fail "Legacy WordPress directory was renamed."
printf 'PASS - v%s runtime with legacy plugin slug preserved\n' "$VERSION"

printf '==> JavaScript syntax\n'
js_count="$(find "$PLUGIN" -type f -name '*.js' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.js' -print0 | xargs -0 -P 8 -n 1 node --check >/dev/null || fail "JavaScript syntax failed."
printf 'PASS - %d JavaScript files\n' "$js_count"

printf '==> JSON and Python syntax\n'
read -r json_count python_count < <("$PYTHON_BIN" - "$ROOT" <<'PY'
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
printf 'PASS - %d JSON files and %d Python files\n' "$json_count" "$python_count"

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
printf 'PASS - %d balanced CSS layers\n' "$css_count"

printf '==> Release Intelligence and copy-control manifests\n'
"$PYTHON_BIN" - "$ROOT" <<'PY'
from pathlib import Path
import json,sys
r=Path(sys.argv[1])
current=json.loads((r/'feature_suggestions_manifest.json').read_text())
versioned=json.loads((r/'feature_suggestions_manifest-v7.5.0.json').read_text())
release=json.loads((r/'release-manifest-v7.5.0.json').read_text())
board_schema=json.loads((r/'schemas/scfs-release-board-v1.schema.json').read_text())
registry_schema=json.loads((r/'schemas/scfs-canonical-product-registry-v2.schema.json').read_text())
example=json.loads((r/'examples/release-board-v7.5.0.json').read_text())
assert current == versioned
assert current['version'] == '7.5.0'
assert current['release_name'] == 'Release Intelligence and Console Copy Controls'
assert current['repository'] == 'Content-Catalyst-LLC/sustainable-catalyst-product-support-feedback'
board=current['release_board']
assert board['schema'] == 'scfs-release-board/1.3'
assert board['shortcode'] == 'sc_release_board'
assert board['layouts'] == ['terminal','blackboard','compact','directory']
assert board['default_interval_seconds'] == 7
assert board['product_labels_navigating'] is False
assert board['footer_links_only'] is True
for key in ('release_intelligence','previous_version_comparison','release_date_display','change_summaries','validation_indicators','documentation_indicators','known_issue_counts','recently_updated_indicator','maintenance_and_superseded_indicators','copy_controls','shortcode_copy_overrides'):
    assert board[key] is True,key
assert board['registry_facts_overridable_by_copy'] is False
registry=current['canonical_product_registry']
assert registry['schema'] == 'scfs-canonical-product-registry/2.0'
for key in ('release_intelligence_governed','previous_version_comparison','release_date_governed','change_summary_governed','validation_state_governed','documentation_state_governed','known_issue_count_governed'):
    assert registry[key] is True,key
copy=current['release_console_copy']
assert copy['schema'] == 'scfs-release-console-copy/1.0'
assert copy['option_key'] == 'scfs_release_console_copy'
assert copy['admin_screen'] is True
assert copy['wordpress_settings'] is True
assert copy['shortcode_overrides'] is True
assert copy['registry_authority_preserved'] is True
assert copy['product_facts_editable_here'] is False
assert release['version'] == '7.5.0'
assert release['release_name'] == 'Release Intelligence and Console Copy Controls'
assert board_schema['properties']['version']['const'] == '7.5.0'
assert registry_schema['properties']['version']['const'] == '7.5.0'
assert example['schema'] == 'scfs-release-board/1.3'
assert example['version'] == '7.5.0'
print('PASS - release intelligence, copy controls, registry authority, accessibility, and compatibility validated')
PY

printf '==> Source contracts\n'
BOARD="$PLUGIN/includes/class-scfs-release-board.php"
REGISTRY="$PLUGIN/includes/class-scfs-canonical-product-registry.php"
COPY="$PLUGIN/includes/class-scfs-release-console-copy.php"
CSS="$PLUGIN/assets/release-board-v7.5.0.css"
JS="$PLUGIN/assets/release-console-v7.5.0.js"
for f in "$BOARD" "$REGISTRY" "$COPY" "$CSS" "$JS"; do [ -s "$f" ] || fail "Missing ${f#$ROOT/}."; done
grep -Fq "const VERSION = '7.5.0';" "$BOARD" || fail "Release board version mismatch."
grep -Fq "const SCHEMA = 'scfs-release-board/1.3';" "$BOARD" || fail "Release board schema mismatch."
grep -Fq "const OPTION_KEY = 'scfs_release_console_copy';" "$COPY" || fail "Copy option missing."
grep -Fq "apply_filters('scfs_release_console_copy'" "$COPY" || fail "Copy filter missing."
grep -Fq "do_action('scfs_release_console_copy_updated'" "$COPY" || fail "Copy invalidation hook missing."
grep -Fq "scfs_release_console_product_intelligence" "$BOARD" || fail "Product intelligence filter missing."
for field in previous_version release_date change_summary validation_state documentation_state known_issue_count; do grep -Fq "'$field'" "$REGISTRY" || fail "Registry field missing: $field"; done
grep -Fq "apply_v750_release_intelligence_migrations" "$REGISTRY" || fail "v7.5.0 migration missing."
grep -Fq "data-console-pause-label" "$BOARD" || fail "Configurable pause label missing."
grep -Fq "data-console-play-label" "$BOARD" || fail "Configurable play label missing."
grep -Fq "MutationObserver" "$JS" || fail "Dynamic initialization missing."
grep -Fq "prefers-reduced-motion" "$CSS" || fail "Reduced-motion CSS missing."
printf 'PASS - WordPress source contracts\n'

printf '==> Backend test suite\n'
backend_output="$(cd "$ROOT/backend" && "$PYTHON_BIN" -m pytest -q)" || { printf '%s\n' "$backend_output" >&2; fail "Backend tests failed."; }
printf '%s\n' "$backend_output"
backend_passed="$(printf '%s\n' "$backend_output" | sed -nE 's/^([0-9]+) passed.*/\1/p' | tail -1)"
[ "$backend_passed" = "316" ] || fail "Expected 316 backend tests, got ${backend_passed:-unknown}."
printf 'PASS - 316 backend tests\n'

printf '==> Bash compatibility\n'
if grep -Eq '(^|[[:space:]])(mapfile|readarray)([[:space:]]|$)' "$ROOT/validate_v7_5_0.sh"; then fail "Validator requires Bash 4."; fi
bash -n "$ROOT/validate_v7_5_0.sh" || fail "Validator shell syntax failed."
printf 'PASS - Bash 3.2-compatible validator\n'

printf 'VALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
printf 'COUNTS: php_contracts=%s php_files=%s javascript_files=%s json_files=%s python_files=%s css_files=%s backend_tests=%s\n' "$contract_count" "$php_count" "$js_count" "$json_count" "$python_count" "$css_count" "$backend_passed"
