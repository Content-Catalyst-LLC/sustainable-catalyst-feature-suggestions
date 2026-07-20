#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="6.6.0"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN="$ROOT/wordpress/sustainable-catalyst-feature-suggestions"
PYTHON_BIN="${PYTHON_BIN:-python3}"
CONTRACT_EXECUTION_MODE="sequential-state-safe"
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
printf '==> Product Support and Feedback Platform v%s validation\n' "$VERSION"
printf '==> PHP syntax\n'
php_count="$(find "$PLUGIN" -type f -name '*.php' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.php' -print0 | xargs -0 -P 8 -n 1 php -l >/dev/null || fail "PHP syntax failed."
printf 'PASS - %d plugin PHP files\n' "$php_count"
printf '==> WordPress contract suite (%s)\n' "$CONTRACT_EXECUTION_MODE"
contract_count="$(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | wc -l | tr -d ' ')"
contract_log="$(mktemp "${TMPDIR:-/tmp}/scfs-v660-contract.XXXXXX")"
while IFS= read -r contract_file; do
  if ! php "$contract_file" >"$contract_log" 2>&1; then
    cat "$contract_log" >&2
    rm -f "$contract_log"
    fail "PHP contract failed: ${contract_file#$ROOT/}"
  fi
done < <(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | LC_ALL=C sort)
rm -f "$contract_log"
printf 'PASS - %d PHP contract files executed sequentially\n' "$contract_count"
printf '==> Post-contract release identity integrity\n'
php -r '
$plugin = file_get_contents($argv[1]);
if (!preg_match("/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*6\.6\.0[[:space:]]*$/m", $plugin)) { fwrite(STDERR, "Plugin header changed during contract execution.\n"); exit(1); }
if (!preg_match("/const VERSION = '"'"'6\.6\.0'"'"';/", $plugin)) { fwrite(STDERR, "Runtime version changed during contract execution.\n"); exit(1); }
' "$PLUGIN/sustainable-catalyst-feature-suggestions.php" || fail "Post-contract plugin identity check failed."
"$PYTHON_BIN" - "$ROOT/feature_suggestions_manifest.json" <<'PYIDENTITY'
from pathlib import Path
import json, sys
manifest = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'))
assert manifest.get('version') == '6.6.0', 'Feature manifest version changed during contract execution.'
print('PASS - plugin header, runtime version, and feature manifest remain v6.6.0')
PYIDENTITY
printf '==> JavaScript syntax\n'
js_count="$(find "$PLUGIN" -type f -name '*.js' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.js' -print0 | xargs -0 -P 8 -n 1 node --check >/dev/null || fail "JavaScript syntax failed."
printf 'PASS - %d JavaScript files\n' "$js_count"
printf '==> JSON and Python syntax\n'
read -r json_count python_count < <("$PYTHON_BIN" - "$ROOT" <<'PYSYNTAX'
from pathlib import Path
import json, py_compile, sys
root=Path(sys.argv[1]); jc=pc=0
for path in root.rglob('*.json'):
    if any(part in {'.git','.venv','venv','__pycache__'} for part in path.parts): continue
    json.loads(path.read_text(encoding='utf-8')); jc+=1
for path in (root/'backend').rglob('*.py'):
    if '__pycache__' in path.parts: continue
    py_compile.compile(str(path),doraise=True); pc+=1
print(jc,pc)
PYSYNTAX
)
printf 'PASS - %d JSON files and %d Python files\n' "$json_count" "$python_count"
printf '==> Source whitespace compatibility\n'
"$PYTHON_BIN" - "$ROOT" <<'PYSPACE'
from pathlib import Path
import sys
root=Path(sys.argv[1]); failures=[]; exts={'.php','.py','.js','.css','.json','.md','.txt','.sh','.yml','.yaml','.html','.csv'}
for p in root.rglob('*'):
    if not p.is_file() or any(part in {'.git','.venv','venv','__pycache__'} for part in p.parts) or p.suffix.lower() not in exts: continue
    try: text=p.read_text(encoding='utf-8')
    except UnicodeDecodeError: continue
    for i,line in enumerate(text.splitlines(),1):
        if line.rstrip(' \t')!=line: failures.append(f'{p.relative_to(root)}:{i}: trailing whitespace')
    if text.endswith('\n\n'): failures.append(f'{p.relative_to(root)}: blank EOF lines')
if failures: print('\n'.join(failures)); raise SystemExit(1)
PYSPACE
printf 'PASS - source tree contains no trailing whitespace or blank EOF lines\n'
printf '==> CSS structural validation\n'
css_count="$("$PYTHON_BIN" - "$PLUGIN" <<'PYCSS'
from pathlib import Path
import sys
files=sorted(Path(sys.argv[1]).rglob('*.css'))
for p in files:
 t=p.read_text(); assert t.count('{')==t.count('}'),f'Unbalanced CSS: {p}'
print(len(files))
PYCSS
)"
printf 'PASS - %d balanced CSS layers\n' "$css_count"
printf '==> FastAPI backend tests\n'
(cd "$ROOT/backend" && "$PYTHON_BIN" -m pytest -q)
printf '==> Release manifests\n'
"$PYTHON_BIN" - "$ROOT" <<'PYMANIFEST'
from pathlib import Path
import json,sys
r=Path(sys.argv[1]); feature=json.loads((r/'feature_suggestions_manifest-v6.6.0.json').read_text()); release=json.loads((r/'release-manifest-v6.6.0.json').read_text()); schema=json.loads((r/'schemas/scfs-help-desk-knowledge-resolution-v1.schema.json').read_text()); example=json.loads((r/'examples/help-desk-knowledge-resolution-v6.6.0.json').read_text()); resolution=feature['help_desk_knowledge_resolution']
assert feature['version']=='6.6.0'; assert release['version']=='6.6.0'; assert release['release_name']=='Knowledge-Assisted Case Resolution'; assert resolution['schema']=='scfs-help-desk-knowledge-resolution/1.0'; assert resolution['db_version']=='1.5.0'
for key in ('support_article_matching','known_issue_matching','release_matching','privacy_safe_similar_case_matching','duplicate_case_review','guided_resolution_planning','agent_decision_governance','customer_approved_guidance','documentation_promotion_requests','append_only_resolution_actions','human_review_required'): assert resolution[key] is True,key
for key in ('private_message_content_persisted','requester_identity_persisted','similar_case_message_bodies_exposed','automatic_customer_send','automatic_duplicate_merge','automatic_case_resolution','automatic_publication'): assert resolution[key] is False,key
assert release['compatibility']['existing_knowledge_resolution_data_migration_required'] is False; assert release['compatibility']['additive_knowledge_resolution_schema_activation_required'] is True; assert schema['properties']['version']['const']=='6.6.0'; assert schema['properties']['schema']['const']=='scfs-help-desk-knowledge-resolution/1.0'; assert example['privacy']['private_message_content_persisted'] is False; assert example['governance']['automatic_publication'] is False
print('PASS - release identity, compatibility, knowledge-resolution schema, privacy, and governance fields')
PYMANIFEST
printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
