#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="5.4.0"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$ROOT_DIR/wordpress/sustainable-catalyst-feature-suggestions"
PYTHON_BIN="${PYTHON_BIN:-python3}"

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
command -v php >/dev/null 2>&1 || fail "PHP CLI is required."
command -v "$PYTHON_BIN" >/dev/null 2>&1 || fail "Python is required."

printf '==> Product Support and Feedback Platform v%s validation\n' "$VERSION"

MAIN="$PLUGIN_DIR/sustainable-catalyst-feature-suggestions.php"
KB="$PLUGIN_DIR/includes/class-scfs-knowledge-base.php"
SUPPORT="$PLUGIN_DIR/includes/class-scfs-product-support-platform.php"
UNIFIED="$PLUGIN_DIR/includes/class-scfs-unified-support-search.php"
INTELLIGENCE="$PLUGIN_DIR/includes/class-scfs-known-issue-release-intelligence.php"
INTELLIGENCE_CSS="$PLUGIN_DIR/assets/issue-release-intelligence.css"
UNIFIED_CSS="$PLUGIN_DIR/assets/unified-support-search.css"
KB_CSS="$PLUGIN_DIR/assets/knowledge-base.css"
SUPPORT_CSS="$PLUGIN_DIR/assets/product-support-platform.css"
INTEGRITY_CSS="$PLUGIN_DIR/assets/support-article-integrity.css"
MANIFEST="$ROOT_DIR/feature_suggestions_manifest.json"
RELEASE_MANIFEST="$ROOT_DIR/release-manifest-v5.4.0.json"
BACKEND="$ROOT_DIR/backend/app/issue_release_intelligence.py"
BACKEND_TEST="$ROOT_DIR/backend/tests/test_issue_release_intelligence.py"
SCHEMA="$ROOT_DIR/schemas/scfs-known-issue-release-intelligence-v1.schema.json"
EXAMPLE="$ROOT_DIR/examples/issue-release-intelligence-v5.4.0.json"
GUIDE="$ROOT_DIR/docs/known-issues-release-intelligence-v5.4.0.md"

for path in \
  "$MAIN" "$KB" "$SUPPORT" "$UNIFIED" "$INTELLIGENCE" \
  "$INTELLIGENCE_CSS" "$UNIFIED_CSS" "$KB_CSS" "$SUPPORT_CSS" "$INTEGRITY_CSS" \
  "$MANIFEST" "$RELEASE_MANIFEST" "$BACKEND" "$BACKEND_TEST" "$SCHEMA" \
  "$EXAMPLE" "$GUIDE" "$ROOT_DIR/RELEASE_NOTES_5.4.0.md" \
  "$ROOT_DIR/SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V5.4.0_RELEASE_NOTES.md"; do
  [ -f "$path" ] || fail "Missing required release file: ${path#$ROOT_DIR/}"
done

