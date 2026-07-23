#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="7.5.1"
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
contract_log="$(mktemp "${TMPDIR:-/tmp}/scpsf-v751-contract.XXXXXX")"
while IFS= read -r contract_file; do
  if ! TERM=dumb php "$contract_file" >"$contract_log" 2>&1; then
    cat "$contract_log" >&2
    rm -f "$contract_log"
    fail "PHP contract failed: ${contract_file#$ROOT/}"
  fi
done < <(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | LC_ALL=C sort)
rm -f "$contract_log"
[ "$contract_count" = "269" ] || fail "Expected 269 PHP contracts, got $contract_count."
printf 'PASS - %d PHP contract files executed sequentially\n' "$contract_count"

printf '==> Runtime identity and compatibility\n'
php -r '$p=file_get_contents($argv[1]); if(!preg_match("/Plugin Name:[[:space:]]*Sustainable Catalyst Product Support and Feedback Platform/",$p)||!preg_match("/Version:[[:space:]]*7\\.5\\.1/",$p)||!preg_match("/const VERSION = '\''7\\.5\\.1'\'';/",$p)){exit(1);}' "$PLUGIN/sustainable-catalyst-feature-suggestions.php" || fail "Plugin identity mismatch."
[ ! -d "$ROOT/wordpress/sustainable-catalyst-product-support-feedback" ] || fail "Legacy WordPress directory was renamed."
printf 'PASS - v%s runtime with legacy plugin slug preserved\n' "$VERSION"

printf '==> JavaScript syntax\n'
js_count="$(find "$PLUGIN" -type f -name '*.js' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.js' -print0 | xargs -0 -P 8 -n 1 node --check >/dev/null || fail "JavaScript syntax failed."
printf 'PASS - %d JavaScript files\n' "$js_count"

printf '==> JSON and Python syntax\n'
read -r json_count python_count <<EOF
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
EOF
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

printf '==> v7.5.1 release manifests and examples\n'
"$PYTHON_BIN" - "$ROOT" <<'PY'
from pathlib import Path
import json,sys
r=Path(sys.argv[1])
current=json.loads((r/'feature_suggestions_manifest.json').read_text())
versioned=json.loads((r/'feature_suggestions_manifest-v7.5.1.json').read_text())
release=json.loads((r/'release-manifest-v7.5.1.json').read_text())
board_schema=json.loads((r/'schemas/scfs-release-board-v1.schema.json').read_text())
registry_schema=json.loads((r/'schemas/scfs-canonical-product-registry-v2.schema.json').read_text())
console_example=json.loads((r/'examples/release-console-v7.5.1.json').read_text())
discovery_example=json.loads((r/'examples/plugin-discovery-status-v7.5.1.json').read_text())
name='Release Console Alignment and Plugin Discovery Status Repair'
assert current == versioned
assert current['version'] == '7.5.1'
assert current['release_name'] == name
assert current['repository'] == 'Content-Catalyst-LLC/sustainable-catalyst-product-support-feedback'
board=current['release_board']
assert board['schema'] == 'scfs-release-board/1.3'
assert board['shortcode'] == 'sc_release_board'
assert board['layouts'] == ['terminal','blackboard','compact','directory']
assert board['default_interval_seconds'] == 7
assert board['product_labels_navigating'] is False
assert board['footer_links_only'] is True
for key in ('shared_responsive_column_grid','heading_value_alignment','release_intelligence_beneath_product_names','footer_spacing_tightened','state_source_optional_alignment'):
    assert board[key] is True,key
discovery=current['installed_plugin_discovery']
for key in ('pending_heading_requires_unmatched_candidates','actionable_candidate_rows','duplicate_mapping_review_separated','stale_status_reconciled_after_rescan','rescan_response_includes_pending_queue'):
    assert discovery[key] is True,key
assert discovery['zero_state_message'] == 'No plugins awaiting review'
assert release['version'] == '7.5.1'
assert release['release_name'] == name
assert board_schema['properties']['version']['const'] == '7.5.1'
assert registry_schema['properties']['version']['const'] == '7.5.1'
assert console_example['version'] == '7.5.1'
assert discovery_example['version'] == '7.5.1'
print('PASS - release identity, console alignment, discovery status repair, and compatibility metadata validated')
PY

