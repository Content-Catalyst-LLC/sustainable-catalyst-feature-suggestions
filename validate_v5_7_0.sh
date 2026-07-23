#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="5.7.0"
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
ANALYTICS="$PLUGIN_DIR/includes/class-scfs-support-analytics-documentation-effectiveness.php"
ANALYTICS_CSS="$PLUGIN_DIR/assets/support-analytics-documentation-effectiveness.css"
ANALYTICS_JS="$PLUGIN_DIR/assets/support-analytics-documentation-effectiveness.js"
MANIFEST="$ROOT_DIR/feature_suggestions_manifest.json"
RELEASE_MANIFEST="$ROOT_DIR/release-manifest-v5.7.0.json"
BACKEND="$ROOT_DIR/backend/app/support_analytics_documentation_effectiveness.py"
BACKEND_TEST="$ROOT_DIR/backend/tests/test_support_analytics_documentation_effectiveness.py"
SCHEMA="$ROOT_DIR/schemas/scfs-support-analytics-documentation-effectiveness-v1.schema.json"
EXAMPLE="$ROOT_DIR/examples/support-analytics-documentation-effectiveness-v5.7.0.json"
GUIDE="$ROOT_DIR/docs/support-analytics-documentation-effectiveness-v5.7.0.md"

for path in \
  "$MAIN" "$KB" "$SUPPORT" "$ANALYTICS" "$ANALYTICS_CSS" "$ANALYTICS_JS" \
  "$MANIFEST" "$RELEASE_MANIFEST" "$BACKEND" "$BACKEND_TEST" "$SCHEMA" \
  "$EXAMPLE" "$GUIDE" "$ROOT_DIR/RELEASE_NOTES_5.7.0.md" \
  "$ROOT_DIR/SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V5.7.0_RELEASE_NOTES.md"; do
  [ -f "$path" ] || fail "Missing required release file: ${path#$ROOT_DIR/}"
done

grep -Fq 'Plugin Name: Sustainable Catalyst Product Support and Feedback Platform' "$MAIN" || fail "Public plugin name mismatch."
grep -Fq 'Version: 5.7.0' "$MAIN" || fail "Plugin version header mismatch."
grep -Fq "const VERSION = '5.7.0';" "$MAIN" || fail "Runtime version mismatch."
grep -Fq "const POST_TYPE = 'sc_feature_suggest';" "$MAIN" || fail "Legacy suggestion post type changed."
grep -Fq "const REST_NAMESPACE = 'scfs/v1';" "$MAIN" || fail "Legacy REST namespace changed."
grep -Fq "const ARTICLE_POST_TYPE = 'sc_support_article';" "$KB" || fail "Support Article CPT changed."
grep -Fq "const ISSUE_POST_TYPE = 'sc_known_issue';" "$KB" || fail "Known Issue CPT changed."
grep -Fq "'rewrite' => array('slug' => 'support/guides'" "$KB" || fail "Support Article permalink base changed."
grep -Fq "const RELEASE_POST_TYPE = 'sc_release_record';" "$SUPPORT" || fail "Release Record CPT changed."
grep -Fq "const SHORTCODE = 'scfs_product_support_center';" "$SUPPORT" || fail "Support Center shortcode changed."
grep -Fq 'final class SCFS_Support_Analytics_Documentation_Effectiveness' "$ANALYTICS" || fail "Support Analytics class missing."
grep -Fq "const VERSION = '5.7.0';" "$ANALYTICS" || fail "Support Analytics version mismatch."
grep -Fq "const SCHEMA = 'scfs-support-analytics-documentation-effectiveness/1.0';" "$ANALYTICS" || fail "Support Analytics schema mismatch."
grep -Fq '/support-analytics/summary' "$ANALYTICS" || fail "Support Analytics summary route missing."
grep -Fq '/support-analytics/product/' "$ANALYTICS" || fail "Support Analytics product route missing."
grep -Fq '/support-analytics/trend/' "$ANALYTICS" || fail "Support Analytics trend route missing."
grep -Fq '/v1/support-analytics/capabilities' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI capabilities route missing."
grep -Fq '/v1/support-analytics/evaluate' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI evaluate route missing."
grep -Fq '/v1/support-analytics/portfolio' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI portfolio route missing."
grep -Fq '/v1/support-analytics/trends/compare' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI trend route missing."
grep -Fq '/v1/support-analytics/reports/verify' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI report integrity route missing."

printf '==> PHP syntax\n'
php_count=0
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
  php_count=$((php_count + 1))
done < <(find "$PLUGIN_DIR" -type f -name '*.php' -print0)
printf 'PASS - %d plugin PHP files\n' "$php_count"

printf '==> WordPress contract suite\n'
test_count=0
contract_log="$(mktemp "${TMPDIR:-/tmp}/scfs-v570-contracts.XXXXXX")"
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
"$PYTHON_BIN" - "$ROOT_DIR" <<'PYVALID'
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
PYVALID

