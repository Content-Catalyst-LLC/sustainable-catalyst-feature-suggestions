#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="4.1.1"
RELEASE_NAME="Content Operations Reliability Patch"
REPOSITORY="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git"
DOWNLOADS_DIR="${HOME}/Downloads"
WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/scfs-v411.XXXXXX")"
STAGE_DIR="${WORK_DIR}/stage"
CLONE_DIR="${WORK_DIR}/repository"
VENV_DIR="${WORK_DIR}/venv"
SUCCESS=0

cleanup() {
  local status=$?
  if [[ "$SUCCESS" -eq 1 ]]; then
    rm -rf "$WORK_DIR"
  else
    echo
    echo "The v${VERSION} release was not committed or pushed."
    echo "Temporary validation workspace retained at: $WORK_DIR"
  fi
  exit "$status"
}
trap cleanup EXIT

echo "Feature Suggestions v${VERSION} — ${RELEASE_NAME}"
echo "Working directory: $WORK_DIR"
mkdir -p "$STAGE_DIR"

choose_python() {
  local candidates=(
    "/opt/homebrew/bin/python3.13"
    "/usr/local/bin/python3.13"
    "python3.13"
    "/opt/homebrew/bin/python3.12"
    "/usr/local/bin/python3.12"
    "python3.12"
  )
  local candidate version
  for candidate in "${candidates[@]}"; do
    if command -v "$candidate" >/dev/null 2>&1 || [[ -x "$candidate" ]]; then
      version="$($candidate -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")' 2>/dev/null || true)"
      if [[ "$version" == "3.13" || "$version" == "3.12" ]]; then
        printf '%s' "$candidate"
        return 0
      fi
    fi
  done
  return 1
}

PYTHON_BIN="$(choose_python || true)"
if [[ -z "$PYTHON_BIN" ]]; then
  echo "ERROR: Python 3.12 or 3.13 is required."
  echo "Install it with: brew install python@3.13"
  exit 1
fi

echo "Using Python: $PYTHON_BIN ($($PYTHON_BIN --version 2>&1))"

