#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="5.3.0"
INSTALLER_REVISION="V5_3_0_R1_UNIFIED_SEARCH_GUIDED_RESOLUTION"
RELEASE_NAME="Unified Search and Guided Resolution"
REPOSITORY="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git"
DOWNLOADS_DIR="${HOME}/Downloads"
WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/scfs-v530.XXXXXX")"
STAGE_DIR="${WORK_DIR}/stage"
CLONE_DIR="${WORK_DIR}/repository"
VENV_DIR="${WORK_DIR}/venv"
SUCCESS=0

cleanup() {
  status=$?
  if [ "$SUCCESS" -eq 1 ]; then
    rm -rf "$WORK_DIR"
  else
    printf '\nThe v%s release was not committed or pushed.\n' "$VERSION"
    printf 'Temporary validation workspace retained at: %s\n' "$WORK_DIR"
  fi
  exit "$status"
}
trap cleanup EXIT

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

choose_python() {
  local candidate
  for candidate in \
    /opt/homebrew/opt/python@3.13/bin/python3.13 \
    /opt/homebrew/bin/python3.13 \
    /usr/local/opt/python@3.13/bin/python3.13 \
    /usr/local/bin/python3.13 \
    /Library/Frameworks/Python.framework/Versions/3.13/bin/python3.13 \
    python3.13 \
    /opt/homebrew/opt/python@3.12/bin/python3.12 \
    /opt/homebrew/bin/python3.12 \
    /usr/local/opt/python@3.12/bin/python3.12 \
    /usr/local/bin/python3.12 \
    /Library/Frameworks/Python.framework/Versions/3.12/bin/python3.12 \
    python3.12 \
    python3; do
    if command -v "$candidate" >/dev/null 2>&1 || [ -x "$candidate" ]; then
      if "$candidate" -c 'import sys; raise SystemExit(0 if (3, 12) <= sys.version_info[:2] < (3, 14) else 1)' >/dev/null 2>&1; then
        printf '%s' "$candidate"
        return 0
      fi
    fi
  done
  return 1
}

printf 'Sustainable Catalyst Product Support and Feedback Platform v%s\n' "$VERSION"
printf '%s\n' "$RELEASE_NAME"
printf 'Installer revision: %s\n' "$INSTALLER_REVISION"
mkdir -p "$STAGE_DIR"

PYTHON_BIN="$(choose_python || true)"
[ -n "$PYTHON_BIN" ] || fail 'Python 3.12 or 3.13 is required for v5.3.0 validation. Install one with: brew install python@3.13'
printf 'Using Python: %s (%s)\n' "$PYTHON_BIN" "$("$PYTHON_BIN" --version 2>&1)"

for command_name in git unzip rsync php zip; do
  command -v "$command_name" >/dev/null 2>&1 || fail "$command_name is required."
done

SOURCE_ARCHIVE="$("$PYTHON_BIN" - "$DOWNLOADS_DIR" <<'PY'
from pathlib import Path
import sys
root = Path(sys.argv[1])
patterns = (
    'sustainable-catalyst-product-support-feedback-platform-v5.3.0-release-bundle*.zip',
    'sustainable-catalyst-feature-suggestions-v5.3.0-repo*.zip',
)
files = []
for pattern in patterns:
    files.extend(root.glob(pattern))
print(max(files, key=lambda p: p.stat().st_mtime) if files else '')
PY
)"
[ -f "$SOURCE_ARCHIVE" ] || fail 'No v5.3.0 repository ZIP or release bundle was found in ~/Downloads.'
printf 'Using release archive: %s\n' "$SOURCE_ARCHIVE"
unzip -q "$SOURCE_ARCHIVE" -d "$STAGE_DIR"

INNER_ZIP="$(find "$STAGE_DIR" -maxdepth 8 -type f -name 'sustainable-catalyst-feature-suggestions-v5.3.0-repo*.zip' -print -quit)"
if [ -n "$INNER_ZIP" ]; then
  mkdir -p "$STAGE_DIR/repository-package"
  unzip -q "$INNER_ZIP" -d "$STAGE_DIR/repository-package"
  SEARCH_ROOT="$STAGE_DIR/repository-package"
else
  SEARCH_ROOT="$STAGE_DIR"
fi

MANIFEST_PATH="$(find "$SEARCH_ROOT" -maxdepth 8 -type f -name feature_suggestions_manifest.json -print -quit)"
[ -n "$MANIFEST_PATH" ] || fail 'Could not locate feature_suggestions_manifest.json in the release.'
PACKAGE_ROOT="$(dirname "$MANIFEST_PATH")"

