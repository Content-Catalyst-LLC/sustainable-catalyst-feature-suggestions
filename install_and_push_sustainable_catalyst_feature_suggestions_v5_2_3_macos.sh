#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="5.2.3"
INSTALLER_REVISION="V5_2_2"
RELEASE_NAME="Dynamic Documentation Records, Permalink Integrity, and Knowledge Base Interface Refinement"
REPOSITORY="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git"
DOWNLOADS_DIR="${HOME}/Downloads"
WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/scfs-v522.XXXXXX")"
STAGE_DIR="${WORK_DIR}/stage"
CLONE_DIR="${WORK_DIR}/repository"
VENV_DIR="${WORK_DIR}/venv"
SUCCESS=0
cleanup(){ status=$?; if [ "$SUCCESS" -eq 1 ]; then rm -rf "$WORK_DIR"; else echo; echo "The v${VERSION} release was not committed or pushed."; echo "Temporary validation workspace retained at: $WORK_DIR"; fi; exit "$status"; }
trap cleanup EXIT

echo "Feature Suggestions v${VERSION} — ${RELEASE_NAME}"
echo "Installer revision: ${INSTALLER_REVISION}"
mkdir -p "$STAGE_DIR"

choose_python(){ for c in /opt/homebrew/bin/python3.13 /usr/local/bin/python3.13 python3.13 /opt/homebrew/bin/python3.12 /usr/local/bin/python3.12 python3.12 python3; do if command -v "$c" >/dev/null 2>&1 || [ -x "$c" ]; then "$c" -c 'import sys; raise SystemExit(0 if sys.version_info >= (3,12) else 1)' >/dev/null 2>&1 && { printf '%s' "$c"; return 0; }; fi; done; return 1; }
PYTHON_BIN="$(choose_python || true)"
[ -n "$PYTHON_BIN" ] || { echo "ERROR: Python 3.12+ is required."; exit 1; }
echo "Using Python: $PYTHON_BIN ($($PYTHON_BIN --version 2>&1))"

SOURCE_ARCHIVE="$($PYTHON_BIN - "$DOWNLOADS_DIR" <<'PY'
from pathlib import Path
import sys
root=Path(sys.argv[1])
files=list(root.glob('sustainable-catalyst-feature-suggestions-v5.2.3-repo*.zip'))+list(root.glob('sustainable-catalyst-feature-suggestions-v5.2.3-release-bundle*.zip'))
print(max(files,key=lambda p:p.stat().st_mtime) if files else '')
PY
)"
[ -f "$SOURCE_ARCHIVE" ] || { echo "ERROR: No v${VERSION} repository ZIP or release bundle found in ~/Downloads."; exit 1; }
echo "Using release archive: $SOURCE_ARCHIVE"
unzip -q "$SOURCE_ARCHIVE" -d "$STAGE_DIR"
INNER_ZIP="$(find "$STAGE_DIR" -maxdepth 8 -type f -name 'sustainable-catalyst-feature-suggestions-v5.2.3-repo*.zip' -print -quit)"
if [ -n "$INNER_ZIP" ]; then mkdir -p "$STAGE_DIR/repository-package"; unzip -q "$INNER_ZIP" -d "$STAGE_DIR/repository-package"; SEARCH_ROOT="$STAGE_DIR/repository-package"; else SEARCH_ROOT="$STAGE_DIR"; fi
MANIFEST_PATH="$(find "$SEARCH_ROOT" -maxdepth 8 -type f -name feature_suggestions_manifest.json -print -quit)"
[ -n "$MANIFEST_PATH" ] || { echo "ERROR: Could not locate repository manifest."; exit 1; }
PACKAGE_ROOT="$(dirname "$MANIFEST_PATH")"
read -r MANIFEST_VERSION MANIFEST_RELEASE <<EOF
$($PYTHON_BIN - "$MANIFEST_PATH" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
print(x.get('version',''), x.get('release_name',''))
PY
)
EOF
[ "$MANIFEST_VERSION" = "$VERSION" ] || { echo "ERROR: Manifest version mismatch: $MANIFEST_VERSION"; exit 1; }
[ "$MANIFEST_RELEASE" = "Compact" ] && true # read splits release; exact check follows in Python
$PYTHON_BIN - "$MANIFEST_PATH" "$RELEASE_NAME" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
assert x.get('release_name') == sys.argv[2], (x.get('release_name'),sys.argv[2])
PY