shopt -s nullglob
archives=(
  "$DOWNLOADS_DIR"/sustainable-catalyst-feature-suggestions-v4.1.1-repo*.zip
  "$DOWNLOADS_DIR"/sustainable-catalyst-feature-suggestions-v4.1.1-release-bundle*.zip
)
shopt -u nullglob
if [[ ${#archives[@]} -eq 0 ]]; then
  echo "ERROR: No v4.1.1 repository ZIP or release bundle was found in ~/Downloads."
  exit 1
fi
SOURCE_ARCHIVE="$(ls -t "${archives[@]}" | head -1)"
echo "Using release archive: $SOURCE_ARCHIVE"
unzip -q "$SOURCE_ARCHIVE" -d "$STAGE_DIR"

MANIFEST_PATH="$(find "$STAGE_DIR" -maxdepth 5 -type f -name feature_suggestions_manifest.json -print -quit)"
if [[ -z "$MANIFEST_PATH" ]]; then
  INNER_ZIP="$(find "$STAGE_DIR" -maxdepth 5 -type f -name 'sustainable-catalyst-feature-suggestions-v4.1.1-repo*.zip' -print -quit)"
  if [[ -z "$INNER_ZIP" ]]; then
    echo "ERROR: The release archive does not contain the v4.1.1 repository."
    exit 1
  fi
  mkdir -p "$STAGE_DIR/repository-package"
  unzip -q "$INNER_ZIP" -d "$STAGE_DIR/repository-package"
  MANIFEST_PATH="$(find "$STAGE_DIR/repository-package" -maxdepth 5 -type f -name feature_suggestions_manifest.json -print -quit)"
fi
if [[ -z "$MANIFEST_PATH" ]]; then
  echo "ERROR: Could not locate the v4.1.1 repository root."
  exit 1
fi
PACKAGE_ROOT="$(dirname "$MANIFEST_PATH")"

MANIFEST_VERSION="$($PYTHON_BIN - "$MANIFEST_PATH" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as handle:
    print(json.load(handle).get('version',''))
PY
)"
if [[ "$MANIFEST_VERSION" != "$VERSION" ]]; then
  echo "ERROR: Manifest version is '$MANIFEST_VERSION', expected '$VERSION'."
  exit 1
fi

echo "Cloning the latest GitHub main branch..."
git clone "$REPOSITORY" "$CLONE_DIR"
cd "$CLONE_DIR"
git checkout main
git pull --ff-only origin main

rsync -a --delete \
  --exclude='.git/' \
  --exclude='__pycache__/' \
  --exclude='.pytest_cache/' \
  --exclude='venv/' \
  "$PACKAGE_ROOT/" "$CLONE_DIR/"

MAIN_PLUGIN="wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php"
OPERATIONS_CLASS="wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-support-content-operations.php"
OPERATIONS_JS="wordpress/sustainable-catalyst-feature-suggestions/assets/support-content-operations.js"
if [[ ! -f "$MAIN_PLUGIN" || ! -f "$OPERATIONS_CLASS" || ! -f "$OPERATIONS_JS" ]]; then
  echo "ERROR: Required WordPress v4.1.1 files are missing."
  exit 1
fi
if ! grep -Fq 'Version: 4.1.1' "$MAIN_PLUGIN" || \
   ! grep -Fq "const VERSION = '4.1.1';" "$OPERATIONS_CLASS" || \
   ! grep -Fq "const SCHEMA_VERSION = '1.1';" "$OPERATIONS_CLASS"; then
  echo "ERROR: WordPress version or schema markers do not match v4.1.1."
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: PHP is required for release validation."
  exit 1
fi

echo "Running PHP syntax validation..."
PHP_FILES=0
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
  PHP_FILES=$((PHP_FILES + 1))
done < <(find wordpress tests -type f -name '*.php' -print0)
echo "PASS - $PHP_FILES PHP files"

echo "Running WordPress contract tests..."
PHP_TESTS=0
PHP_CHECKS=0
while IFS= read -r test_file; do
  output="$(php "$test_file")"
  printf '%s\n' "$output"
  count="$(printf '%s\n' "$output" | awk '/checks passed/{print $1}' | tail -1)"
  [[ -n "$count" ]] && PHP_CHECKS=$((PHP_CHECKS + count))
  PHP_TESTS=$((PHP_TESTS + 1))
done < <(find tests -maxdepth 1 -type f -name 'test-*.php' | sort)
if [[ "$PHP_TESTS" -lt 15 || "$PHP_CHECKS" -lt 250 ]]; then
  echo "ERROR: Expected at least 15 WordPress test files and 250 checks; found $PHP_TESTS files and $PHP_CHECKS checks."
  exit 1
fi
echo "PASS - $PHP_TESTS WordPress test files, $PHP_CHECKS checks"

if command -v node >/dev/null 2>&1; then
  echo "Validating JavaScript syntax..."
  node --check wordpress/sustainable-catalyst-feature-suggestions/assets/product-support-platform.js
  node --check wordpress/sustainable-catalyst-feature-suggestions/assets/support-content-operations.js
fi

echo "Creating an isolated Python validation environment..."
"$PYTHON_BIN" -m venv "$VENV_DIR"
"$VENV_DIR/bin/python" -m pip install --upgrade pip
"$VENV_DIR/bin/python" -m pip install -r backend/requirements.txt pytest httpx

echo "Running Python/FastAPI tests..."
PYTHONPATH=backend "$VENV_DIR/bin/python" -m pytest backend/tests -q

echo "Validating JSON records..."
JSON_COUNT="$($VENV_DIR/bin/python - <<'PY'
import json
from pathlib import Path
files=[]
for path in Path('.').rglob('*.json'):
    if any(part in {'.git','venv','__pycache__','.pytest_cache'} for part in path.parts):
        continue
    with path.open(encoding='utf-8') as handle:
        json.load(handle)
    files.append(path)
print(len(files))
PY
)"
if [[ "$JSON_COUNT" -lt 24 ]]; then
  echo "ERROR: Expected at least 24 JSON records; found $JSON_COUNT."
  exit 1
fi
echo "PASS - $JSON_COUNT JSON files"

mkdir -p dist
rm -f dist/sustainable-catalyst-feature-suggestions.zip
(
  cd wordpress
  zip -qr ../dist/sustainable-catalyst-feature-suggestions.zip sustainable-catalyst-feature-suggestions \
    -x '*/.DS_Store' '*/__MACOSX/*' '*/__pycache__/*'
)
unzip -tq dist/sustainable-catalyst-feature-suggestions.zip >/dev/null
HEADER_FILE="$WORK_DIR/plugin-header.php"
unzip -p dist/sustainable-catalyst-feature-suggestions.zip sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php > "$HEADER_FILE"
if ! grep -Fq 'Version: 4.1.1' "$HEADER_FILE"; then
  echo "ERROR: WordPress distribution ZIP does not contain plugin version 4.1.1."
  exit 1
fi
ROOT_ENTRY_COUNT="$(zipinfo -1 dist/sustainable-catalyst-feature-suggestions.zip | awk -F/ 'NF && $1!=""{print $1}' | sort -u | wc -l | tr -d ' ')"
if [[ "$ROOT_ENTRY_COUNT" != "1" ]]; then
  echo "ERROR: WordPress ZIP must contain exactly one plugin root folder."
  exit 1
fi

if ! unzip -p dist/sustainable-catalyst-feature-suggestions.zip sustainable-catalyst-feature-suggestions/includes/class-scfs-support-content-operations.php | grep -F "const VERSION = '4.1.1';" >/dev/null; then
  echo "ERROR: WordPress ZIP does not contain the v4.1.1 content-operations class."
  exit 1
fi

echo "Running push-safe secret scan..."
if grep -RInE --exclude-dir=.git --exclude='*.zip' \
  '(AKIA[0-9A-Z]{16}|gh[pousr]_[A-Za-z0-9_]{30,}|sk-[A-Za-z0-9]{32,}|AIza[0-9A-Za-z_-]{30,}|-----BEGIN (RSA|OPENSSH|EC) PRIVATE KEY-----)' .; then
  echo "ERROR: A potential secret was found. Nothing was committed or pushed."
  exit 1
fi

git add -A
if git diff --cached --quiet; then
  echo "No repository changes remain. v4.1.1 may already be installed."
else
  git commit -m "Release Feature Suggestions v4.1.1 content operations reliability"
fi

echo "Rebasing over any newer remote commits..."
git pull --rebase origin main

echo "Pushing main..."
git push origin main
SUCCESS=1

echo
echo "Feature Suggestions v4.1.1 was validated, committed, and pushed successfully."
echo "Repository: $REPOSITORY"
