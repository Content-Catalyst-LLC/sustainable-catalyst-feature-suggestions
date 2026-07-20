#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="6.5.0"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN="$ROOT/wordpress/sustainable-catalyst-feature-suggestions"
PYTHON_BIN="${PYTHON_BIN:-python3}"
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
printf '==> Product Support and Feedback Platform v%s validation\n' "$VERSION"

printf '==> PHP syntax\n'
php_count="$(find "$PLUGIN" -type f -name '*.php' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.php' -print0 | xargs -0 -P 8 -n 1 php -l >/dev/null || fail "PHP syntax failed."
printf 'PASS - %d plugin PHP files\n' "$php_count"

printf '==> WordPress contract suite\n'
contract_count="$(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | wc -l | tr -d ' ')"
find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print0 | xargs -0 -P 8 -n 1 php >/dev/null || fail "PHP contract suite failed."
printf 'PASS - %d PHP contract files\n' "$contract_count"

printf '==> JavaScript syntax\n'
js_count="$(find "$PLUGIN" -type f -name '*.js' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.js' -print0 | xargs -0 -P 8 -n 1 node --check >/dev/null || fail "JavaScript syntax failed."
printf 'PASS - %d JavaScript files\n' "$js_count"

printf '==> JSON and Python syntax\n'
read -r json_count python_count < <("$PYTHON_BIN" - "$ROOT" <<'PYSYNTAX'
from pathlib import Path
import json, py_compile, sys
root=Path(sys.argv[1]); json_count=0; python_count=0
for path in root.rglob('*.json'):
    if any(part in {'.git','.venv','venv','__pycache__'} for part in path.parts): continue
    try: json.loads(path.read_text(encoding='utf-8'))
    except Exception as exc: raise SystemExit(f'JSON syntax failed: {path}: {exc}')
    json_count += 1
for path in (root/'backend').rglob('*.py'):
    if '__pycache__' in path.parts: continue
    try: py_compile.compile(str(path), doraise=True)
    except Exception as exc: raise SystemExit(f'Python syntax failed: {path}: {exc}')
    python_count += 1
print(json_count, python_count)
PYSYNTAX
)
printf 'PASS - %d JSON files and %d Python files\n' "$json_count" "$python_count"

printf '==> Source whitespace compatibility\n'
"$PYTHON_BIN" - "$ROOT" <<'PYSPACE'
from pathlib import Path
import sys
root=Path(sys.argv[1]); failures=[]
text_ext={'.php','.py','.js','.css','.json','.md','.txt','.sh','.yml','.yaml','.html','.csv'}
for p in root.rglob('*'):
    if not p.is_file() or any(part in {'.git','.venv','venv','__pycache__'} for part in p.parts) or p.suffix.lower() not in text_ext: continue
    data=p.read_bytes()
    try: text=data.decode('utf-8')
    except UnicodeDecodeError: continue
    lines=text.splitlines()
    for i,line in enumerate(lines,1):
        if line.rstrip(' \t') != line: failures.append(f'{p.relative_to(root)}:{i}: trailing whitespace')
    if text.endswith('\n\n'): failures.append(f'{p.relative_to(root)}: blank EOF lines')
if failures:
    print('\n'.join(failures)); raise SystemExit(1)
PYSPACE
printf 'PASS - source tree contains no trailing whitespace or blank EOF lines\n'

printf '==> CSS structural validation\n'
css_count="$("$PYTHON_BIN" - "$PLUGIN" <<'PYCSS'
from pathlib import Path
import sys
root=Path(sys.argv[1]); files=sorted(root.rglob('*.css'))
for path in files:
    text=path.read_text(encoding='utf-8')
    if text.count('{') != text.count('}'):
        raise SystemExit(f'Unbalanced CSS: {path}')
print(len(files))
PYCSS
)"
printf 'PASS - %d balanced CSS layers\n' "$css_count"

printf '==> FastAPI backend tests\n'
(cd "$ROOT/backend" && "$PYTHON_BIN" -m pytest -q)

printf '==> Release manifests\n'
"$PYTHON_BIN" - "$ROOT" <<'PYMANIFEST'
from pathlib import Path
import json,sys
r=Path(sys.argv[1])
feature=json.loads((r/'feature_suggestions_manifest-v6.5.0.json').read_text())
release=json.loads((r/'release-manifest-v6.5.0.json').read_text())
schema=json.loads((r/'schemas/scfs-help-desk-secure-evidence-v1.schema.json').read_text())
example=json.loads((r/'examples/help-desk-secure-evidence-v6.5.0.json').read_text())
evidence=feature['help_desk_secure_evidence']
assert feature['version']=='6.5.0'
assert evidence['schema']=='scfs-help-desk-secure-evidence/1.0'
assert evidence['db_version']=='1.4.0'
for key in ('private_evidence_intakes','delegated_attachment_registration','diagnostic_bundle_manifests','sha256_attachment_integrity','malware_scan_state','redaction_state','retention_review','hash_only_access_grants','append_only_attachment_events','human_review_required'):
    assert evidence[key] is True,key
for key in ('media_library_storage','raw_download_urls_stored','raw_grant_secrets_stored','public_attachment_api','automatic_redaction','automatic_deletion'):
    assert evidence[key] is False,key
assert evidence['identity_authority']=='contact-engagement'
assert evidence['attachment_authority']=='contact-engagement'
assert release['version']=='6.5.0'
assert release['release_name']=='Secure Evidence, Attachments, and Diagnostic Intake'
assert release['compatibility']['existing_private_case_data_migration_required'] is False
assert release['compatibility']['existing_secure_evidence_data_migration_required'] is False
assert release['compatibility']['additive_secure_evidence_schema_activation_required'] is True
assert schema['properties']['version']['const']=='6.5.0'
assert schema['properties']['schema']['const']=='scfs-help-desk-secure-evidence/1.0'
assert example['schema']=='scfs-help-desk-secure-evidence/1.0'
assert example['privacy']['media_library_storage'] is False
assert example['governance']['automatic_deletion'] is False
print('PASS - release identity, compatibility, evidence schema, privacy, and governance fields')
PYMANIFEST
printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
