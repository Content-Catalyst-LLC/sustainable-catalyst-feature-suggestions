#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="6.0.0"
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
CONNECTED="$PLUGIN_DIR/includes/class-scfs-connected-product-support-platform.php"
CONNECTED_CSS="$PLUGIN_DIR/assets/connected-product-support-platform.css"
CONNECTED_JS="$PLUGIN_DIR/assets/connected-product-support-platform.js"
MANIFEST="$ROOT_DIR/feature_suggestions_manifest.json"
RELEASE_MANIFEST="$ROOT_DIR/release-manifest-v6.0.0.json"
SCHEMA="$ROOT_DIR/schemas/scfs-connected-product-support-feedback-platform-v1.schema.json"
EXAMPLE="$ROOT_DIR/examples/connected-product-support-platform-v6.0.0.json"
for path in "$MAIN" "$KB" "$SUPPORT" "$CONNECTED" "$CONNECTED_CSS" "$CONNECTED_JS" "$MANIFEST" "$RELEASE_MANIFEST" "$SCHEMA" "$EXAMPLE" "$ROOT_DIR/backend/app/connected_product_support_platform.py" "$ROOT_DIR/backend/tests/test_connected_product_support_platform.py" "$ROOT_DIR/docs/connected-product-support-feedback-platform-v6.0.0.md"; do [ -f "$path" ] || fail "Missing required release file: ${path#$ROOT_DIR/}"; done
grep -Fq 'Version: 6.0.0' "$MAIN" || fail "Plugin version header mismatch."
grep -Fq "const VERSION = '6.0.0';" "$MAIN" || fail "Runtime version mismatch."
grep -Fq "const POST_TYPE = 'sc_feature_suggest';" "$MAIN" || fail "Legacy suggestion post type changed."
grep -Fq "const REST_NAMESPACE = 'scfs/v1';" "$MAIN" || fail "Legacy REST namespace changed."
grep -Fq "const ARTICLE_POST_TYPE = 'sc_support_article';" "$KB" || fail "Support Article CPT changed."
grep -Fq "'rewrite' => array('slug' => 'support/guides'" "$KB" || fail "Support Article permalink base changed."
grep -Fq 'final class SCFS_Connected_Product_Support_Platform' "$CONNECTED" || fail "Connected platform class missing."
grep -Fq "const SCHEMA = 'scfs-connected-product-support-feedback-platform/1.0';" "$CONNECTED" || fail "Connected platform schema mismatch."
grep -Fq '/connected-platform/overview' "$CONNECTED" || fail "Connected overview route missing."
grep -Fq '/connected-platform/journey' "$CONNECTED" || fail "Connected journey route missing."
printf '==> PHP syntax\n'
php_count=0
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; php_count=$((php_count + 1)); done < <(find "$PLUGIN_DIR" -type f -name '*.php' -print0)
printf 'PASS - %d plugin PHP files\n' "$php_count"
printf '==> WordPress contract suite\n'
test_count=0
while IFS= read -r file; do php "$file" >/dev/null || fail "WordPress contract failed: ${file#$ROOT_DIR/}"; test_count=$((test_count + 1)); done < <(find "$ROOT_DIR/tests" -maxdepth 1 -type f -name 'test-*.php' | sort)
printf 'PASS - %d PHP contract files\n' "$test_count"
printf '==> JavaScript syntax\n'
js_count=0
if command -v node >/dev/null 2>&1; then while IFS= read -r -d '' file; do node --check "$file" >/dev/null; js_count=$((js_count + 1)); done < <(find "$PLUGIN_DIR/assets" -type f -name '*.js' -print0); printf 'PASS - %d JavaScript files\n' "$js_count"; else printf 'SKIP - Node.js unavailable.\n'; fi
printf '==> JSON and Python syntax\n'
"$PYTHON_BIN" - "$ROOT_DIR" <<'PYVALID'
import ast,json,sys
from pathlib import Path
root=Path(sys.argv[1]); excluded={'.git','.venv','venv','__pycache__','.pytest_cache'}
json_files=[p for p in root.rglob('*.json') if not any(x in excluded for x in p.parts)]
for p in json_files: json.loads(p.read_text())
py_files=[p for p in root.rglob('*.py') if not any(x in excluded for x in p.parts)]
for p in py_files: ast.parse(p.read_text(),filename=str(p))
print(f'PASS - {len(json_files)} JSON files and {len(py_files)} Python files')
PYVALID
printf '==> Source whitespace compatibility\n'
"$PYTHON_BIN" - "$ROOT_DIR" <<'PYSPACE'
import sys
from pathlib import Path
root=Path(sys.argv[1]); ext={'.css','.csv','.html','.ini','.js','.json','.md','.php','.py','.sh','.txt','.xml','.yaml','.yml'}; excluded={'.git','.pytest_cache','.venv','__pycache__','venv'}; bad=[]
for p in root.rglob('*'):
 if not p.is_file() or p.suffix.lower() not in ext or any(x in excluded for x in p.parts): continue
 try: lines=p.read_text().splitlines()
 except UnicodeDecodeError: continue
 for n,line in enumerate(lines,1):
  if line.endswith((' ','\t')): bad.append(f'{p.relative_to(root)}:{n}')