printf '==> Source whitespace compatibility\n'
"$PYTHON_BIN" - "$ROOT_DIR" <<'PYSPACE'
import sys
from pathlib import Path
root = Path(sys.argv[1])
extensions = {'.css', '.html', '.ini', '.js', '.json', '.md', '.php', '.py', '.sh', '.txt', '.xml', '.yaml', '.yml'}
excluded = {'.git', '.pytest_cache', '.venv', '__pycache__', 'venv'}
issues = []
for path in root.rglob('*'):
    if not path.is_file() or path.suffix.lower() not in extensions or any(part in excluded for part in path.parts):
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
PYSPACE

printf '==> CSS structural validation\n'
"$PYTHON_BIN" - \
  "$PLUGIN_DIR/assets/knowledge-base.css" \
  "$PLUGIN_DIR/assets/product-support-platform.css" \
  "$PLUGIN_DIR/assets/support-article-integrity.css" \
  "$PLUGIN_DIR/assets/unified-support-search.css" \
  "$PLUGIN_DIR/assets/issue-release-intelligence.css" \
  "$PLUGIN_DIR/assets/support-content-governance.css" \
  "$PLUGIN_DIR/assets/feedback-product-signals.css" \
  "$ANALYTICS_CSS" <<'PYCSS'
import re
import sys
from pathlib import Path
for raw in sys.argv[1:]:
    path = Path(raw)
    text = path.read_text(encoding='utf-8')
    clean = re.sub(r'/\*.*?\*/', '', text, flags=re.S)
    level = 0
    for index, char in enumerate(clean):
        if char == '{': level += 1
        elif char == '}':
            level -= 1
            if level < 0: raise SystemExit(f'{path.name} closes a block too early at offset {index}')
    if level != 0: raise SystemExit(f'{path.name} block imbalance: {level}')
    if '</style>' in text.lower(): raise SystemExit(f'{path.name} contains a forbidden </style> tag')
    print(f'PASS - {path.name} balanced ({len(text.splitlines())} lines)')
PYCSS

printf '==> FastAPI backend tests\n'
PYTHONPATH="$ROOT_DIR/backend" "$PYTHON_BIN" -m pytest "$ROOT_DIR/backend/tests" -q

printf '==> Release manifests\n'
"$PYTHON_BIN" - "$MANIFEST" "$RELEASE_MANIFEST" "$SCHEMA" "$EXAMPLE" <<'PYMANIFEST'
import json
import sys
manifest = json.load(open(sys.argv[1], encoding='utf-8'))
release = json.load(open(sys.argv[2], encoding='utf-8'))
schema = json.load(open(sys.argv[3], encoding='utf-8'))
example = json.load(open(sys.argv[4], encoding='utf-8'))
assert manifest['name'] == 'Sustainable Catalyst Product Support and Feedback Platform'
assert manifest['legacy_name'] == 'Sustainable Catalyst Feature Suggestions'
assert manifest['slug'] == 'sustainable-catalyst-feature-suggestions'
assert manifest['version'] == '5.7.0'
assert manifest['release_name'] == 'Support Analytics and Documentation Effectiveness'
assert manifest['compatibility']['rest_namespace'] == 'scfs/v1'
assert manifest['compatibility']['database_migration_required'] is False
analytics = manifest['support_analytics']
assert analytics['schema'] == 'scfs-support-analytics-documentation-effectiveness/1.0'
for key in ('search_success_analysis','search_engagement_analysis','article_helpfulness_analysis','publication_integrity_analysis','content_freshness_analysis','known_issue_guidance_coverage','release_documentation_coverage','documentation_gap_resolution','scheduled_snapshots','trend_history','csv_export','administrator_only','human_review_required'):
    assert analytics[key] is True, key
for key in ('personal_identifiers_exposed','raw_search_text_exposed','private_case_content_exposed','automatic_publication','automatic_issue_resolution','automatic_roadmap_changes','database_migration_required'):
    assert analytics[key] is False, key
assert release['version'] == '5.7.0'
assert release['support_article_permalink_base'] == '/support/guides/'
assert release['compatibility']['rest_namespace'] == 'scfs/v1'
assert release['compatibility']['database_migration_required'] is False
assert release['support_analytics']['schema'] == 'scfs-support-analytics-documentation-effectiveness/1.0'
assert schema['$id'].endswith('scfs-support-analytics-documentation-effectiveness-v1.schema.json')
assert schema['properties']['version']['const'] == '5.7.0'
assert example['schema'] == 'scfs-support-analytics-documentation-effectiveness/1.0'
assert example['privacy']['raw_search_text_exposed'] is False
assert example['governance']['human_review_required'] is True
validation = manifest['validation']
assert validation['status'] == 'passed'
assert validation['plugin_php_files'] >= 29
assert validation['php_contract_files'] >= 101
assert validation['javascript_files'] >= 14
assert validation['json_files'] >= 81
assert validation['python_files'] >= 36
assert validation['fastapi_tests'] >= 111
print('PASS - release identity, compatibility, privacy, and Support Analytics fields')
PYMANIFEST

printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
