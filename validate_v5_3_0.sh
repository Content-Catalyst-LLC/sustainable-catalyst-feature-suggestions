#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="5.3.0"
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
GUIDED="$PLUGIN_DIR/includes/class-scfs-guided-resolution.php"
DISCOVERY="$PLUGIN_DIR/includes/class-scfs-support-discovery.php"
UNIFIED="$PLUGIN_DIR/includes/class-scfs-unified-support-search.php"
UNIFIED_JS="$PLUGIN_DIR/assets/unified-support-search.js"
UNIFIED_CSS="$PLUGIN_DIR/assets/unified-support-search.css"
KB_CSS="$PLUGIN_DIR/assets/knowledge-base.css"
SUPPORT_CSS="$PLUGIN_DIR/assets/product-support-platform.css"
INTEGRITY_CSS="$PLUGIN_DIR/assets/support-article-integrity.css"
MANIFEST="$ROOT_DIR/feature_suggestions_manifest.json"
RELEASE_MANIFEST="$ROOT_DIR/release-manifest-v5.3.0.json"
BACKEND="$ROOT_DIR/backend/app/unified_support_search.py"
BACKEND_TEST="$ROOT_DIR/backend/tests/test_unified_support_search.py"
SCHEMA="$ROOT_DIR/schemas/scfs-unified-support-search-v1.schema.json"
EXAMPLE="$ROOT_DIR/examples/unified-support-search.json"
GUIDE="$ROOT_DIR/docs/unified-search-guided-resolution-v5.3.0.md"

for path in \
  "$MAIN" "$KB" "$SUPPORT" "$GUIDED" "$DISCOVERY" "$UNIFIED" \
  "$UNIFIED_JS" "$UNIFIED_CSS" "$KB_CSS" "$SUPPORT_CSS" "$INTEGRITY_CSS" \
  "$MANIFEST" "$RELEASE_MANIFEST" "$BACKEND" "$BACKEND_TEST" "$SCHEMA" \
  "$EXAMPLE" "$GUIDE" "$ROOT_DIR/RELEASE_NOTES_5.3.0.md" \
  "$ROOT_DIR/SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V5.3.0_RELEASE_NOTES.md"; do
  [ -f "$path" ] || fail "Missing required release file: ${path#$ROOT_DIR/}"
done

grep -Fq 'Plugin Name: Sustainable Catalyst Product Support and Feedback Platform' "$MAIN" || fail "Public plugin name mismatch."
grep -Fq 'Version: 5.3.0' "$MAIN" || fail "Plugin version header mismatch."
grep -Fq "const VERSION = '5.3.0';" "$MAIN" || fail "Runtime version mismatch."
grep -Fq "const POST_TYPE = 'sc_feature_suggest';" "$MAIN" || fail "Legacy suggestion post type changed."
grep -Fq "const REST_NAMESPACE = 'scfs/v1';" "$MAIN" || fail "Legacy REST namespace changed."
grep -Fq "const ARTICLE_POST_TYPE = 'sc_support_article';" "$KB" || fail "Support Article CPT changed."
grep -Fq "'rewrite' => array('slug' => 'support/guides'" "$KB" || fail "Support Article permalink base changed."
grep -Fq "const SHORTCODE = 'scfs_product_support_center';" "$SUPPORT" || fail "Support Center shortcode changed."
grep -Fq 'final class SCFS_Unified_Support_Search' "$UNIFIED" || fail "Unified Support Search class missing."
grep -Fq "const VERSION = '5.3.0';" "$UNIFIED" || fail "Unified Support Search version mismatch."
grep -Fq "const SCHEMA = 'scfs-unified-support-search/1.0';" "$UNIFIED" || fail "Unified search schema mismatch."
grep -Fq "const JOURNEY_SCHEMA = 'scfs-support-resolution-journey/1.0';" "$UNIFIED" || fail "Resolution journey schema mismatch."
grep -Fq "const SHORTCODE = 'scfs_unified_support_search';" "$UNIFIED" || fail "Unified search shortcode missing."
grep -Fq "const LEGACY_SHORTCODE = 'scfs_unified_guided_resolution';" "$UNIFIED" || fail "Unified search compatibility shortcode missing."
grep -Fq '/unified-support/search' "$UNIFIED" || fail "WordPress unified search route missing."
grep -Fq '/unified-support/journey' "$UNIFIED" || fail "WordPress resolution journey route missing."
grep -Fq '/v1/unified-support/search' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI unified search route missing."
grep -Fq '/v1/unified-support/journey' "$ROOT_DIR/backend/app/main.py" || fail "FastAPI resolution journey route missing."
grep -Fq 'SCFS_Unified_Support_Search' "$SUPPORT" || fail "Support Center does not integrate unified search."
grep -Fq 'SCFS_Guided_Resolution' "$SUPPORT" || fail "Guided Resolution fallback was removed."

printf '==> PHP syntax\n'
php_count=0
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
  php_count=$((php_count + 1))
done < <(find "$PLUGIN_DIR" -type f -name '*.php' -print0)
printf 'PASS - %d plugin PHP files\n' "$php_count"

printf '==> WordPress contract suite\n'
test_count=0
contract_log="$(mktemp "${TMPDIR:-/tmp}/scfs-v530-contracts.XXXXXX")"
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
"$PYTHON_BIN" - "$KB_CSS" "$SUPPORT_CSS" "$INTEGRITY_CSS" "$UNIFIED_CSS" <<'PY'
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
"$PYTHON_BIN" - "$MANIFEST" "$RELEASE_MANIFEST" <<'PY'
import json
import sys
manifest = json.load(open(sys.argv[1], encoding='utf-8'))
release = json.load(open(sys.argv[2], encoding='utf-8'))
assert manifest['name'] == 'Sustainable Catalyst Product Support and Feedback Platform'
assert manifest['legacy_name'] == 'Sustainable Catalyst Feature Suggestions'
assert manifest['slug'] == 'sustainable-catalyst-feature-suggestions'
assert manifest['version'] == '5.3.0'
assert manifest['release_name'] == 'Unified Search and Guided Resolution'
assert manifest['compatibility']['rest_namespace'] == 'scfs/v1'
assert manifest['compatibility']['database_migration_required'] is False
unified = manifest['unified_support_search']
assert unified['schema'] == 'scfs-unified-support-search/1.0'
assert unified['journey_schema'] == 'scfs-support-resolution-journey/1.0'
assert unified['personal_data_stored'] is False
assert unified['automatic_case_creation'] is False
assert unified['human_review_required'] is True
assert release['version'] == '5.3.0'
assert release['support_article_permalink_base'] == '/support/guides/'
assert release['compatibility']['rest_namespace'] == 'scfs/v1'
assert release['compatibility']['database_migration_required'] is False
assert release['unified_support_search']['schema'] == 'scfs-unified-support-search/1.0'
assert release['unified_support_search']['journey_schema'] == 'scfs-support-resolution-journey/1.0'
assert release['unified_support_search']['personal_data_stored'] is False
assert release['unified_support_search']['automatic_case_creation'] is False
print('PASS - release identity, unified-search contracts, safety boundaries, and compatibility fields')
PY

printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
