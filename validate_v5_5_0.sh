#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="5.5.0"
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
CONTENT_OPS="$PLUGIN_DIR/includes/class-scfs-support-content-operations.php"
EDITORIAL="$PLUGIN_DIR/includes/class-scfs-editorial-governance.php"
GOVERNANCE="$PLUGIN_DIR/includes/class-scfs-support-content-governance.php"
GOVERNANCE_CSS="$PLUGIN_DIR/assets/support-content-governance.css"
GOVERNANCE_JS="$PLUGIN_DIR/assets/support-content-governance.js"
MANIFEST="$ROOT_DIR/feature_suggestions_manifest.json"
RELEASE_MANIFEST="$ROOT_DIR/release-manifest-v5.5.0.json"
BACKEND="$ROOT_DIR/backend/app/content_governance.py"
BACKEND_TEST="$ROOT_DIR/backend/tests/test_content_governance.py"
SCHEMA="$ROOT_DIR/schemas/scfs-support-content-governance-v1.schema.json"
EXAMPLE="$ROOT_DIR/examples/support-content-governance-v5.5.0.json"
GUIDE="$ROOT_DIR/docs/support-content-operations-editorial-governance-v5.5.0.md"

for path in \
  "$MAIN" "$KB" "$SUPPORT" "$CONTENT_OPS" "$EDITORIAL" "$GOVERNANCE" \
  "$GOVERNANCE_CSS" "$GOVERNANCE_JS" "$MANIFEST" "$RELEASE_MANIFEST" \
  "$BACKEND" "$BACKEND_TEST" "$SCHEMA" "$EXAMPLE" "$GUIDE" \
  "$ROOT_DIR/RELEASE_NOTES_5.5.0.md" \
  "$ROOT_DIR/SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V5.5.0_RELEASE_NOTES.md"; do
  [ -f "$path" ] || fail "Missing required release file: ${path#$ROOT_DIR/}"
done

grep -Fq 'Plugin Name: Sustainable Catalyst Product Support and Feedback Platform' "$MAIN" || fail "Public plugin name mismatch."
grep -Fq 'Version: 5.5.0' "$MAIN" || fail "Plugin version header mismatch."
grep -Fq "const VERSION = '5.5.0';" "$MAIN" || fail "Runtime version mismatch."
grep -Fq "const POST_TYPE = 'sc_feature_suggest';" "$MAIN" || fail "Legacy suggestion post type changed."
grep -Fq "const REST_NAMESPACE = 'scfs/v1';" "$MAIN" || fail "Legacy REST namespace changed."
grep -Fq "const ARTICLE_POST_TYPE = 'sc_support_article';" "$KB" || fail "Support Article CPT changed."
grep -Fq "const ISSUE_POST_TYPE = 'sc_known_issue';" "$KB" || fail "Known Issue CPT changed."
grep -Fq "'rewrite' => array('slug' => 'support/guides'" "$KB" || fail "Support Article permalink base changed."
grep -Fq "const RELEASE_POST_TYPE = 'sc_release_record';" "$SUPPORT" || fail "Release Record CPT changed."
grep -Fq "const SHORTCODE = 'scfs_product_support_center';" "$SUPPORT" || fail "Support Center shortcode changed."
grep -Fq "const VERSION = '5.5.0';" "$CONTENT_OPS" || fail "Content Operations version mismatch."
grep -Fq "const VERSION = '5.5.0';" "$EDITORIAL" || fail "Editorial Governance version mismatch."
grep -Fq 'final class SCFS_Support_Content_Governance' "$GOVERNANCE" || fail "Content Governance class missing."
grep -Fq "const VERSION = '5.5.0';" "$GOVERNANCE" || fail "Content Governance version mismatch."
grep -Fq "const SCHEMA = 'scfs-support-content-governance/1.0';" "$GOVERNANCE" || fail "Content Governance schema mismatch."
grep -Fq '/content-governance/schema' "$GOVERNANCE" || fail "Content Governance schema route missing."
grep -Fq '/content-governance/queue' "$GOVERNANCE" || fail "Content Governance queue route missing."
grep -Fq '/content-governance/verify/(?P<id>\d+)' "$GOVERNANCE" || fail "Content Governance verification route missing."
grep -Fq '/v1/content-governance/capabilities' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI capabilities route missing."
grep -Fq '/v1/content-governance/evaluate' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI evaluation route missing."
grep -Fq '/v1/content-governance/queue/summarize' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI queue route missing."
grep -Fq '/v1/content-governance/bulk/plan' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI bulk plan route missing."

printf '==> PHP syntax\n'
php_count=0
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
  php_count=$((php_count + 1))
done < <(find "$PLUGIN_DIR" -type f -name '*.php' -print0)
printf 'PASS - %d plugin PHP files\n' "$php_count"

printf '==> WordPress contract suite\n'
test_count=0
contract_log="$(mktemp "${TMPDIR:-/tmp}/scfs-v550-contracts.XXXXXX")"
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
"$PYTHON_BIN" - \
  "$PLUGIN_DIR/assets/knowledge-base.css" \
  "$PLUGIN_DIR/assets/product-support-platform.css" \
  "$PLUGIN_DIR/assets/support-article-integrity.css" \
  "$PLUGIN_DIR/assets/unified-support-search.css" \
  "$PLUGIN_DIR/assets/issue-release-intelligence.css" \
  "$GOVERNANCE_CSS" <<'PY'
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
assert manifest['version'] == '5.5.0'
assert manifest['release_name'] == 'Support Content Operations and Editorial Governance'
assert manifest['compatibility']['rest_namespace'] == 'scfs/v1'
assert manifest['compatibility']['database_migration_required'] is False
governance = manifest['support_content_governance']
assert governance['schema'] == 'scfs-support-content-governance/1.0'
assert governance['content_owner_assignments'] is True
assert governance['technical_owner_assignments'] is True
assert governance['verification_history'] is True
assert governance['supersession_relationships'] is True
assert governance['public_record_data_exposed'] is False
assert governance['automatic_publication'] is False
assert governance['automatic_editorial_approval'] is False
assert governance['human_review_required'] is True
assert release['version'] == '5.5.0'
assert release['support_article_permalink_base'] == '/support/guides/'
assert release['compatibility']['rest_namespace'] == 'scfs/v1'
assert release['compatibility']['database_migration_required'] is False
assert release['support_content_governance']['schema'] == 'scfs-support-content-governance/1.0'
assert schema['properties']['version']['const'] == '5.5.0'
assert example['schema'] == 'scfs-support-content-governance/1.0'
assert example['human_review_required'] is True
assert example['automatic_publication'] is False
print('PASS - release identity, governance contracts, human-authority boundaries, and compatibility fields')
PY

printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
