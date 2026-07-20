#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="6.1.0"
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
HELP_DESK="$PLUGIN_DIR/includes/class-scfs-help-desk-case-foundation.php"
HELP_DESK_CSS="$PLUGIN_DIR/assets/help-desk-case-foundation.css"
MANIFEST="$ROOT_DIR/feature_suggestions_manifest.json"
RELEASE_MANIFEST="$ROOT_DIR/release-manifest-v6.1.0.json"
SCHEMA="$ROOT_DIR/schemas/scfs-help-desk-case-v1.schema.json"
EXAMPLE="$ROOT_DIR/examples/help-desk-case-foundation-v6.1.0.json"

for path in \
  "$MAIN" "$KB" "$SUPPORT" "$HELP_DESK" "$HELP_DESK_CSS" \
  "$MANIFEST" "$RELEASE_MANIFEST" "$SCHEMA" "$EXAMPLE" \
  "$ROOT_DIR/backend/app/help_desk_case_foundation.py" \
  "$ROOT_DIR/backend/tests/test_help_desk_case_foundation.py" \
  "$ROOT_DIR/backend/requirements-validation.txt" \
  "$ROOT_DIR/docs/help-desk-case-foundation-v6.1.0.md" \
  "$ROOT_DIR/install_and_push_v6_1_0_macos.sh"; do
  [ -f "$path" ] || fail "Missing required release file: ${path#$ROOT_DIR/}"
done

grep -Fq 'Version: 6.1.0' "$MAIN" || fail "Plugin version header mismatch."
grep -Fq "const VERSION = '6.1.0';" "$MAIN" || fail "Runtime version mismatch."
grep -Fq "const POST_TYPE = 'sc_feature_suggest';" "$MAIN" || fail "Legacy suggestion post type changed."
grep -Fq "const REST_NAMESPACE = 'scfs/v1';" "$MAIN" || fail "Legacy REST namespace changed."
grep -Fq "const ARTICLE_POST_TYPE = 'sc_support_article';" "$KB" || fail "Support Article CPT changed."
grep -Fq "'rewrite' => array('slug' => 'support/guides'" "$KB" || fail "Support Article permalink base changed."
grep -Fq "const RELEASE_POST_TYPE = 'sc_release_record';" "$SUPPORT" || fail "Release CPT changed."
grep -Fq 'final class SCFS_Help_Desk_Case_Foundation' "$HELP_DESK" || fail "Help Desk class missing."
grep -Fq "const SCHEMA = 'scfs-help-desk-case/1.0';" "$HELP_DESK" || fail "Help Desk schema mismatch."
grep -Fq "'cases' => \$wpdb->prefix . 'scfs_cases'" "$HELP_DESK" || fail "Private case table missing."
grep -Fq "'public_case_api' => false" "$HELP_DESK" || fail "Public case API privacy boundary missing."
grep -Fq "'attachment_authority' => 'contact-engagement'" "$HELP_DESK" || fail "Attachment authority boundary missing."

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
for path in root.rglob('*'):
    if not path.is_file() or path.suffix.lower() not in extensions or any(x in excluded for x in path.parts):
        continue
    try:
        lines = path.read_text().splitlines()
    except UnicodeDecodeError:
        continue
    for number, line in enumerate(lines, 1):
        if line.endswith((' ', '\t')):
            bad.append(f'{path.relative_to(root)}:{number}')
if bad:
    raise SystemExit('Trailing whitespace detected:\n' + '\n'.join(bad))
print('PASS - source tree contains no trailing whitespace')
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
assert manifest['version'] == '6.1.0'
assert manifest['release_name'] == 'Help Desk Case Foundation'
assert manifest['compatibility']['rest_namespace'] == 'scfs/v1'

help_desk = manifest['help_desk_case_foundation']
assert help_desk['schema'] == 'scfs-help-desk-case/1.0'
assert help_desk['private_case_records'] is True
assert len(help_desk['private_tables']) == 8
assert help_desk['identity_authority'] == 'contact-engagement'
assert help_desk['attachment_authority'] == 'contact-engagement'
for key in ('public_case_api','public_case_shortcode','private_case_content_exposed','uploaded_files_stored_in_media_library','automatic_case_creation','automatic_case_resolution'):
    assert help_desk[key] is False, key
for key in ('validated_status_transitions','participants','threaded_messages','internal_notes','assignment_history','public_record_relationships','attachment_metadata_only','sla_event_foundation','append_only_audit_history','authenticated_rest_api','wp_cli','public_support_records_unchanged','additive_schema_activation_required','human_review_required'):
    assert help_desk[key] is True, key

assert release['version'] == '6.1.0'
assert release['support_article_permalink_base'] == '/support/guides/'
assert release['compatibility']['rest_namespace'] == 'scfs/v1'
assert release['compatibility']['existing_public_data_migration_required'] is False
assert release['compatibility']['additive_private_schema_activation_required'] is True
assert schema['properties']['version']['const'] == '6.1.0'
assert schema['properties']['schema']['const'] == 'scfs-help-desk-case/1.0'
assert example['schema'] == 'scfs-help-desk-case/1.0'
assert example['privacy']['public_case_api'] is False
assert example['privacy']['identity_authority'] == 'contact-engagement'
print('PASS - release identity, compatibility, private schema, privacy, and governance fields')
PYMANIFEST

printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
