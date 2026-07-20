#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="6.2.0"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$ROOT_DIR/wordpress/sustainable-catalyst-feature-suggestions"
PYTHON_BIN="${PYTHON_BIN:-python3}"

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
command -v php >/dev/null 2>&1 || fail "PHP CLI is required."
command -v "$PYTHON_BIN" >/dev/null 2>&1 || fail "Python is required."

printf '==> Product Support and Feedback Platform v%s validation\n' "$VERSION"

MAIN="$PLUGIN_DIR/sustainable-catalyst-feature-suggestions.php"
FOUNDATION="$PLUGIN_DIR/includes/class-scfs-help-desk-case-foundation.php"
WORKSPACE="$PLUGIN_DIR/includes/class-scfs-help-desk-agent-workspace.php"
WORKSPACE_CSS="$PLUGIN_DIR/assets/help-desk-agent-workspace.css"
WORKSPACE_JS="$PLUGIN_DIR/assets/help-desk-agent-workspace.js"
MANIFEST="$ROOT_DIR/feature_suggestions_manifest.json"
RELEASE_MANIFEST="$ROOT_DIR/release-manifest-v6.2.0.json"
SCHEMA="$ROOT_DIR/schemas/scfs-help-desk-agent-workspace-v1.schema.json"
EXAMPLE="$ROOT_DIR/examples/help-desk-agent-workspace-v6.2.0.json"

for path in \
  "$MAIN" "$FOUNDATION" "$WORKSPACE" "$WORKSPACE_CSS" "$WORKSPACE_JS" \
  "$MANIFEST" "$RELEASE_MANIFEST" "$SCHEMA" "$EXAMPLE" \
  "$ROOT_DIR/backend/app/help_desk_agent_workspace.py" \
  "$ROOT_DIR/backend/tests/test_help_desk_agent_workspace.py" \
  "$ROOT_DIR/backend/requirements-validation.txt" \
  "$ROOT_DIR/docs/help-desk-agent-workspace-v6.2.0.md" \
  "$ROOT_DIR/install_and_push_v6_2_0_macos.sh"; do
  [ -f "$path" ] || fail "Missing required release file: ${path#$ROOT_DIR/}"
done

grep -Fq 'Version: 6.2.0' "$MAIN" || fail "Plugin version header mismatch."
grep -Fq "const VERSION = '6.2.0';" "$MAIN" || fail "Runtime version mismatch."
grep -Fq "const POST_TYPE = 'sc_feature_suggest';" "$MAIN" || fail "Legacy suggestion post type changed."
grep -Fq "const REST_NAMESPACE = 'scfs/v1';" "$MAIN" || fail "Legacy REST namespace changed."
grep -Fq 'final class SCFS_Help_Desk_Agent_Workspace' "$WORKSPACE" || fail "Agent Workspace class missing."
grep -Fq "const SCHEMA = 'scfs-help-desk-agent-workspace/1.0';" "$WORKSPACE" || fail "Agent Workspace schema mismatch."
grep -Fq "'saved_views' => \$wpdb->prefix . 'scfs_help_desk_saved_views'" "$WORKSPACE" || fail "Saved views table missing."
grep -Fq "'teams' => \$wpdb->prefix . 'scfs_help_desk_teams'" "$WORKSPACE" || fail "Teams table missing."
grep -Fq "'team_members' => \$wpdb->prefix . 'scfs_help_desk_team_members'" "$WORKSPACE" || fail "Team members table missing."
grep -Fq "'public_workspace_api' => false" "$WORKSPACE" || fail "Public workspace API privacy boundary missing."
grep -Fq "'automatic_assignment' => false" "$WORKSPACE" || fail "Automatic assignment boundary missing."

printf '==> PHP syntax\n'
php_count=0
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
  php_count=$((php_count + 1))
done < <(find "$PLUGIN_DIR" -type f -name '*.php' -print0)
printf 'PASS - %d plugin PHP files\n' "$php_count"

printf '==> WordPress contract suite\n'
test_count=0
while IFS= read -r file; do
  php "$file" >/dev/null || fail "WordPress contract failed: ${file#$ROOT_DIR/}"
  test_count=$((test_count + 1))
done < <(find "$ROOT_DIR/tests" -maxdepth 1 -type f -name 'test-*.php' | sort)
printf 'PASS - %d PHP contract files\n' "$test_count"

printf '==> JavaScript syntax\n'
js_count=0
if command -v node >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    node --check "$file" >/dev/null
    js_count=$((js_count + 1))
  done < <(find "$PLUGIN_DIR/assets" -type f -name '*.js' -print0)
  printf 'PASS - %d JavaScript files\n' "$js_count"
else
  printf 'SKIP - Node.js unavailable.\n'
fi

printf '==> JSON and Python syntax\n'
"$PYTHON_BIN" - "$ROOT_DIR" <<'PYVALID'
import ast
import json
import sys
from pathlib import Path
root = Path(sys.argv[1])
excluded = {'.git', '.venv', 'venv', '__pycache__', '.pytest_cache'}
json_files = [p for p in root.rglob('*.json') if not any(x in excluded for x in p.parts)]
for path in json_files:
    json.loads(path.read_text())
py_files = [p for p in root.rglob('*.py') if not any(x in excluded for x in p.parts)]
for path in py_files:
    ast.parse(path.read_text(), filename=str(path))