grep -Fq 'Plugin Name: Sustainable Catalyst Product Support and Feedback Platform' "$MAIN" || fail "Public plugin name mismatch."
grep -Fq 'Version: 5.4.0' "$MAIN" || fail "Plugin version header mismatch."
grep -Fq "const VERSION = '5.4.0';" "$MAIN" || fail "Runtime version mismatch."
grep -Fq "const POST_TYPE = 'sc_feature_suggest';" "$MAIN" || fail "Legacy suggestion post type changed."
grep -Fq "const REST_NAMESPACE = 'scfs/v1';" "$MAIN" || fail "Legacy REST namespace changed."
grep -Fq "const ARTICLE_POST_TYPE = 'sc_support_article';" "$KB" || fail "Support Article CPT changed."
grep -Fq "const ISSUE_POST_TYPE = 'sc_known_issue';" "$KB" || fail "Known Issue CPT changed."
grep -Fq "'rewrite' => array('slug' => 'support/guides'" "$KB" || fail "Support Article permalink base changed."
grep -Fq "const RELEASE_POST_TYPE = 'sc_release_record';" "$SUPPORT" || fail "Release Record CPT changed."
grep -Fq "const SHORTCODE = 'scfs_product_support_center';" "$SUPPORT" || fail "Support Center shortcode changed."
grep -Fq 'final class SCFS_Known_Issue_Release_Intelligence' "$INTELLIGENCE" || fail "Issue and Release Intelligence class missing."
grep -Fq "const VERSION = '5.4.0';" "$INTELLIGENCE" || fail "Issue and Release Intelligence version mismatch."
grep -Fq "const SCHEMA = 'scfs-known-issue-release-intelligence/1.0';" "$INTELLIGENCE" || fail "Issue and Release Intelligence schema mismatch."
grep -Fq "const SHORTCODE = 'scfs_issue_release_intelligence';" "$INTELLIGENCE" || fail "Issue and Release Intelligence shortcode missing."
grep -Fq '/issue-release-intelligence/issues' "$INTELLIGENCE" || fail "WordPress issue intelligence route missing."
grep -Fq '/issue-release-intelligence/releases' "$INTELLIGENCE" || fail "WordPress release intelligence route missing."
grep -Fq '/issue-release-intelligence/sync' "$INTELLIGENCE" || fail "Protected synchronization route missing."
grep -Fq '/v1/issue-release-intelligence/capabilities' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI intelligence capabilities route missing."
grep -Fq '/v1/issue-release-intelligence/evaluate' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI intelligence evaluation route missing."
grep -Fq 'SCFS_Known_Issue_Release_Intelligence' "$SUPPORT" || fail "Support Center operational integration missing."
grep -Fq 'enrich_operational_intelligence' "$UNIFIED" || fail "Unified Support Search operational enrichment missing."

printf '==> PHP syntax\n'
php_count=0
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
  php_count=$((php_count + 1))
done < <(find "$PLUGIN_DIR" -type f -name '*.php' -print0)
printf 'PASS - %d plugin PHP files\n' "$php_count"

printf '==> WordPress contract suite\n'
test_count=0
contract_log="$(mktemp "${TMPDIR:-/tmp}/scfs-v540-contracts.XXXXXX")"
while IFS= read -r file; do
  if ! php "$file" >>"$contract_log" 2>&1; then
    cat "$contract_log" >&2
    rm -f "$contract_log"
    fail "WordPress contract failed: ${file#$ROOT_DIR/}"
  fi
  test_count=$((test_count + 1))
done < <(find "$ROOT_DIR/tests" -maxdepth 1 -type f -name 'test-*.php' | sort)
rm -f "$contract_log"
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
  printf 'SKIP - Node.js is not installed; JavaScript syntax was not checked.\n'
fi

printf '==> JSON and Python syntax\n'
"$PYTHON_BIN" - "$ROOT_DIR" <<'PY'
import ast
import json
import sys
from pathlib import Path
root = Path(sys.argv[1])
excluded = {'.git', '.venv', 'venv', '__pycache__', '.pytest_cache'}
json_files = [p for p in root.rglob('*.json') if not any(part in excluded for part in p.parts)]
for path in json_files:
    json.loads(path.read_text(encoding='utf-8'))
py_files = [p for p in root.rglob('*.py') if not any(part in excluded for part in p.parts)]
for path in py_files:
    ast.parse(path.read_text(encoding='utf-8'), filename=str(path))
print(f'PASS - {len(json_files)} JSON files and {len(py_files)} Python files')
PY

printf '==> Source whitespace compatibility\n'
"$PYTHON_BIN" - "$ROOT_DIR" <<'PY'
import sys
from pathlib import Path
root = Path(sys.argv[1])
extensions = {'.css', '.html', '.ini', '.js', '.json', '.md', '.php', '.py', '.sh', '.txt', '.xml', '.yaml', '.yml'}
excluded = {'.git', '.pytest_cache', '.venv', '__pycache__', 'venv'}
issues = []
for path in root.rglob('*'):
    if not path.is_file() or path.suffix.lower() not in extensions:
        continue
    if any(part in excluded for part in path.parts):
        continue
    try:
        lines = path.read_text(encoding='utf-8').splitlines()
    except UnicodeDecodeError:
        continue
    for number, line in enumerate(lines, 1):
        if line.endswith((' ', '\t')):
            issues.append(f'{path.relative_to(root)}:{number}')