if bad: raise SystemExit('Trailing whitespace detected:\n'+'\n'.join(bad))
print('PASS - source tree contains no trailing whitespace')
PYSPACE
printf '==> CSS structural validation\n'
"$PYTHON_BIN" - "$PLUGIN_DIR/assets" <<'PYCSS'
import re,sys
from pathlib import Path
assets=Path(sys.argv[1])
css_files=sorted(p for p in assets.glob('*.css') if p.is_file())
if not css_files:
 raise SystemExit('No CSS layers found')
for p in css_files:
 text=p.read_text(); clean=re.sub(r'/\*.*?\*/','',text,flags=re.S); level=0
 for i,ch in enumerate(clean):
  if ch=='{': level+=1
  elif ch=='}':
   level-=1
   if level<0: raise SystemExit(f'{p.name} closes early at {i}')
 if level: raise SystemExit(f'{p.name} imbalance {level}')
 if '</style>' in text.lower(): raise SystemExit(f'{p.name} contains forbidden style tag')
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
import json,sys
m=json.load(open(sys.argv[1])); r=json.load(open(sys.argv[2])); s=json.load(open(sys.argv[3])); e=json.load(open(sys.argv[4]))
assert m['name']=='Sustainable Catalyst Product Support and Feedback Platform'
assert m['slug']=='sustainable-catalyst-feature-suggestions'
assert m['version']=='6.0.0'
assert m['release_name']=='Connected Product Support and Feedback Platform'
assert m['compatibility']['rest_namespace']=='scfs/v1'
assert m['connected_platform']['database_migration_required'] is False
assert r['version']=='6.0.0' and r['support_article_permalink_base']=='/support/guides/'
p=r['connected_platform']
for key in ('support_center','publication_library','operational_intelligence','feedback_intelligence','platform_integration','connected_product_dossiers','guided_resolution_journeys','cross_product_handoffs','public_api_and_embeds','institutional_contracts','platform_health','sha256_report_integrity','specialist_modules_remain_source_of_truth','public_records_only','human_review_required'): assert p[key] is True,key
for key in ('personal_identifiers_exposed','raw_search_text_persisted','private_case_content_exposed','private_documents_exposed','contact_records_exposed','automatic_publication','automatic_issue_resolution','automatic_release_change','automatic_roadmap_change','automatic_private_case_creation','automatic_redirect','database_migration_required'): assert p[key] is False,key
assert s['properties']['version']['const']=='6.0.0'
assert e['schema']=='scfs-connected-product-support-feedback-platform/1.0'
assert e['privacy']['private_case_content_exposed'] is False
print('PASS - release identity, compatibility, privacy, governance, and connected-platform fields')
PYMANIFEST
printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
