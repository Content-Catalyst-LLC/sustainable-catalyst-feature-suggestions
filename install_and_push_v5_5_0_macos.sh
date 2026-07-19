#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="5.5.0"
INSTALLER_REVISION="V5_5_0_R1_CONTENT_OPERATIONS_EDITORIAL_GOVERNANCE"
RELEASE_NAME="Support Content Operations and Editorial Governance"
REPOSITORY="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git"
DOWNLOADS_DIR="${HOME}/Downloads"
WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/scfs-v550.XXXXXX")"
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
[ -n "$PYTHON_BIN" ] || fail 'Python 3.12 or 3.13 is required for v5.5.0 validation. Install one with: brew install python@3.13'
printf 'Using Python: %s (%s)\n' "$PYTHON_BIN" "$("$PYTHON_BIN" --version 2>&1)"

for command_name in git unzip rsync php zip; do
  command -v "$command_name" >/dev/null 2>&1 || fail "$command_name is required."
done

SOURCE_ARCHIVE="$("$PYTHON_BIN" - "$DOWNLOADS_DIR" <<'PY'
from pathlib import Path
import sys
root = Path(sys.argv[1])
patterns = (
    'sustainable-catalyst-product-support-feedback-platform-v5.5.0-release-bundle*.zip',
    'sustainable-catalyst-feature-suggestions-v5.5.0-repo*.zip',
)
files = []
for pattern in patterns:
    files.extend(root.glob(pattern))
print(max(files, key=lambda p: p.stat().st_mtime) if files else '')
PY
)"
[ -f "$SOURCE_ARCHIVE" ] || fail 'No v5.5.0 repository ZIP or release bundle was found in ~/Downloads.'
printf 'Using release archive: %s\n' "$SOURCE_ARCHIVE"
unzip -q "$SOURCE_ARCHIVE" -d "$STAGE_DIR"

INNER_ZIP="$(find "$STAGE_DIR" -maxdepth 8 -type f -name 'sustainable-catalyst-feature-suggestions-v5.5.0-repo*.zip' -print -quit)"
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
governance = manifest.get('support_content_governance', {})
assert governance.get('schema') == 'scfs-support-content-governance/1.0'
assert governance.get('content_owner_assignments') is True
assert governance.get('technical_owner_assignments') is True
assert governance.get('verification_history') is True
assert governance.get('review_cadence') is True
assert governance.get('supersession_relationships') is True
assert governance.get('public_record_data_exposed') is False
assert governance.get('automatic_publication') is False
assert governance.get('automatic_editorial_approval') is False
assert governance.get('human_review_required') is True
validation = manifest.get('validation', {})
assert validation.get('status') == 'passed'
assert validation.get('fastapi_tests', 0) >= 98
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
  wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-support-content-governance.php \
  wordpress/sustainable-catalyst-feature-suggestions/assets/support-content-governance.css \
  wordpress/sustainable-catalyst-feature-suggestions/assets/support-content-governance.js \
  backend/app/content_governance.py \
  backend/tests/test_content_governance.py \
  tests/test-v550-bootstrap.php \
  tests/test-v550-governance-engine.php \
  tests/test-v550-admin-and-api.php \
  tests/test-v550-interface.php \
  tests/test-v550-compatibility.php \
  tests/test-v550-release-artifacts.php \
  docs/support-content-operations-editorial-governance-v5.5.0.md \
  examples/support-content-governance-v5.5.0.json \
  schemas/scfs-support-content-governance-v1.schema.json \
  RELEASE_NOTES_5.5.0.md \
  SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V5.5.0_RELEASE_NOTES.md \
  release-manifest-v5.5.0.json \
  validate_v5_5_0.sh; do
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
PYTHON_BIN="$VENV_DIR/bin/python" ./validate_v5_5_0.sh

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
git tag -a "v${VERSION}" -m "$COMMIT_MESSAGE" 2>/dev/null || true
git push origin main
git push origin "v${VERSION}" 2>/dev/null || true
SUCCESS=1

printf '\nv%s committed and pushed successfully.\n' "$VERSION"
printf 'Commit: %s\n' "$(git rev-parse HEAD)"
printf 'Canonical Support Center: https://sustainablecatalyst.com/support/\n'
printf 'Content Operations: WordPress Admin → Support & Feedback → Content Operations\n'