if issues:
    raise SystemExit('Trailing whitespace detected:\n' + '\n'.join(issues))
print('PASS - source tree contains no trailing whitespace')
PY

printf '==> CSS structural validation\n'
"$PYTHON_BIN" - "$KB_CSS" "$SUPPORT_CSS" "$INTEGRITY_CSS" "$UNIFIED_CSS" "$INTELLIGENCE_CSS" <<'PY'
import re
import sys
from pathlib import Path
for raw in sys.argv[1:]:
    path = Path(raw)
    text = path.read_text(encoding='utf-8')
    clean = re.sub(r'/\*.*?\*/', '', text, flags=re.S)
    level = 0
    for index, char in enumerate(clean):
        if char == '{':
            level += 1
        elif char == '}':
            level -= 1
            if level < 0:
                raise SystemExit(f'{path.name} closes a block too early at offset {index}')
    if level != 0:
        raise SystemExit(f'{path.name} block imbalance: {level}')
    if '</style>' in text.lower():
        raise SystemExit(f'{path.name} contains a forbidden </style> tag')
    print(f'PASS - {path.name} balanced ({len(text.splitlines())} lines)')
PY

printf '==> FastAPI backend tests\n'
PYTHONPATH="$ROOT_DIR/backend" "$PYTHON_BIN" -m pytest "$ROOT_DIR/backend/tests" -q

printf '==> Release manifests\n'
"$PYTHON_BIN" - "$MANIFEST" "$RELEASE_MANIFEST" "$SCHEMA" "$EXAMPLE" <<'PY'
import json
import sys
manifest = json.load(open(sys.argv[1], encoding='utf-8'))
release = json.load(open(sys.argv[2], encoding='utf-8'))
schema = json.load(open(sys.argv[3], encoding='utf-8'))
example = json.load(open(sys.argv[4], encoding='utf-8'))
assert manifest['name'] == 'Sustainable Catalyst Product Support and Feedback Platform'
assert manifest['legacy_name'] == 'Sustainable Catalyst Feature Suggestions'
assert manifest['slug'] == 'sustainable-catalyst-feature-suggestions'
assert manifest['version'] == '5.4.0'
assert manifest['release_name'] == 'Known Issues and Release Intelligence Integration'
assert manifest['compatibility']['rest_namespace'] == 'scfs/v1'
assert manifest['compatibility']['database_migration_required'] is False
intelligence = manifest['issue_release_intelligence']
assert intelligence['schema'] == 'scfs-known-issue-release-intelligence/1.0'
assert intelligence['target_release_relationships'] is True
assert intelligence['fixed_release_relationships'] is True
assert intelligence['automatic_incident_declaration'] is False
assert intelligence['automatic_release_status_changes'] is False
assert intelligence['automatic_publication'] is False
assert intelligence['human_review_required'] is True
assert release['version'] == '5.4.0'
assert release['support_article_permalink_base'] == '/support/guides/'
assert release['compatibility']['rest_namespace'] == 'scfs/v1'
assert release['compatibility']['database_migration_required'] is False
assert release['issue_release_intelligence']['schema'] == 'scfs-known-issue-release-intelligence/1.0'
assert release['issue_release_intelligence']['automatic_incident_declaration'] is False
assert release['issue_release_intelligence']['automatic_release_status_changes'] is False
assert schema['properties']['version']['const'] == '5.4.0'
assert example['schema'] == 'scfs-known-issue-release-intelligence/1.0'
assert example['human_review_required'] is True
print('PASS - release identity, relationship contracts, governance boundaries, and compatibility fields')
PY

printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
