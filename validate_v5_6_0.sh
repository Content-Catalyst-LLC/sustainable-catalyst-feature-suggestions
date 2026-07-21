#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="5.6.0"
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
SIGNALS="$PLUGIN_DIR/includes/class-scfs-feedback-product-signals.php"
SIGNALS_CSS="$PLUGIN_DIR/assets/feedback-product-signals.css"
SIGNALS_JS="$PLUGIN_DIR/assets/feedback-product-signals.js"
MANIFEST="$ROOT_DIR/feature_suggestions_manifest.json"
RELEASE_MANIFEST="$ROOT_DIR/release-manifest-v5.6.0.json"
BACKEND="$ROOT_DIR/backend/app/feedback_product_signals.py"
BACKEND_TEST="$ROOT_DIR/backend/tests/test_feedback_product_signals.py"
SCHEMA="$ROOT_DIR/schemas/scfs-feedback-product-signals-v1.schema.json"
EXAMPLE="$ROOT_DIR/examples/feedback-product-signals-v5.6.0.json"
GUIDE="$ROOT_DIR/docs/feedback-intelligence-product-signals-v5.6.0.md"

for path in \
  "$MAIN" "$KB" "$SUPPORT" "$SIGNALS" "$SIGNALS_CSS" "$SIGNALS_JS" \
  "$MANIFEST" "$RELEASE_MANIFEST" "$BACKEND" "$BACKEND_TEST" "$SCHEMA" \
  "$EXAMPLE" "$GUIDE" "$ROOT_DIR/RELEASE_NOTES_5.6.0.md" \
  "$ROOT_DIR/SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V5.6.0_RELEASE_NOTES.md"; do
  [ -f "$path" ] || fail "Missing required release file: ${path#$ROOT_DIR/}"
done

grep -Fq 'Plugin Name: Sustainable Catalyst Product Support and Feedback Platform' "$MAIN" || fail "Public plugin name mismatch."
grep -Fq 'Version: 5.6.0' "$MAIN" || fail "Plugin version header mismatch."
grep -Fq "const VERSION = '5.6.0';" "$MAIN" || fail "Runtime version mismatch."
grep -Fq "const POST_TYPE = 'sc_feature_suggest';" "$MAIN" || fail "Legacy suggestion post type changed."
grep -Fq "const REST_NAMESPACE = 'scfs/v1';" "$MAIN" || fail "Legacy REST namespace changed."
grep -Fq "const ARTICLE_POST_TYPE = 'sc_support_article';" "$KB" || fail "Support Article CPT changed."
grep -Fq "const ISSUE_POST_TYPE = 'sc_known_issue';" "$KB" || fail "Known Issue CPT changed."
grep -Fq "'rewrite' => array('slug' => 'support/guides'" "$KB" || fail "Support Article permalink base changed."
grep -Fq "const RELEASE_POST_TYPE = 'sc_release_record';" "$SUPPORT" || fail "Release Record CPT changed."
grep -Fq "const SHORTCODE = 'scfs_product_support_center';" "$SUPPORT" || fail "Support Center shortcode changed."
grep -Fq 'final class SCFS_Feedback_Product_Signals' "$SIGNALS" || fail "Product Signals class missing."
grep -Fq "const VERSION = '5.6.0';" "$SIGNALS" || fail "Product Signals version mismatch."
grep -Fq "const SCHEMA = 'scfs-feedback-product-signals/1.0';" "$SIGNALS" || fail "Product Signals schema mismatch."
grep -Fq '/feedback-product-signals/summary' "$SIGNALS" || fail "Product Signals summary route missing."
grep -Fq '/feedback-product-signals/products' "$SIGNALS" || fail "Product Signals products route missing."
grep -Fq '/feedback-product-signals/refresh' "$SIGNALS" || fail "Product Signals refresh route missing."
grep -Fq '/v1/feedback-product-signals/capabilities' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI capabilities route missing."
grep -Fq '/v1/feedback-product-signals/score' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI score route missing."
grep -Fq '/v1/feedback-product-signals/portfolio' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI portfolio route missing."
grep -Fq '/v1/feedback-product-signals/clusters/prioritize' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI cluster route missing."

printf '==> PHP syntax\n'
php_count=0
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
  php_count=$((php_count + 1))
done < <(find "$PLUGIN_DIR" -type f -name '*.php' -print0)
printf 'PASS - %d plugin PHP files\n' "$php_count"

printf '==> WordPress contract suite\n'
test_count=0
contract_log="$(mktemp "${TMPDIR:-/tmp}/scfs-v560-contracts.XXXXXX")"
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
  "$PLUGIN_DIR/assets/support-content-governance.css" \
  "$SIGNALS_CSS" <<'PY'
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
assert manifest['version'] == '5.6.0'
assert manifest['release_name'] == 'Feedback Intelligence and Product Signals'
assert manifest['compatibility']['rest_namespace'] == 'scfs/v1'
assert manifest['compatibility']['database_migration_required'] is False
signals = manifest['feedback_product_signals']
assert signals['schema'] == 'scfs-feedback-product-signals/1.0'
for key in ('feature_request_demand', 'public_vote_demand', 'article_feedback_quality_signals', 'unresolved_search_signals', 'failed_resolution_path_signals', 'documentation_gap_signals', 'known_issue_demand', 'support_relationship_counts', 'evidence_cluster_prioritization', 'scheduled_snapshots', 'csv_export', 'administrator_only', 'human_review_required'):
    assert signals[key] is True, key
for key in ('personal_identifiers_exposed', 'raw_search_text_exposed', 'private_case_content_exposed', 'automatic_roadmap_changes', 'automatic_issue_declaration', 'automatic_publication', 'database_migration_required'):
    assert signals[key] is False, key
assert release['version'] == '5.6.0'
assert release['support_article_permalink_base'] == '/support/guides/'
assert release['compatibility']['rest_namespace'] == 'scfs/v1'
assert release['compatibility']['database_migration_required'] is False
assert release['feedback_product_signals']['schema'] == 'scfs-feedback-product-signals/1.0'
assert release['feedback_product_signals']['raw_search_text_exposed'] is False
assert release['feedback_product_signals']['private_case_content_exposed'] is False
assert release['feedback_product_signals']['human_review_required'] is True
assert schema['$id'].endswith('scfs-feedback-product-signals-v1.schema.json')
assert example['schema'] == 'scfs-feedback-product-signals/1.0'
validation = manifest['validation']
assert validation['status'] == 'passed'
assert validation['plugin_php_files'] >= 28
assert validation['php_contract_files'] >= 95
assert validation['javascript_files'] >= 13
assert validation['json_files'] >= 77
assert validation['python_files'] >= 34
assert validation['fastapi_tests'] >= 104
print('PASS - release identity, compatibility, privacy, and Product Signals fields')
PY

printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
