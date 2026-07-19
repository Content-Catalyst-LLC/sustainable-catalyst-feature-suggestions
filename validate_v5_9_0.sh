#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="5.9.0"
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
INTEGRATION="$PLUGIN_DIR/includes/class-scfs-public-support-integrations.php"
INTEGRATION_CSS="$PLUGIN_DIR/assets/public-support-integrations.css"
INTEGRATION_JS="$PLUGIN_DIR/assets/public-support-integrations.js"
MANIFEST="$ROOT_DIR/feature_suggestions_manifest.json"
RELEASE_MANIFEST="$ROOT_DIR/release-manifest-v5.9.0.json"
SCHEMA="$ROOT_DIR/schemas/scfs-public-support-integration-v1.schema.json"
EXAMPLE="$ROOT_DIR/examples/public-support-integration-v5.9.0.json"
for path in "$MAIN" "$KB" "$SUPPORT" "$INTEGRATION" "$INTEGRATION_CSS" "$INTEGRATION_JS" "$MANIFEST" "$RELEASE_MANIFEST" "$SCHEMA" "$EXAMPLE" "$ROOT_DIR/backend/app/public_support_integrations.py" "$ROOT_DIR/backend/tests/test_public_support_integrations.py" "$ROOT_DIR/docs/public-api-embeds-institutional-support-v5.9.0.md"; do [ -f "$path" ] || fail "Missing required release file: ${path#$ROOT_DIR/}"; done
grep -Fq 'Version: 5.9.0' "$MAIN" || fail "Plugin version header mismatch."
grep -Fq "const VERSION = '5.9.0';" "$MAIN" || fail "Runtime version mismatch."
grep -Fq "const POST_TYPE = 'sc_feature_suggest';" "$MAIN" || fail "Legacy suggestion post type changed."
grep -Fq "const REST_NAMESPACE = 'scfs/v1';" "$MAIN" || fail "Legacy REST namespace changed."
grep -Fq "const ARTICLE_POST_TYPE = 'sc_support_article';" "$KB" || fail "Support Article CPT changed."
grep -Fq "'rewrite' => array('slug' => 'support/guides'" "$KB" || fail "Support Article permalink base changed."
grep -Fq 'final class SCFS_Public_Support_Integrations' "$INTEGRATION" || fail "Public integrations class missing."
grep -Fq "const SCHEMA = 'scfs-public-support-integration/1.0';" "$INTEGRATION" || fail "Integration schema mismatch."
grep -Fq '/public-support/version/verify' "$INTEGRATION" || fail "Version route missing."
grep -Fq '/institutional-support/contracts' "$INTEGRATION" || fail "Institution contract route missing."
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
root=Path(sys.argv[1]); ext={'.css','.html','.ini','.js','.json','.md','.php','.py','.sh','.txt','.xml','.yaml','.yml'}; excluded={'.git','.pytest_cache','.venv','__pycache__','venv'}; bad=[]
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
"$PYTHON_BIN" - "$PLUGIN_DIR/assets/knowledge-base.css" "$PLUGIN_DIR/assets/product-support-platform.css" "$PLUGIN_DIR/assets/support-article-integrity.css" "$PLUGIN_DIR/assets/unified-support-search.css" "$PLUGIN_DIR/assets/issue-release-intelligence.css" "$PLUGIN_DIR/assets/support-content-governance.css" "$PLUGIN_DIR/assets/feedback-product-signals.css" "$PLUGIN_DIR/assets/support-analytics-documentation-effectiveness.css" "$PLUGIN_DIR/assets/cross-product-support-graph.css" "$INTEGRATION_CSS" <<'PYCSS'
import re,sys
from pathlib import Path
for raw in sys.argv[1:]:
 p=Path(raw); text=p.read_text(); clean=re.sub(r'/\*.*?\*/','',text,flags=re.S); level=0
 for i,ch in enumerate(clean):
  if ch=='{': level+=1
  elif ch=='}':
   level-=1
   if level<0: raise SystemExit(f'{p.name} closes early at {i}')
 if level: raise SystemExit(f'{p.name} imbalance {level}')
 if '</style>' in text.lower(): raise SystemExit(f'{p.name} contains forbidden style tag')
 print(f'PASS - {p.name} balanced ({len(text.splitlines())} lines)')
PYCSS
printf '==> FastAPI backend tests\n'
PYTHONPATH="$ROOT_DIR/backend" "$PYTHON_BIN" -m pytest "$ROOT_DIR/backend/tests" -q
printf '==> Release manifests\n'
"$PYTHON_BIN" - "$MANIFEST" "$RELEASE_MANIFEST" "$SCHEMA" "$EXAMPLE" <<'PYMANIFEST'
import json,sys
m=json.load(open(sys.argv[1])); r=json.load(open(sys.argv[2])); s=json.load(open(sys.argv[3])); e=json.load(open(sys.argv[4]))
assert m['name']=='Sustainable Catalyst Product Support and Feedback Platform'
assert m['slug']=='sustainable-catalyst-feature-suggestions'
assert m['version']=='5.9.0'
assert m['release_name']=='Public API, Embeds, and Institutional Support Integration'
assert m['compatibility']['rest_namespace']=='scfs/v1'
assert m['compatibility']['database_migration_required'] is False
p=m['public_support_integrations']
for key in ('public_product_catalog','product_support_contracts','version_verification','product_scoped_embeds','responsive_embeds','institutional_contracts','origin_allowlists','optional_public_api_keys','request_rate_governance','cache_and_etag_metadata','cross_platform_handoffs','public_records_only','read_only_public_api','human_review_required'): assert p[key] is True,key
for key in ('personal_identifiers_exposed','private_case_content_exposed','private_documents_exposed','contact_records_exposed','automatic_private_case_creation','automatic_publication','automatic_issue_resolution','automatic_release_change','database_migration_required'): assert p[key] is False,key
assert r['version']=='5.9.0' and r['support_article_permalink_base']=='/support/guides/'
assert s['properties']['version']['const']=='5.9.0'
assert e['schema']=='scfs-public-support-integration/1.0'
assert e['privacy']['private_case_content_exposed'] is False
print('PASS - release identity, compatibility, privacy, and public integration fields')
PYMANIFEST
printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
