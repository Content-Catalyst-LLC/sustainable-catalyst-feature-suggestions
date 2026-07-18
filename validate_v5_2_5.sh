#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="5.2.5"
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
CSS="$PLUGIN_DIR/assets/knowledge-base.css"
PAGE="$PLUGIN_DIR/content/knowledge-base/page.html"
MANIFEST="$ROOT_DIR/feature_suggestions_manifest.json"

for path in "$MAIN" "$KB" "$INTEGRATED" "$CSS" "$PAGE" "$MANIFEST" "$ROOT_DIR/RELEASE_NOTES_5.2.5.md"; do
  [ -f "$path" ] || fail "Missing required release file: ${path#$ROOT_DIR/}"
done

grep -Fq 'Plugin Name: Sustainable Catalyst Product Support and Feedback Platform' "$MAIN" || fail "Public plugin name mismatch."
grep -Fq 'Version: 5.2.5' "$MAIN" || fail "Plugin version header mismatch."
grep -Fq "const VERSION = '5.2.5';" "$MAIN" || fail "Runtime version mismatch."
grep -Fq "const POST_TYPE = 'sc_feature_suggest';" "$MAIN" || fail "Legacy suggestion post type changed."
grep -Fq "const REST_NAMESPACE = 'scfs/v1';" "$MAIN" || fail "Legacy REST namespace changed."
grep -Fq "const SHORTCODE = 'scfs_support_knowledge_base';" "$KB" || fail "Knowledge Base shortcode changed."
grep -Fq "const ARTICLE_POST_TYPE = 'sc_support_article';" "$KB" || fail "Support Article CPT changed."
grep -Fq 'scfs-kb-library-navigation' "$INTEGRATED" || fail "Two-panel browser navigation missing."
grep -Fq 'scfs-kb-publication' "$INTEGRATED" || fail "Publication article renderer missing."
grep -Fq '[scfs_support_knowledge_base]' "$PAGE" || fail "Bundled page shortcode missing."

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
"$PYTHON_BIN" - "$CSS" <<'PY'
import re
import sys
from pathlib import Path
path = Path(sys.argv[1])
text = path.read_text(encoding='utf-8')
clean = re.sub(r'/\*.*?\*/', '', text, flags=re.S)
level = 0
for index, char in enumerate(clean):
    if char == '{': level += 1
    elif char == '}':
        level -= 1
        if level < 0: raise SystemExit(f'CSS closes a block too early at offset {index}')
if level != 0: raise SystemExit(f'CSS block imbalance: {level}')
if '</style>' in text.lower(): raise SystemExit('CSS contains a forbidden </style> tag')
print(f'PASS - balanced stylesheet ({len(text.splitlines())} lines)')
PY

printf '==> FastAPI backend tests\n'
PYTHONPATH="$ROOT_DIR/backend" "$PYTHON_BIN" -m pytest "$ROOT_DIR/backend/tests" -q

printf '==> Release manifest\n'
"$PYTHON_BIN" - "$MANIFEST" <<'PY'
import json, sys
manifest = json.load(open(sys.argv[1], encoding='utf-8'))
assert manifest['name'] == 'Sustainable Catalyst Product Support and Feedback Platform'
assert manifest['legacy_name'] == 'Sustainable Catalyst Feature Suggestions'
assert manifest['slug'] == 'sustainable-catalyst-feature-suggestions'
assert manifest['version'] == '5.2.5'
assert manifest['public_support_archive'] == '/support/knowledge-base/'
assert manifest['dedicated_knowledge_base_shortcode'] == '[scfs_support_knowledge_base]'
print('PASS - release identity and compatibility fields')
PY

printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
