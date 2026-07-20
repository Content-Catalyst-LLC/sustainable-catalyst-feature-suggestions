#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="5.2.5"
INSTALLER_REVISION="V5_2_5_R2_GIT_WHITESPACE_REPAIR"
RELEASE_NAME="Product Support and Feedback Platform Rebrand, Knowledge Base Rendering Repair, Library Browser Redesign, and Publication-Parity Support Articles"
REPOSITORY="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git"
DOWNLOADS_DIR="${HOME}/Downloads"
WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/scfs-v525.XXXXXX")"
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

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
choose_python() {
  local candidate
  for candidate in /opt/homebrew/bin/python3.13 /usr/local/bin/python3.13 python3.13 /opt/homebrew/bin/python3.12 /usr/local/bin/python3.12 python3.12 python3; do
    if command -v "$candidate" >/dev/null 2>&1 || [ -x "$candidate" ]; then
      if "$candidate" -c 'import sys; raise SystemExit(0 if sys.version_info >= (3,12) else 1)' >/dev/null 2>&1; then
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
[ -n "$PYTHON_BIN" ] || fail 'Python 3.12 or newer is required.'
printf 'Using Python: %s (%s)\n' "$PYTHON_BIN" "$("$PYTHON_BIN" --version 2>&1)"
command -v git >/dev/null 2>&1 || fail 'Git is required.'
command -v unzip >/dev/null 2>&1 || fail 'unzip is required.'
command -v rsync >/dev/null 2>&1 || fail 'rsync is required.'
command -v php >/dev/null 2>&1 || fail 'PHP CLI is required.'

SOURCE_ARCHIVE="$("$PYTHON_BIN" - "$DOWNLOADS_DIR" <<'PY'
from pathlib import Path
import sys
root = Path(sys.argv[1])
patterns = (
    'sustainable-catalyst-feature-suggestions-v5.2.5-repo*.zip',
    'sustainable-catalyst-product-support-feedback-platform-v5.2.5-release-bundle*.zip',
    'sustainable-catalyst-feature-suggestions-v5.2.5-release-bundle*.zip',
)
files = []
for pattern in patterns:
    files.extend(root.glob(pattern))
print(max(files, key=lambda p: p.stat().st_mtime) if files else '')
PY
)"
[ -f "$SOURCE_ARCHIVE" ] || fail 'No v5.2.5 repository ZIP or release bundle was found in ~/Downloads.'
printf 'Using release archive: %s\n' "$SOURCE_ARCHIVE"
unzip -q "$SOURCE_ARCHIVE" -d "$STAGE_DIR"

INNER_ZIP="$(find "$STAGE_DIR" -maxdepth 8 -type f -name 'sustainable-catalyst-feature-suggestions-v5.2.5-repo*.zip' -print -quit)"
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
import json, sys
path, version, release_name = sys.argv[1:]
manifest = json.load(open(path, encoding='utf-8'))
assert manifest.get('version') == version, (manifest.get('version'), version)
assert manifest.get('release_name') == release_name, (manifest.get('release_name'), release_name)
assert manifest.get('slug') == 'sustainable-catalyst-feature-suggestions'
assert manifest.get('legacy_name') == 'Sustainable Catalyst Feature Suggestions'
PY

printf 'Cloning current GitHub main...\n'
git clone "$REPOSITORY" "$CLONE_DIR"
cd "$CLONE_DIR"
git checkout main
git pull --ff-only origin main

BACKUP_PATH="${DOWNLOADS_DIR}/sustainable-catalyst-feature-suggestions-before-v${VERSION}-$(date +%Y%m%d-%H%M%S).zip"
(cd "$CLONE_DIR" && zip -qr "$BACKUP_PATH" . -x '.git/*' '*/__pycache__/*' '*/.pytest_cache/*' '*/.venv/*')
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
  wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-knowledge-base.php \
  wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-integrated-knowledge-base.php \
  wordpress/sustainable-catalyst-feature-suggestions/assets/knowledge-base.css \
  wordpress/sustainable-catalyst-feature-suggestions/content/knowledge-base/page.html \
  tests/test-v525-branding-compatibility.php \
  tests/test-v525-css-coverage.php \
  tests/test-v525-knowledge-base-repair.php \
  tests/test-v525-library-browser.php \
  tests/test-v525-publication-parity.php \
  RELEASE_NOTES_5.2.5.md \
  validate_v5_2_5.sh; do
  [ -f "$required" ] || fail "Missing required release file: $required"
done

printf 'Preparing isolated Python validation environment...\n'
"$PYTHON_BIN" -m venv "$VENV_DIR"
"$VENV_DIR/bin/python" -m pip install -q --upgrade pip
"$VENV_DIR/bin/python" -m pip install -q -r backend/requirements.txt pytest
PYTHON_BIN="$VENV_DIR/bin/python" ./validate_v5_2_5.sh

if git diff --check; then :; else fail 'Git whitespace validation failed.'; fi
git add -A
if git diff --cached --quiet; then fail 'No release changes were detected.'; fi
COMMIT_MESSAGE="Sustainable Catalyst Product Support and Feedback Platform v${VERSION} — ${RELEASE_NAME}"
git commit -m "$COMMIT_MESSAGE"
git push origin main
SUCCESS=1
printf '\nv%s committed and pushed successfully.\n' "$VERSION"
printf 'Commit: %s\n' "$(git rev-parse HEAD)"