print(f'PASS - {len(json_files)} JSON files and {len(py_files)} Python files')
PYVALID

printf '==> Source whitespace compatibility\n'
"$PYTHON_BIN" - "$ROOT_DIR" <<'PYSPACE'
import sys
from pathlib import Path
root = Path(sys.argv[1])
extensions = {'.css','.csv','.html','.ini','.js','.json','.md','.php','.py','.sh','.txt','.xml','.yaml','.yml'}
excluded = {'.git','.pytest_cache','.venv','__pycache__','venv'}
bad = []
blank_eof = []
for path in root.rglob('*'):
    if not path.is_file() or path.suffix.lower() not in extensions or any(x in excluded for x in path.parts):
        continue
    try:
        text = path.read_text()
    except UnicodeDecodeError:
        continue
    lines = text.splitlines()
    for number, line in enumerate(lines, 1):
        if line.endswith((' ', '\t')):
            bad.append(f'{path.relative_to(root)}:{number}')
    if text.endswith('\n\n'):
        blank_eof.append(str(path.relative_to(root)))
if bad:
    raise SystemExit('Trailing whitespace detected:\n' + '\n'.join(bad))
if blank_eof:
    raise SystemExit('Blank line at end of file detected:\n' + '\n'.join(blank_eof))
print('PASS - source tree contains no trailing whitespace or blank EOF lines')
PYSPACE

printf '==> CSS structural validation\n'
"$PYTHON_BIN" - "$PLUGIN_DIR/assets" <<'PYCSS'
import re
import sys
from pathlib import Path
assets = Path(sys.argv[1])
css_files = sorted(path for path in assets.glob('*.css') if path.is_file())
if not css_files:
    raise SystemExit('No CSS layers found')
for path in css_files:
    text = path.read_text()
    clean = re.sub(r'/\*.*?\*/', '', text, flags=re.S)
    level = 0
    for index, character in enumerate(clean):
        if character == '{':
            level += 1
        elif character == '}':
            level -= 1
            if level < 0:
                raise SystemExit(f'{path.name} closes early at {index}')
    if level:
        raise SystemExit(f'{path.name} imbalance {level}')
    if '</style>' in text.lower():
        raise SystemExit(f'{path.name} contains forbidden style tag')
print(f'PASS - {len(css_files)} balanced CSS layers')
PYCSS

printf '==> FastAPI backend tests\n'
if ! "$PYTHON_BIN" -c 'import pytest' >/dev/null 2>&1; then
  printf 'ERROR: pytest is not installed for validation interpreter: %s\n' "$PYTHON_BIN" >&2
  printf 'Install backend/requirements-validation.txt before running this validation suite.\n' >&2
  exit 1
fi
PYTHONPATH="$ROOT_DIR/backend" "$PYTHON_BIN" -m pytest "$ROOT_DIR/backend/tests" -q

printf '==> Release manifests\n'
"$PYTHON_BIN" - "$MANIFEST" "$RELEASE_MANIFEST" "$SCHEMA" "$EXAMPLE" <<'PYMANIFEST'
import json
import sys
manifest = json.load(open(sys.argv[1]))
release = json.load(open(sys.argv[2]))
schema = json.load(open(sys.argv[3]))
example = json.load(open(sys.argv[4]))
assert manifest['name'] == 'Sustainable Catalyst Product Support and Feedback Platform'
assert manifest['slug'] == 'sustainable-catalyst-feature-suggestions'
assert manifest['version'] == '6.2.0'
assert manifest['release_name'] == 'Agent Workspace, Queues, and Assignment'
assert manifest['compatibility']['rest_namespace'] == 'scfs/v1'
workspace = manifest['help_desk_agent_workspace']
assert workspace['schema'] == 'scfs-help-desk-agent-workspace/1.0'
assert workspace['db_version'] == '1.1.0'
assert len(workspace['additive_tables']) == 3
for key in ('private_agent_workspace','built_in_queues','dynamic_team_queues','private_case_search','saved_views','workload_summary','assignment_history','bulk_actions','internal_notes','requester_visible_replies','authenticated_rest_api','wp_cli','human_review_required'):
    assert workspace[key] is True, key
for key in ('public_workspace_api','public_workspace_shortcode','private_case_content_exposed','automatic_assignment','automatic_reassignment'):
    assert workspace[key] is False, key
assert workspace['identity_authority'] == 'contact-engagement'
assert workspace['attachment_authority'] == 'contact-engagement'
assert release['version'] == '6.2.0'
assert release['support_article_permalink_base'] == '/support/guides/'
assert release['compatibility']['rest_namespace'] == 'scfs/v1'
assert release['compatibility']['existing_public_data_migration_required'] is False
assert release['compatibility']['existing_private_case_data_migration_required'] is False
assert release['compatibility']['additive_workspace_schema_activation_required'] is True
assert schema['properties']['version']['const'] == '6.2.0'
assert schema['properties']['schema']['const'] == 'scfs-help-desk-agent-workspace/1.0'
assert example['schema'] == 'scfs-help-desk-agent-workspace/1.0'
assert example['public_workspace_api'] is False
assert example['automatic_assignment'] is False
print('PASS - release identity, compatibility, workspace schema, privacy, and governance fields')
PYMANIFEST

printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
