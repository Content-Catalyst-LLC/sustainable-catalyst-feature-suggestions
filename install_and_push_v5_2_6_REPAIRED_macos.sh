#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="5.2.6"
INSTALLER_REVISION="V5_2_6_R2_PYTHON_314_COMPATIBILITY_REPAIR"
RELEASE_NAME="Unified Support Center, Embedded Knowledge Base Browser, and Legacy Knowledge Base Route Consolidation"
REPOSITORY="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git"
DOWNLOADS_DIR="${HOME}/Downloads"
WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/scfs-v526.XXXXXX")"
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
  # The v5.2.6 backend pins Pydantic 2.11.5. Use a CPython release
  # with published pydantic-core wheels and never fall through to 3.14,
  # where pip would attempt a local Rust build on the affected macOS setup.
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
[ -n "$PYTHON_BIN" ] || fail 'Python 3.12 or 3.13 is required for v5.2.6 validation. Install one with: brew install python@3.13'
printf 'Using Python: %s (%s)\n' "$PYTHON_BIN" "$("$PYTHON_BIN" --version 2>&1)"

for command_name in git unzip rsync php zip; do
  command -v "$command_name" >/dev/null 2>&1 || fail "$command_name is required."
done

SOURCE_ARCHIVE="$("$PYTHON_BIN" - "$DOWNLOADS_DIR" <<'PY'
from pathlib import Path
import sys
root = Path(sys.argv[1])
pattern_groups = (
    (
        'sustainable-catalyst-product-support-feedback-platform-v5.2.6-release-bundle-REPAIRED*.zip',
        'sustainable-catalyst-feature-suggestions-v5.2.6-repo-REPAIRED*.zip',
    ),
    (
        'sustainable-catalyst-feature-suggestions-v5.2.6-repo*.zip',
        'sustainable-catalyst-product-support-feedback-platform-v5.2.6-release-bundle*.zip',
        'sustainable-catalyst-feature-suggestions-v5.2.6-release-bundle*.zip',
    ),
)
for patterns in pattern_groups:
    files = []
    for pattern in patterns:
        files.extend(root.glob(pattern))
    if files:
        print(max(files, key=lambda p: p.stat().st_mtime))
        break
else:
    print('')
PY
)"
[ -f "$SOURCE_ARCHIVE" ] || fail 'No v5.2.6 repository ZIP or release bundle was found in ~/Downloads.'
printf 'Using release archive: %s\n' "$SOURCE_ARCHIVE"
unzip -q "$SOURCE_ARCHIVE" -d "$STAGE_DIR"

INNER_ZIP="$(find "$STAGE_DIR" -maxdepth 8 -type f -name 'sustainable-catalyst-feature-suggestions-v5.2.6-repo*.zip' -print -quit)"
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
  wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-product-support-platform.php \
  wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-integrated-knowledge-base.php \
  wordpress/sustainable-catalyst-feature-suggestions/assets/product-support-platform.js \
  wordpress/sustainable-catalyst-feature-suggestions/assets/product-support-platform.css \
  wordpress/sustainable-catalyst-feature-suggestions/assets/knowledge-base.css \
  wordpress/sustainable-catalyst-feature-suggestions/content/knowledge-base/page.html \
  tests/test-v526-unified-support-center.php \
  tests/test-v526-embedded-browser.php \
  tests/test-v526-legacy-route-consolidation.php \
  tests/test-v526-backward-compatibility.php \
  RELEASE_NOTES_5.2.6.md \
  release-manifest-v5.2.6.json \
  validate_v5_2_6.sh; do
  [ -f "$required" ] || fail "Missing required release file: $required"
done

printf 'Preparing isolated Python validation environment...\n'
"$PYTHON_BIN" -m venv "$VENV_DIR"
"$VENV_DIR/bin/python" -m pip install -q --upgrade pip
if ! "$VENV_DIR/bin/python" -m pip install -q \
  --prefer-binary \
  --only-binary=pydantic-core \
  -r backend/requirements.txt pytest; then
  fail "Backend dependencies could not be installed with $($PYTHON_BIN --version 2>&1). Install Python 3.13 with 'brew install python@3.13' and rerun this repaired installer."
fi
PYTHON_BIN="$VENV_DIR/bin/python" ./validate_v5_2_6.sh

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
printf 'Support Articles anchor: https://sustainablecatalyst.com/support/?scfs_support_view=documentation#knowledge-base\n'
