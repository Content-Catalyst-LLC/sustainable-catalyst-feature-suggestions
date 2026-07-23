#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="6.11.0"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN="$ROOT/wordpress/sustainable-catalyst-feature-suggestions"
PYTHON_BIN="${PYTHON_BIN:-python3}"
CONTRACT_EXECUTION_MODE="sequential-state-safe"
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
printf '==> Product Support and Feedback Platform v%s validation\n' "$VERSION"
printf '==> PHP syntax\n'
php_count="$(find "$PLUGIN" -type f -name '*.php' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.php' -print0 | xargs -0 -P 8 -n 1 php -l >/dev/null || fail "PHP syntax failed."
printf 'PASS - %d plugin PHP files\n' "$php_count"
printf '==> WordPress contract suite (%s)\n' "$CONTRACT_EXECUTION_MODE"
contract_count="$(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | wc -l | tr -d ' ')"
contract_log="$(mktemp "${TMPDIR:-/tmp}/scfs-v6110-contract.XXXXXX")"
while IFS= read -r contract_file; do
  if ! php "$contract_file" >"$contract_log" 2>&1; then cat "$contract_log" >&2; rm -f "$contract_log"; fail "PHP contract failed: ${contract_file#$ROOT/}"; fi
done < <(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | LC_ALL=C sort)
rm -f "$contract_log"
printf 'PASS - %d PHP contract files executed sequentially\n' "$contract_count"
printf '==> Post-contract release identity integrity\n'
php -r '$plugin=file_get_contents($argv[1]);if(!preg_match("/^[[:space:]]*\\*[[:space:]]*Version:[[:space:]]*6\\.11\\.0[[:space:]]*$/m",$plugin)){exit(1);}if(!preg_match("/const VERSION = '\''6\\.11\\.0'\'';/",$plugin)){exit(1);}' "$PLUGIN/sustainable-catalyst-feature-suggestions.php" || fail "Post-contract plugin identity check failed."
"$PYTHON_BIN" - "$ROOT/feature_suggestions_manifest.json" <<'PYIDENTITY'
from pathlib import Path
import json,sys
m=json.loads(Path(sys.argv[1]).read_text());assert m.get('version')=='6.11.0';print('PASS - plugin header, runtime version, and feature manifest remain v6.11.0')
PYIDENTITY
printf '==> JavaScript syntax\n'
js_count="$(find "$PLUGIN" -type f -name '*.js' -print | wc -l | tr -d ' ')"
find "$PLUGIN" -type f -name '*.js' -print0 | xargs -0 -P 8 -n 1 node --check >/dev/null || fail "JavaScript syntax failed."
printf 'PASS - %d JavaScript files\n' "$js_count"
printf '==> JSON and Python syntax\n'
read -r json_count python_count < <("$PYTHON_BIN" - "$ROOT" <<'PYSYNTAX'
from pathlib import Path
import json,py_compile,sys
root=Path(sys.argv[1]);jc=pc=0
for p in root.rglob('*.json'):
 if any(x in {'.git','.venv','venv','__pycache__'} for x in p.parts):continue
 json.loads(p.read_text());jc+=1
for p in (root/'backend').rglob('*.py'):
 if '__pycache__' in p.parts:continue
 py_compile.compile(str(p),doraise=True);pc+=1
print(jc,pc)
PYSYNTAX
)
printf 'PASS - %d JSON files and %d Python files\n' "$json_count" "$python_count"
printf '==> Source whitespace compatibility\n'
"$PYTHON_BIN" - "$ROOT" <<'PYSPACE'
from pathlib import Path
import sys
root=Path(sys.argv[1]);failures=[];exts={'.php','.py','.js','.css','.json','.md','.txt','.sh','.yml','.yaml','.html','.csv'}
for p in root.rglob('*'):
 if not p.is_file() or any(x in {'.git','.venv','venv','__pycache__'} for x in p.parts) or p.suffix.lower() not in exts:continue
 try:text=p.read_text()
 except UnicodeDecodeError:continue
 for i,line in enumerate(text.splitlines(),1):
  if line.rstrip(' \t')!=line:failures.append(f'{p.relative_to(root)}:{i}: trailing whitespace')
 if text.endswith('\n\n'):failures.append(f'{p.relative_to(root)}: blank EOF lines')
if failures:print('\n'.join(failures));raise SystemExit(1)
PYSPACE
printf 'PASS - source tree contains no trailing whitespace or blank EOF lines\n'
printf '==> CSS structural validation\n'
css_count="$("$PYTHON_BIN" - "$PLUGIN" <<'PYCSS'
from pathlib import Path
import sys
files=sorted(Path(sys.argv[1]).rglob('*.css'))
for p in files:
 t=p.read_text();assert t.count('{')==t.count('}'),f'Unbalanced CSS: {p}'
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
r=Path(sys.argv[1]);f=json.loads((r/'feature_suggestions_manifest-v6.11.0.json').read_text());m=json.loads((r/'release-manifest-v6.11.0.json').read_text());s=json.loads((r/'schemas/scfs-help-desk-api-integrations-v1.schema.json').read_text());e=json.loads((r/'examples/help-desk-api-integrations-v6.11.0.json').read_text());c=f['help_desk_api_integrations']
assert f['version']=='6.11.0';assert m['version']=='6.11.0';assert m['release_name']=='API, Webhooks, and External Integrations';assert c['schema']=='scfs-help-desk-api-integrations/1.0';assert c['db_version']=='2.0.0'
for k in ('scoped_case_api','signed_outbound_webhooks','delivery_retry_queue','dead_letter_review','external_system_links','integration_checkpoints','append_only_audit_events','github_repository_links','monitoring_handoffs','institutional_connectors','contact_engagement_interoperability','authenticated_rest_api','wp_cli','human_authorization_required'):assert c[k] is True,k
for k in ('public_case_api','public_inbound_webhook','raw_secrets_stored','requester_identity_exposed','private_message_content_exposed','attachment_content_exposed','automatic_external_issue_creation','automatic_case_transition','automatic_customer_communication'):assert c[k] is False,k
assert c['credential_authority']=='environment-or-contact-engagement';assert len(c['additive_tables'])==9
assert m['compatibility']['existing_api_integration_data_migration_required'] is False;assert m['compatibility']['additive_api_integration_schema_activation_required'] is True;assert s['properties']['version']['const']=='6.11.0';assert e['governance']['public_inbound_webhook'] is False;assert e['governance']['raw_secrets_stored'] is False
print('PASS - release identity, compatibility, API integration schema, signing, privacy, and governance fields')
PYMANIFEST
printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