"$PYTHON_BIN" - "$MANIFEST_PATH" "$VERSION" "$RELEASE_NAME" <<'PY'
import json
import sys
path, version, release_name = sys.argv[1:]
with open(path, encoding='utf-8') as handle:
    manifest = json.load(handle)
assert manifest.get('version') == version, (manifest.get('version'), version)
assert manifest.get('release_name') == release_name, (manifest.get('release_name'), release_name)
assert manifest.get('slug') == 'sustainable-catalyst-feature-suggestions'
assert manifest.get('legacy_name') == 'Sustainable Catalyst Feature Suggestions'
compatibility = manifest.get('compatibility', {})
assert compatibility.get('rest_namespace') == 'scfs/v1'
assert compatibility.get('database_migration_required') is False
unified = manifest.get('unified_support_search', {})
assert unified.get('schema') == 'scfs-unified-support-search/1.0'
assert unified.get('journey_schema') == 'scfs-support-resolution-journey/1.0'
assert unified.get('personal_data_stored') is False
assert unified.get('automatic_case_creation') is False
PY

printf 'Cloning current GitHub main...\n'
git clone "$REPOSITORY" "$CLONE_DIR"
cd "$CLONE_DIR"
git checkout main
git pull --ff-only origin main

BACKUP_PATH="${DOWNLOADS_DIR}/sustainable-catalyst-feature-suggestions-before-v${VERSION}-$(date +%Y%m%d-%H%M%S).zip"
(
  cd "$CLONE_DIR"
  zip -qr "$BACKUP_PATH" . \
    -x '.git/*' '*/__pycache__/*' '*/.pytest_cache/*' '*/.venv/*' '*/venv/*'
)
printf 'Safety backup: %s\n' "$BACKUP_PATH"

rsync -a --delete \
  --exclude='.git/' \
  --exclude='__pycache__/' \
  --exclude='.pytest_cache/' \
  --exclude='.venv/' \
  --exclude='venv/' \
  "$PACKAGE_ROOT/" "$CLONE_DIR/"

for required in \
  wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php \
  wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-unified-support-search.php \
  wordpress/sustainable-catalyst-feature-suggestions/assets/unified-support-search.css \
  wordpress/sustainable-catalyst-feature-suggestions/assets/unified-support-search.js \
  backend/app/unified_support_search.py \
  backend/tests/test_unified_support_search.py \
  tests/test-v530-bootstrap.php \
  tests/test-v530-search-fusion.php \
  tests/test-v530-resolution-journey.php \
  tests/test-v530-support-center-integration.php \
  tests/test-v530-api-privacy-compatibility.php \
  tests/test-v530-interface-and-artifacts.php \
  docs/unified-search-guided-resolution-v5.3.0.md \
  examples/unified-support-search.json \
  schemas/scfs-unified-support-search-v1.schema.json \
  RELEASE_NOTES_5.3.0.md \
  release-manifest-v5.3.0.json \
  validate_v5_3_0.sh; do
  [ -f "$required" ] || fail "Missing required release file: $required"
done

printf 'Preparing isolated Python validation environment...\n'
"$PYTHON_BIN" -m venv "$VENV_DIR"
"$VENV_DIR/bin/python" -m pip install -q --upgrade pip
if ! "$VENV_DIR/bin/python" -m pip install -q \
  --prefer-binary \
  --only-binary=pydantic-core \
  -r backend/requirements.txt pytest; then
  fail "Backend dependencies could not be installed with $($PYTHON_BIN --version 2>&1). Install Python 3.13 with 'brew install python@3.13' and rerun the installer."
fi
PYTHON_BIN="$VENV_DIR/bin/python" ./validate_v5_3_0.sh

if git diff --check; then
  printf 'PASS - Git whitespace validation\n'
else
  fail 'Git whitespace validation failed.'
fi

git add -A
if git diff --cached --quiet; then
  fail 'No release changes were detected.'
fi

COMMIT_MESSAGE="Sustainable Catalyst Product Support and Feedback Platform v${VERSION} — ${RELEASE_NAME}"
git commit -m "$COMMIT_MESSAGE"
git push origin main
SUCCESS=1

printf '\nv%s committed and pushed successfully.\n' "$VERSION"
printf 'Commit: %s\n' "$(git rev-parse HEAD)"
printf 'Canonical Support Center: https://sustainablecatalyst.com/support/\n'
printf 'Unified support: https://sustainablecatalyst.com/support/?scfs_support_view=resolve#guided-resolution\n'