printf '==> Source contracts\n'
BOARD="$PLUGIN/includes/class-scfs-release-board.php"
REGISTRY="$PLUGIN/includes/class-scfs-canonical-product-registry.php"
DISCOVERY="$PLUGIN/includes/class-scfs-installed-plugin-discovery.php"
CSS="$PLUGIN/assets/release-board-v7.5.1.css"
JS="$PLUGIN/assets/release-console-v7.5.1.js"
for f in "$BOARD" "$REGISTRY" "$DISCOVERY" "$CSS" "$JS"; do [ -s "$f" ] || fail "Missing ${f#$ROOT/}."; done
grep -Fq "const VERSION = '7.5.1';" "$BOARD" || fail "Release board version mismatch."
grep -Fq -- '--scfs-release-console-columns:' "$CSS" || fail "Shared console grid variable missing."
grep -Fq '.scfs-release-board .scfs-release-board__column-labels,' "$CSS" || fail "Column labels are not in the shared grid rule."
grep -Fq '.scfs-release-board .scfs-release-board__product-line {' "$CSS" || fail "Product values are not in the shared grid rule."
grep -Fq 'grid-template-columns: var(--scfs-release-console-columns);' "$CSS" || fail "Headings and values do not share the grid contract."
grep -Fq 'scfs-release-board__product-identity' "$BOARD" || fail "Product identity wrapper missing."
grep -Fq 'scfs-release-board__intelligence' "$BOARD" || fail "Release intelligence rendering missing."
grep -Fq 'grid-template-areas:' "$CSS" || fail "Compact footer grid missing."
grep -Fq 'No plugins awaiting review' "$DISCOVERY" || fail "Discovery zero state missing."
grep -Fq 'if ($unmatched)' "$DISCOVERY" || fail "Pending heading is not conditioned on unmatched candidates."
grep -Fq 'Duplicate mapping review' "$DISCOVERY" || fail "Duplicate review section missing."
grep -Fq 'render_candidate_table' "$DISCOVERY" || fail "Actionable candidate rows missing."
grep -Fq "\$record['discovered_plugin_version'] = '';" "$DISCOVERY" || fail "Stale discovery version is not cleared."
grep -Fq "'pending' => \$this->pending_candidates()" "$DISCOVERY" || fail "Rescan response does not return the live pending queue."
grep -Fq 'id="scfs-product-' "$REGISTRY" || fail "Product registry action anchors missing."
grep -Fq "const SHORTCODE = 'sc_release_board';" "$BOARD" || fail "Legacy release board shortcode missing."
for layout in terminal blackboard compact directory; do grep -Fq "'$layout'" "$BOARD" || fail "Legacy layout missing: $layout"; done
grep -Fq 'data-console-pause-label' "$BOARD" || fail "Pause accessibility label missing."
grep -Fq 'data-console-play-label' "$BOARD" || fail "Play accessibility label missing."
grep -Fq 'MutationObserver' "$JS" || fail "Dynamic initialization missing."
grep -Fq 'prefers-reduced-motion' "$CSS" || fail "Reduced-motion behavior missing."
printf 'PASS - Release Console, Plugin Discovery, legacy shortcode, and accessibility source contracts\n'

printf '==> Backend test suite\n'
backend_output="$(cd "$ROOT/backend" && PYTHONPATH=. "$PYTHON_BIN" -m pytest -q)" || { printf '%s\n' "$backend_output" >&2; fail "Backend tests failed."; }
printf '%s\n' "$backend_output"
backend_passed="$(printf '%s\n' "$backend_output" | sed -nE 's/^([0-9]+) passed.*/\1/p' | tail -1)"
[ "$backend_passed" = "316" ] || fail "Expected 316 backend tests, got ${backend_passed:-unknown}."
printf 'PASS - 316 backend tests\n'

printf '==> Bash compatibility\n'
if grep -Eq '(^|[[:space:]])(mapfile|readarray)([[:space:]]|$)' "$ROOT/validate_v7_5_1.sh"; then fail "Validator requires Bash 4."; fi
bash -n "$ROOT/validate_v7_5_1.sh" || fail "Validator shell syntax failed."
printf 'PASS - Bash 3.2-compatible validator\n'

printf 'VALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
printf 'COUNTS: php_contracts=%s php_files=%s javascript_files=%s json_files=%s python_files=%s css_files=%s backend_tests=%s\n' "$contract_count" "$php_count" "$js_count" "$json_count" "$python_count" "$css_count" "$backend_passed"
