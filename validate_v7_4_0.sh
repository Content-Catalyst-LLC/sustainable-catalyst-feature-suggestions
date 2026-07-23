#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="7.4.0"
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
contract_log="$(mktemp "${TMPDIR:-/tmp}/scpsf-v740-contract.XXXXXX")"
while IFS= read -r contract_file; do
  if ! php "$contract_file" >"$contract_log" 2>&1; then
    cat "$contract_log" >&2
    rm -f "$contract_log"
    fail "PHP contract failed: ${contract_file#$ROOT/}"
  fi
done < <(find "$ROOT/tests" -maxdepth 1 -type f -name '*.php' -print | LC_ALL=C sort)
rm -f "$contract_log"
printf 'PASS - %d PHP contract files executed sequentially\n' "$contract_count"
printf '==> Runtime and WordPress compatibility identity\n'
php -r '$plugin=file_get_contents($argv[1]);if(!preg_match("/^[[:space:]]*\\*[[:space:]]*Plugin Name:[[:space:]]*Sustainable Catalyst Product Support and Feedback Platform[[:space:]]*$/m",$plugin)){exit(1);}if(!preg_match("/^[[:space:]]*\\*[[:space:]]*Version:[[:space:]]*7\\.4\\.0[[:space:]]*$/m",$plugin)){exit(1);}if(!preg_match("/const VERSION = '\''7\\.4\\.0'\'';/",$plugin)){exit(1);}if(!preg_match("/^[[:space:]]*\\*[[:space:]]*Text Domain:[[:space:]]*sustainable-catalyst-feature-suggestions[[:space:]]*$/m",$plugin)){exit(1);}' "$PLUGIN/sustainable-catalyst-feature-suggestions.php" || fail "Plugin identity or compatibility check failed."
[ ! -d "$ROOT/wordpress/sustainable-catalyst-product-support-feedback" ] || fail "WordPress plugin directory must not be renamed."
printf 'PASS - public name and v%s runtime with legacy WordPress directory and text domain preserved\n' "$VERSION"
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
    if any(x in {'.git','.venv','venv','__pycache__','.pytest_cache'} for x in p.parts): continue
    json.loads(p.read_text());jc+=1
for p in (root/'backend').rglob('*.py'):
    if any(x in {'__pycache__','.pytest_cache'} for x in p.parts): continue
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
    if not p.is_file() or any(x in {'.git','.venv','venv','__pycache__','.pytest_cache'} for x in p.parts) or p.suffix.lower() not in exts: continue
    try: text=p.read_text()
    except UnicodeDecodeError: continue
    for i,line in enumerate(text.splitlines(),1):
        if line.rstrip(' \t')!=line: failures.append(f'{p.relative_to(root)}:{i}: trailing whitespace')
    if text.endswith('\n\n'): failures.append(f'{p.relative_to(root)}: blank EOF lines')
if failures:
    print('\n'.join(failures));raise SystemExit(1)
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
printf '==> Product registry governance manifests and schemas\n'
"$PYTHON_BIN" - "$ROOT" <<'PYMANIFEST'
from pathlib import Path
import json,sys
r=Path(sys.argv[1])
current=json.loads((r/'feature_suggestions_manifest.json').read_text())
versioned=json.loads((r/'feature_suggestions_manifest-v7.4.0.json').read_text())
historical=json.loads((r/'feature_suggestions_manifest-v7.2.1.json').read_text())
release=json.loads((r/'release-manifest-v7.4.0.json').read_text())
example=json.loads((r/'examples/release-board-v7.4.0.json').read_text())
schema=json.loads((r/'schemas/scfs-release-board-v1.schema.json').read_text())
registry_schema=json.loads((r/'schemas/scfs-canonical-product-registry-v2.schema.json').read_text())
assert current==versioned
assert current['version']=='7.4.0'
assert current['release_name']=='Product Registry Governance'
assert current['repository']=='Content-Catalyst-LLC/sustainable-catalyst-product-support-feedback'
board=current['release_board']
assert board['schema']=='scfs-release-board/1.2'
assert board['shortcode']=='sc_release_board'
assert board['layouts']==['terminal','blackboard','compact','directory']
assert board['default_layout']=='terminal'
assert board['public_title']=='Release Console'
assert board['rotating_screens']==['foundation','research-intelligence','data-analysis','creation-systems','commercial']
assert board['default_interval_seconds']==7
assert board['previous_pause_next_controls'] is True
assert board['pause_on_hover_and_focus'] is True
assert board['reduced_motion_respected'] is True
assert board['product_labels_navigating'] is False
assert board['footer_links_only'] is True
for key in ('stable_screen_height','controls_hidden_without_javascript','all_screens_visible_without_javascript','multiple_instances_supported','duplicate_initialization_guard','dynamic_dom_initialization','screen_reader_announcements_manual_only','astra_theme_scoped','minification_safe','mobile_control_alignment_repaired','footer_position_stable'):
    assert board[key] is True,key
