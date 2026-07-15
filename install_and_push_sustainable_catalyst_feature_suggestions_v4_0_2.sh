#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="4.0.2"
REPO_URL="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git"
DOWNLOADS_DIR="${1:-$HOME/Downloads}"
WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/scfs-v402.XXXXXX")"
CLONE_DIR="$WORK_DIR/repo"
STAGE_DIR="$WORK_DIR/stage"
VENV_DIR="$WORK_DIR/venv"

cleanup_on_failure() {
  local status=$?
  if [[ $status -ne 0 ]]; then
    echo
    echo "The v${VERSION} release was not committed or pushed."
    echo "Temporary validation workspace retained at: $WORK_DIR"
    trap - EXIT
  fi
  exit "$status"
}
trap cleanup_on_failure EXIT

find_python() {
  local candidate version
  for candidate in \
    /opt/homebrew/bin/python3.13 \
    /usr/local/bin/python3.13 \
    python3.13 \
    /opt/homebrew/bin/python3.12 \
    /usr/local/bin/python3.12 \
    python3.12; do
    if [[ "$candidate" == /* ]]; then
      [[ -x "$candidate" ]] || continue
    else
      command -v "$candidate" >/dev/null 2>&1 || continue
      candidate="$(command -v "$candidate")"
    fi
    version="$($candidate -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")')"
    if [[ "$version" == "3.12" || "$version" == "3.13" ]]; then
      printf '%s\n' "$candidate"
      return 0
    fi
  done
  return 1
}

PYTHON_BIN="$(find_python || true)"
if [[ -z "$PYTHON_BIN" ]]; then
  echo "ERROR: Python 3.12 or 3.13 is required. Python 3.14 is intentionally not used."
  echo "Install Python 3.13 with: brew install python@3.13"
  exit 1
fi

echo "Using Python: $PYTHON_BIN ($($PYTHON_BIN --version 2>&1))"

for command_name in git unzip rsync php node; do
  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "ERROR: $command_name is required."
    exit 1
  fi
done

shopt -s nullglob
ARCHIVE_CANDIDATES=(
  "$DOWNLOADS_DIR"/sustainable-catalyst-feature-suggestions-v4.0.2-repo*.zip
  "$DOWNLOADS_DIR"/sustainable-catalyst-feature-suggestions-v4.0.2-release-bundle*.zip
)
shopt -u nullglob

if (( ${#ARCHIVE_CANDIDATES[@]} == 0 )); then
  echo "ERROR: Could not find the v${VERSION} repository ZIP or release bundle in $DOWNLOADS_DIR."
  echo "Download sustainable-catalyst-feature-suggestions-v4.0.2-repo.zip and run again."
  exit 1
fi

RELEASE_ARCHIVE="$(ls -t "${ARCHIVE_CANDIDATES[@]}" | head -1)"
echo "Using release archive: $RELEASE_ARCHIVE"
mkdir -p "$STAGE_DIR"
unzip -q "$RELEASE_ARCHIVE" -d "$STAGE_DIR"

MANIFEST_PATH="$(find "$STAGE_DIR" -maxdepth 8 -type f -name feature_suggestions_manifest.json -print -quit)"
if [[ -z "$MANIFEST_PATH" ]]; then
  INNER_ZIP="$(find "$STAGE_DIR" -maxdepth 7 -type f -name 'sustainable-catalyst-feature-suggestions-v4.0.2-repo*.zip' -print -quit)"
  if [[ -n "$INNER_ZIP" ]]; then
    mkdir -p "$STAGE_DIR/repository"
    unzip -q "$INNER_ZIP" -d "$STAGE_DIR/repository"
    MANIFEST_PATH="$(find "$STAGE_DIR/repository" -maxdepth 8 -type f -name feature_suggestions_manifest.json -print -quit)"
  fi
fi

if [[ -z "$MANIFEST_PATH" || ! -f "$MANIFEST_PATH" ]]; then
  echo "ERROR: The selected archive does not contain the Feature Suggestions repository."
  exit 1
fi
SOURCE_DIR="$(dirname "$MANIFEST_PATH")"

MANIFEST_VERSION="$($PYTHON_BIN - "$MANIFEST_PATH" <<'PY'
import json, sys
with open(sys.argv[1], encoding='utf-8') as fh:
    print(json.load(fh).get('version', ''))
PY
)"
if [[ "$MANIFEST_VERSION" != "$VERSION" ]]; then
  echo "ERROR: Archive manifest version is $MANIFEST_VERSION, expected $VERSION."
  exit 1
fi

PLUGIN_DIR="$SOURCE_DIR/wordpress/sustainable-catalyst-feature-suggestions"
PLATFORM_CLASS="$PLUGIN_DIR/includes/class-scfs-product-support-platform.php"
PLATFORM_CSS="$PLUGIN_DIR/assets/product-support-platform.css"
PLATFORM_JS="$PLUGIN_DIR/assets/product-support-platform.js"
if [[ ! -d "$PLUGIN_DIR" || ! -d "$SOURCE_DIR/backend" ]]; then
  echo "ERROR: The archive is missing the WordPress or backend source tree."
  exit 1
fi
if [[ ! -f "$PLATFORM_CLASS" || ! -f "$PLATFORM_CSS" || ! -f "$PLATFORM_JS" ]]; then
  echo "ERROR: The v${VERSION} navigation implementation is incomplete."
  exit 1
fi
if ! grep -q "const VERSION = '4.0.2'" "$PLATFORM_CLASS"; then
  echo "ERROR: Product Support Platform class does not identify v${VERSION}."
  exit 1
fi
if ! grep -q "'/product-support/view'" "$PLATFORM_CLASS"; then
  echo "ERROR: Public Support Center view route is missing."
  exit 1
fi
if ! grep -q 'history.pushState' "$PLATFORM_JS" || ! grep -q 'popstate' "$PLATFORM_JS"; then
  echo "ERROR: Browser-history navigation implementation is missing."
  exit 1
fi
if ! grep -q 'scfs-support-platform__pathway-card' "$PLATFORM_CSS"; then
  echo "ERROR: Embedded pathway reliability CSS is missing."
  exit 1
fi

node --check "$PLATFORM_JS"
node --check "$PLUGIN_DIR/assets/forms.js"

echo "Cloning the latest GitHub main branch..."
git clone "$REPO_URL" "$CLONE_DIR"
cd "$CLONE_DIR"
git checkout main

echo "Applying the complete v${VERSION} repository over the fresh clone..."
rsync -a --delete --exclude='.git/' "$SOURCE_DIR/" "$CLONE_DIR/"

echo "Running PHP syntax checks..."
PHP_COUNT=0
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
  PHP_COUNT=$((PHP_COUNT + 1))
done < <(find wordpress/sustainable-catalyst-feature-suggestions tests -type f -name '*.php' -print0)
echo "PASS - $PHP_COUNT PHP files"

echo "Running WordPress navigation, rendering, branding, and privacy tests..."
TEST_COUNT=0
while IFS= read -r test_file; do
  php "$test_file"
  TEST_COUNT=$((TEST_COUNT + 1))
done < <(find tests -maxdepth 1 -type f -name 'test-v402-*.php' | sort)
if [[ "$TEST_COUNT" -lt 9 ]]; then
  echo "ERROR: Expected at least nine v4.0.2 PHP test files; found $TEST_COUNT."
  exit 1
fi

echo "Creating an isolated Python validation environment..."
"$PYTHON_BIN" -m venv "$VENV_DIR"
"$VENV_DIR/bin/python" -m pip install --upgrade pip
"$VENV_DIR/bin/python" -m pip install -r backend/requirements.txt pytest httpx

echo "Running Python/FastAPI tests..."
PYTHONPATH=backend "$VENV_DIR/bin/python" -m pytest backend/tests -q

echo "Validating JSON records..."
"$VENV_DIR/bin/python" - <<'PY'
import json
from pathlib import Path
paths = [Path('feature_suggestions_manifest.json'), *sorted(Path('examples').glob('*.json'))]
for path in paths:
    with path.open(encoding='utf-8') as fh:
        json.load(fh)
print(f"PASS - {len(paths)} JSON files")
PY

if [[ ! -f dist/sustainable-catalyst-feature-suggestions.zip ]]; then
  echo "ERROR: WordPress distribution ZIP is missing."
  exit 1
fi
unzip -tq dist/sustainable-catalyst-feature-suggestions.zip >/dev/null
ZIP_ROOTS="$(unzip -Z1 dist/sustainable-catalyst-feature-suggestions.zip | awk -F/ 'NF {print $1}' | sort -u | tr '\n' ' ')"
if [[ "$ZIP_ROOTS" != "sustainable-catalyst-feature-suggestions " ]]; then
  echo "ERROR: WordPress ZIP must contain one plugin root. Found: $ZIP_ROOTS"
  exit 1
fi
if ! unzip -p dist/sustainable-catalyst-feature-suggestions.zip sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php | grep -q 'Version: 4.0.2'; then
  echo "ERROR: WordPress distribution ZIP does not contain plugin version 4.0.2."
  exit 1
fi
echo "PASS - WordPress distribution ZIP"

if grep -RInE --exclude='*.zip' --exclude='*.md' --exclude='*.txt' --exclude-dir='.git' --exclude-dir='venv' \
  '(BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY|AKIA[0-9A-Z]{16}|AIza[0-9A-Za-z_-]{30,}|gh[pousr]_[A-Za-z0-9_]{30,})' .; then
  echo "ERROR: A potential secret was found. Nothing was committed or pushed."
  exit 1
fi
echo "PASS - secret scan"

echo "Validation passed. Preparing Git commit..."
git add -A
if git diff --cached --quiet; then
  echo "No uncommitted v${VERSION} changes were found. The remote may already contain this release."
else
  if ! git config user.name >/dev/null; then
    git config user.name "$(git log -1 --format='%an' 2>/dev/null || echo 'Content Catalyst LLC')"
  fi
  if ! git config user.email >/dev/null; then
    git config user.email "$(git log -1 --format='%ae' 2>/dev/null || echo 'release@users.noreply.github.com')"
  fi
  git commit -m "Release Feature Suggestions v4.0.2 navigation reliability"
fi

echo "Checking for GitHub changes that arrived during validation..."
git pull --rebase origin main

echo "Pushing Feature Suggestions v${VERSION}..."
git push origin main

echo
echo "Feature Suggestions v${VERSION} was validated, installed, committed, and pushed successfully."
echo "Validated repository retained at: $CLONE_DIR"
rm -rf "$VENV_DIR" "$STAGE_DIR"
trap - EXIT
