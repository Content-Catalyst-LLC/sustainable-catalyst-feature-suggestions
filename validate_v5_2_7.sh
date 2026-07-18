#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="5.2.7"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$ROOT_DIR/wordpress/sustainable-catalyst-feature-suggestions"
PYTHON_BIN="${PYTHON_BIN:-python3}"

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
command -v php >/dev/null 2>&1 || fail "PHP CLI is required."
command -v "$PYTHON_BIN" >/dev/null 2>&1 || fail "Python is required."

printf '==> Product Support and Feedback Platform v%s validation\n' "$VERSION"

MAIN="$PLUGIN_DIR/sustainable-catalyst-feature-suggestions.php"
KB="$PLUGIN_DIR/includes/class-scfs-knowledge-base.php"
INTEGRATED="$PLUGIN_DIR/includes/class-scfs-integrated-knowledge-base.php"
SUPPORT="$PLUGIN_DIR/includes/class-scfs-product-support-platform.php"
KB_CSS="$PLUGIN_DIR/assets/knowledge-base.css"
SUPPORT_CSS="$PLUGIN_DIR/assets/product-support-platform.css"
SUPPORT_JS="$PLUGIN_DIR/assets/product-support-platform.js"
KB_JS="$PLUGIN_DIR/assets/integrated-knowledge-base.js"
LEGACY_PAGE="$PLUGIN_DIR/content/knowledge-base/page.html"
MANIFEST="$ROOT_DIR/feature_suggestions_manifest.json"
RELEASE_MANIFEST="$ROOT_DIR/release-manifest-v5.2.7.json"

for path in "$MAIN" "$KB" "$INTEGRATED" "$SUPPORT" "$KB_CSS" "$SUPPORT_CSS" "$SUPPORT_JS" "$KB_JS" "$LEGACY_PAGE" "$MANIFEST" "$RELEASE_MANIFEST" "$ROOT_DIR/RELEASE_NOTES_5.2.7.md"; do
  [ -f "$path" ] || fail "Missing required release file: ${path#$ROOT_DIR/}"
done

grep -Fq 'Plugin Name: Sustainable Catalyst Product Support and Feedback Platform' "$MAIN" || fail "Public plugin name mismatch."
grep -Fq 'Version: 5.2.7' "$MAIN" || fail "Plugin version header mismatch."
grep -Fq "const VERSION = '5.2.7';" "$MAIN" || fail "Runtime version mismatch."
grep -Fq "const POST_TYPE = 'sc_feature_suggest';" "$MAIN" || fail "Legacy suggestion post type changed."
grep -Fq "const REST_NAMESPACE = 'scfs/v1';" "$MAIN" || fail "Legacy REST namespace changed."
grep -Fq "const SHORTCODE = 'scfs_support_knowledge_base';" "$KB" || fail "Knowledge Base shortcode changed."
grep -Fq "const ARTICLE_POST_TYPE = 'sc_support_article';" "$KB" || fail "Support Article CPT changed."
grep -Fq "'rewrite' => array('slug' => 'support/guides'" "$KB" || fail "Support Article permalink base changed."
grep -Fq "const SUPPORT_PAGE_SLUG = 'support';" "$SUPPORT" || fail "Canonical Support page integration missing."
grep -Fq "integrate_support_page_content" "$SUPPORT" || fail "Automatic Support page integration missing."
grep -Fq "maybe_enqueue_support_page_assets" "$SUPPORT" || fail "Early Support assets missing."
grep -Fq 'private static $render_signatures = array();' "$SUPPORT" || fail "Duplicate render registry missing."
grep -Fq 'id="guided-resolution"' "$SUPPORT" || fail "Guided Resolution anchor missing."
grep -Fq 'id="knowledge-base"' "$SUPPORT" || fail "Knowledge Base anchor missing."
grep -Fq 'id="known-issues"' "$SUPPORT" || fail "Known Issues anchor missing."
grep -Fq 'id="release-intelligence"' "$SUPPORT" || fail "Release Intelligence anchor missing."
grep -Fq "const ROUTE_VERSION = '3.1.0';" "$INTEGRATED" || fail "Route contract version mismatch."
grep -Fq 'request_is_redirect_target' "$INTEGRATED" || fail "Legacy redirect-loop guard missing."
grep -Fq 'new AbortController()' "$SUPPORT_JS" || fail "Abortable support navigation missing."
grep -Fq 'SCFSIntegratedKnowledgeBase.initAll(workspace)' "$SUPPORT_JS" || fail "Dynamic Knowledge Base reinitialization missing."
grep -Fq 'window.SCFSIntegratedKnowledgeBase' "$KB_JS" || fail "Knowledge Base runtime API missing."
if grep -Fq '[scfs_support_knowledge_base]' "$LEGACY_PAGE"; then fail "Legacy page still renders a duplicate Knowledge Base browser."; fi

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
  php "$file"
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
  printf 'SKIP - Node.js is not installed; JavaScript syntax was not checked.\n'
fi

printf '==> JSON and Python syntax\n'
"$PYTHON_BIN" - "$ROOT_DIR" <<'PY'
import ast
import json
import sys
from pathlib import Path
root = Path(sys.argv[1])
json_files = [p for p in root.rglob('*.json') if not any(part in {'.git', '.venv', 'venv', '__pycache__', '.pytest_cache'} for part in p.parts)]
for path in json_files:
    json.loads(path.read_text(encoding='utf-8'))
py_files = [p for p in (root / 'backend').rglob('*.py') if '__pycache__' not in p.parts]
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
issues = []
for path in root.rglob('*'):
    if not path.is_file() or path.suffix.lower() not in extensions:
        continue
    if any(part in {'.git', '.pytest_cache', '.venv', '__pycache__', 'venv'} for part in path.parts):
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
"$PYTHON_BIN" - "$KB_CSS" "$SUPPORT_CSS" <<'PY'
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
assert manifest['version'] == '5.2.7'
assert manifest['release_name'] == 'Support Center Production Integration and Interface Hardening'
assert manifest['compatibility']['database_migration_required'] is False
assert manifest['integration']['duplicate_render_suppression'] is True
assert release['version'] == '5.2.7'
assert release['canonical_knowledge_base_location'].endswith('#knowledge-base')
assert release['compatibility']['support_article_permalink_base'] == '/support/guides/'
assert release['production_integration']['early_asset_enqueue'] == 'complete'
assert release['legacy_routes']['/support/knowledge-base/']['status'] == 301
print('PASS - release identity, production integration, route consolidation, and compatibility fields')
PY

printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