assert board['keyboard_navigation']==['ArrowLeft','ArrowRight','Home','End','Space']
for key in ('canonical_registry_source','installed_and_manual_versions_combined','homepage_visibility_governed','inactive_products_hidden_by_default','semantic_list_output','status_text_visible','responsive_without_javascript','cache_safe_asset_versioning','terminal_command_header','registry_source_counts','knowledge_library_homepage_required','live_intelligence_visual_parity'):
    assert board[key] is True,key
for key in ('color_only_status','private_plugin_paths_exposed','private_repository_metadata_exposed'):
    assert board[key] is False,key
assert board['manual_commercial_product']=='catalyst-intelligence'
assert board['required_foundation_products']==['sustainable-catalyst-core','product-support-feedback','contact-engagement','knowledge-library']
assert board['analytics_r_public_label']=='Analytics R'
registry=current['canonical_product_registry']
assert registry['schema']=='scfs-canonical-product-registry/2.0'
assert registry['schema_file']=='schemas/scfs-canonical-product-registry-v2.schema.json'
assert registry['lifecycle_states']==['active','planned','maintenance','superseded','retired']
assert registry['version_precedence']==['manual','discovered','installed']
for key in ('canonical_id_immutable','internal_name_governed','private_repository_identity_governed','console_screen_assignment_governed','lifecycle_state_governed','version_precedence_explicit','verification_provenance_governed','stale_detection','duplicate_detection','supersession_validation','schema_migration_supported','integrity_rest_api','migration_rest_api','wp_cli_validate','wp_cli_migrate'):
    assert registry[key] is True,key