echo "Cloning latest GitHub main..."
git clone "$REPOSITORY" "$CLONE_DIR"
cd "$CLONE_DIR"
git checkout main
git pull --ff-only origin main
BACKUP_PATH="${DOWNLOADS_DIR}/sustainable-catalyst-feature-suggestions-before-v${VERSION}-$(date +%Y%m%d-%H%M%S).zip"
(cd "$CLONE_DIR" && zip -qr "$BACKUP_PATH" . -x '.git/*' '*/__pycache__/*' '*/.pytest_cache/*')
echo "Safety backup: $BACKUP_PATH"
rsync -a --delete --exclude='.git/' --exclude='__pycache__/' --exclude='.pytest_cache/' --exclude='venv/' "$PACKAGE_ROOT/" "$CLONE_DIR/"

MAIN="wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php"
KB="wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-integrated-knowledge-base.php"
CSS="wordpress/sustainable-catalyst-feature-suggestions/assets/knowledge-base.css"
for f in "$MAIN" "$KB" "$CSS" tests/test-v523-dynamic-documentation.php RELEASE_NOTES_5.2.3.md; do [ -f "$f" ] || { echo "ERROR: Missing $f"; exit 1; }; done
grep -Fq 'Version: 5.2.3' "$MAIN" || { echo "ERROR: WordPress version marker mismatch."; exit 1; }
grep -Fq "const VERSION = '5.2.3';" "$KB" || { echo "ERROR: Knowledge Base version marker mismatch."; exit 1; }
grep -Fq "const COMPACT_SHORTCODE = 'scfs_support_library_compact';" "$KB" || { echo "ERROR: Compact shortcode missing."; exit 1; }
if grep -Fq "add_filter('the_content', array(\$this, 'automatically_render_on_support_page')" "$KB"; then echo "ERROR: Automatic Support-page injection is still active."; exit 1; fi

command -v php >/dev/null 2>&1 || { echo "ERROR: PHP required."; exit 1; }
echo "Running PHP syntax validation..."
PHP_COUNT=0
while IFS= read -r -d '' f; do php -l "$f" >/dev/null; PHP_COUNT=$((PHP_COUNT+1)); done < <(find wordpress/sustainable-catalyst-feature-suggestions -name '*.php' -print0)
echo "PASS - $PHP_COUNT plugin PHP files"

echo "Running WordPress contracts..."
TEST_COUNT=0
while IFS= read -r f; do php "$f"; TEST_COUNT=$((TEST_COUNT+1)); done < <(find tests -maxdepth 1 -type f -name 'test-*.php' | sort)
echo "PASS - $TEST_COUNT PHP contract files"

if command -v node >/dev/null 2>&1; then while IFS= read -r -d '' f; do node --check "$f" >/dev/null; done < <(find wordpress/sustainable-catalyst-feature-suggestions/assets -name '*.js' -print0); echo "PASS - JavaScript syntax"; fi

"$PYTHON_BIN" -m venv "$VENV_DIR"
"$VENV_DIR/bin/python" -m pip install -q --upgrade pip
"$VENV_DIR/bin/python" -m pip install -q -r backend/requirements.txt pytest httpx
PYTHONPATH=backend "$VENV_DIR/bin/python" -m pytest backend/tests -q
"$VENV_DIR/bin/python" - <<'PY'
import json
from pathlib import Path
files=[p for p in Path('.').rglob('*.json') if not any(x in p.parts for x in ('.git','venv','__pycache__','.pytest_cache'))]
for p in files: json.loads(p.read_text(encoding='utf-8'))
print(f'PASS - {len(files)} JSON files')
PY

git add -A
if git diff --cached --quiet; then echo "ERROR: No release changes detected."; exit 1; fi
git commit -m "Sustainable Catalyst Feature Suggestions v${VERSION} — ${RELEASE_NAME}"
git push origin main
SUCCESS=1
echo
echo "v${VERSION} committed and pushed successfully."