assert registry['private_repository_fields_publicly_exposed'] is False
assert registry['stale_after_days']==90
assert historical['version']=='7.2.1'
assert release['version']=='7.4.0'
assert release['release_name']=='Product Registry Governance'
assert release['compatibility']['database_migration_required'] is False
assert example['schema']==board['schema']
assert example['version']=='7.4.0'
assert schema['properties']['version']['const']=='7.4.0'
assert registry_schema['$id']=='https://sustainablecatalyst.com/schemas/scfs-canonical-product-registry-v2.schema.json'
assert registry_schema['properties']['schema']['const']=='scfs-canonical-product-registry/2.0'
assert registry_schema['properties']['version']['const']=='7.4.0'
print('PASS - Release Console identity, presentation, labels, governance, privacy, accessibility, and compatibility contracts validated')
PYMANIFEST
printf '==> Release Console and registry governance source contracts\n'
BOARD_CLASS="$PLUGIN/includes/class-scfs-release-board.php"
BOARD_CSS="$PLUGIN/assets/release-board-v7.4.0.css"
BOARD_JS="$PLUGIN/assets/release-console-v7.4.0.js"
[ -f "$BOARD_CLASS" ] || fail "Missing release board class."
[ -f "$BOARD_CSS" ] || fail "Missing release board stylesheet."
[ -f "$BOARD_JS" ] || fail "Missing release console script."
grep -Fq "const VERSION = '7.4.0';" "$BOARD_CLASS" || fail "Release board version mismatch."
grep -Fq "const SHORTCODE = 'sc_release_board';" "$BOARD_CLASS" || fail "Release board shortcode missing."
grep -Fq 'SCFS_Canonical_Product_Registry::instance()->public_products' "$BOARD_CLASS" || fail "Canonical registry projection missing."
for attribute in layout context groups products limit show_status show_updated show_links show_header show_footer show_source dense inactive title heading_level interval rotate; do grep -Fq "'$attribute'" "$BOARD_CLASS" || fail "Shortcode attribute missing: $attribute"; done
for filter in scfs_release_board_shortcode_atts scfs_release_board_products scfs_release_board_output scfs_release_board_cache_ttl; do grep -Fq "$filter" "$BOARD_CLASS" || fail "Release board filter missing: $filter"; done
grep -Fq 'scfs_product_registry_updated' "$BOARD_CLASS" || fail "Registry cache invalidation missing."
grep -Fq 'scfs_installed_plugin_discovery_completed' "$BOARD_CLASS" || fail "Discovery cache invalidation missing."
grep -Fq "'layout' => 'terminal'" "$BOARD_CLASS" || fail "Terminal default layout missing."
grep -Fq "__('Release Console'" "$BOARD_CLASS" || fail "Release Console title missing."
grep -Fq 'scfs-release-board__command-line' "$BOARD_CLASS" || fail "Terminal command header missing."
grep -Fq 'telemetry_counts' "$BOARD_CLASS" || fail "Registry source counts missing."
REGISTRY_CLASS="$PLUGIN/includes/class-scfs-canonical-product-registry.php"
DISCOVERY_CLASS="$PLUGIN/includes/class-scfs-installed-plugin-discovery.php"
[ -f "$REGISTRY_CLASS" ] || fail "Missing canonical product registry class."
[ -f "$DISCOVERY_CLASS" ] || fail "Missing installed-plugin discovery class."
grep -Fq "const SCHEMA = 'scfs-canonical-product-registry/2.0';" "$REGISTRY_CLASS" || fail "Registry schema 2.0 is missing."
grep -Fq 'const STALE_AFTER_DAYS = 90;' "$REGISTRY_CLASS" || fail "Registry stale threshold is missing."
for method in apply_v740_governance_migrations integrity_report resolved_version is_stale_record registry_fingerprint; do grep -Fq "function $method" "$REGISTRY_CLASS" || fail "Registry governance method missing: $method"; done
for route in product-registry/integrity product-registry/migrations; do grep -Fq "$route" "$REGISTRY_CLASS" || fail "Registry governance route missing: $route"; done
grep -Fq "'console_screen'" "$BOARD_CLASS" || fail "Governed console-screen projection is missing."
grep -Fq "'superseded'" "$BOARD_CLASS" || fail "Superseded product suppression is missing."
grep -Fq "'retired'" "$BOARD_CLASS" || fail "Retired product suppression is missing."
grep -Fq "\$record['verification_source'] = 'wordpress_plugin';" "$DISCOVERY_CLASS" || fail "Installed-plugin verification provenance is missing."
grep -Fq 'data-console-action="previous"' "$BOARD_CLASS" || fail "Previous control missing."
grep -Fq 'data-console-action="toggle"' "$BOARD_CLASS" || fail "Pause/play control missing."
grep -Fq 'data-console-action="next"' "$BOARD_CLASS" || fail "Next control missing."
grep -Fq 'prefers-reduced-motion: reduce' "$BOARD_JS" || fail "Reduced-motion support missing."
grep -Fq "getAttribute('data-console-ready') === 'true'" "$BOARD_JS" || fail "Duplicate initialization guard missing."
grep -Fq 'MutationObserver' "$BOARD_JS" || fail "Dynamic console initialization missing."
grep -Fq "event.key === 'ArrowLeft'" "$BOARD_JS" || fail "Left Arrow keyboard navigation missing."
grep -Fq "event.key === 'ArrowRight'" "$BOARD_JS" || fail "Right Arrow keyboard navigation missing."
grep -Fq "event.key === 'Home'" "$BOARD_JS" || fail "Home keyboard navigation missing."
grep -Fq "event.key === 'End'" "$BOARD_JS" || fail "End keyboard navigation missing."
grep -Fq 'show(index + 1, false)' "$BOARD_JS" || fail "Automatic rotation must remain silent to assistive technology."
grep -Fq '<noscript>' "$BOARD_CLASS" || fail "No-JavaScript fallback notice missing."
grep -Fq 'data-console-announcer' "$BOARD_CLASS" || fail "Manual screen reader announcer missing."
grep -Fq 'aria-controls="<?php echo esc_attr($screens_id); ?>"' "$BOARD_CLASS" || fail "Unique ARIA control relationships missing."
grep -Fq '.scfs-release-board--enhanced .scfs-release-board__console-controls' "$BOARD_CSS" || fail "Enhanced-only control display missing."
grep -Fq 'grid-column: 1;' "$BOARD_CSS" || fail "Stable screen overlay grid missing."
grep -Fq '[data-console-active="true"]' "$BOARD_CSS" || fail "Active screen selector missing."
if grep -Fq '<a class="scfs-release-board__name"' "$BOARD_CLASS"; then fail "Product labels must not navigate."; fi
if grep -Fq '<a class="scfs-release-board__version"' "$BOARD_CLASS"; then fail "Version labels must not navigate."; fi
grep -Fq "'Analytics R'" "$PLUGIN/includes/class-scfs-canonical-product-registry.php" || fail "Analytics R label migration missing."
grep -Fq "\$records['knowledge-library']['homepage_visible'] = '1';" "$PLUGIN/includes/class-scfs-canonical-product-registry.php" || fail "Knowledge Library homepage migration missing."
if grep -Fq 'plugin_file' "$BOARD_CLASS"; then fail "Release board exposes plugin file metadata."; fi
printf 'PASS - shortcode, filters, cache invalidation, and public-data boundaries validated\n'
printf '==> Installer contract\n'
INSTALLER="$ROOT/install_and_push_sustainable_catalyst_product_support_feedback_v7_4_0_macos.sh"
[ -f "$INSTALLER" ] || fail "Missing v7.4.0 installer."
bash -n "$INSTALLER" || fail "Invalid installer shell syntax."
grep -Fq 'git@github.com:Content-Catalyst-LLC/sustainable-catalyst-product-support-feedback.git' "$INSTALLER" || fail "Installer does not use canonical remote."
grep -Fq 'sustainable-catalyst-feature-suggestions' "$INSTALLER" || fail "Installer does not preserve the WordPress compatibility slug."
grep -Fq 'SHA256SUMS' "$INSTALLER" || fail "Installer does not require checksums."
grep -Fq 'shasum -a 256 -c' "$INSTALLER" || fail "Installer does not verify checksums."
if grep -Eq '(^|[[:space:]])(mapfile|readarray)([[:space:]]|$)' "$INSTALLER" "$ROOT/validate_v7_4_0.sh"; then fail "Release scripts require Bash 4."; fi
printf 'PASS - canonical installer and Bash 3.2 compatibility validated\n'
printf '==> FastAPI backend tests\n'
(cd "$ROOT/backend" && "$PYTHON_BIN" -m pytest -q)
printf '\nVALIDATION PASSED: Product Support and Feedback Platform v%s\n' "$VERSION"
