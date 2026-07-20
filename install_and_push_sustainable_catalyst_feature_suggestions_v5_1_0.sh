#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="5.1.0"
RELEASE_NAME="Integrated Knowledge Base and Documentation Library"
REPOSITORY="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git"
DOWNLOADS_DIR="${HOME}/Downloads"
WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/scfs-v510.XXXXXX")"
STAGE_DIR="${WORK_DIR}/stage"
CLONE_DIR="${WORK_DIR}/repository"
VENV_DIR="${WORK_DIR}/venv"
SUCCESS=0

cleanup() {
  status=$?
  if [ "$SUCCESS" -eq 1 ]; then
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
echo "Installer compatibility: macOS Bash 3.2+"
echo "Working directory: $WORK_DIR"
mkdir -p "$STAGE_DIR"

choose_python() {
  for candidate in \
    /opt/homebrew/bin/python3.13 \
    /usr/local/bin/python3.13 \
    python3.13 \
    /opt/homebrew/bin/python3.12 \
    /usr/local/bin/python3.12 \
    python3.12
  do
    if command -v "$candidate" >/dev/null 2>&1 || [ -x "$candidate" ]; then
      version="$($candidate -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")' 2>/dev/null || true)"
      if [ "$version" = "3.13" ] || [ "$version" = "3.12" ]; then
        printf '%s' "$candidate"
        return 0
      fi
    fi
  done
  return 1
}

PYTHON_BIN="$(choose_python || true)"
if [ -z "$PYTHON_BIN" ]; then
  echo "ERROR: Python 3.12 or 3.13 is required."
  echo "Install it with: brew install python@3.13"
  exit 1
fi

echo "Using Python: $PYTHON_BIN ($($PYTHON_BIN --version 2>&1))"

SOURCE_ARCHIVE="$($PYTHON_BIN - "$DOWNLOADS_DIR" <<'PY'
from pathlib import Path
import sys
root=Path(sys.argv[1])
files=list(root.glob('sustainable-catalyst-feature-suggestions-v5.1.0-repo*.zip'))
files+=list(root.glob('sustainable-catalyst-feature-suggestions-v5.1.0-release-bundle*.zip'))
if files:
    print(max(files, key=lambda path: path.stat().st_mtime))
PY
)"
if [ -z "$SOURCE_ARCHIVE" ] || [ ! -f "$SOURCE_ARCHIVE" ]; then
  echo "ERROR: No v5.1.0 repository ZIP or release bundle was found in ~/Downloads."
  exit 1
fi

echo "Using release archive: $SOURCE_ARCHIVE"
unzip -q "$SOURCE_ARCHIVE" -d "$STAGE_DIR"

MANIFEST_PATH="$(find "$STAGE_DIR" -maxdepth 8 -type f -name feature_suggestions_manifest.json -print -quit)"
if [ -z "$MANIFEST_PATH" ]; then
  INNER_ZIP="$(find "$STAGE_DIR" -maxdepth 8 -type f -name 'sustainable-catalyst-feature-suggestions-v5.1.0-repo*.zip' -print -quit)"
  if [ -z "$INNER_ZIP" ]; then
    echo "ERROR: The release archive does not contain the v5.1.0 repository."
    exit 1
  fi
  mkdir -p "$STAGE_DIR/repository-package"
  unzip -q "$INNER_ZIP" -d "$STAGE_DIR/repository-package"
  MANIFEST_PATH="$(find "$STAGE_DIR/repository-package" -maxdepth 8 -type f -name feature_suggestions_manifest.json -print -quit)"
fi
if [ -z "$MANIFEST_PATH" ]; then
  echo "ERROR: Could not locate the v5.1.0 repository root."
  exit 1
fi
PACKAGE_ROOT="$(dirname "$MANIFEST_PATH")"

MANIFEST_VERSION="$($PYTHON_BIN - "$MANIFEST_PATH" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as handle:
    print(json.load(handle).get('version',''))
PY
)"
MANIFEST_RELEASE_NAME="$($PYTHON_BIN - "$MANIFEST_PATH" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as handle:
    print(json.load(handle).get('release_name',''))
PY
)"
if [ "$MANIFEST_VERSION" != "$VERSION" ]; then
  echo "ERROR: Manifest version is '$MANIFEST_VERSION', expected '$VERSION'."
  exit 1
fi
if [ "$MANIFEST_RELEASE_NAME" != "$RELEASE_NAME" ]; then
  echo "ERROR: Manifest release name is '$MANIFEST_RELEASE_NAME', expected '$RELEASE_NAME'."
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
KB_CLASS="wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-integrated-knowledge-base.php"
KB_CSS="wordpress/sustainable-catalyst-feature-suggestions/assets/knowledge-base.css"
KB_JS="wordpress/sustainable-catalyst-feature-suggestions/assets/integrated-knowledge-base.js"
KB_CORPUS="wordpress/sustainable-catalyst-feature-suggestions/content/knowledge-base/articles.json"
BACKEND_MAIN="backend/app/main.py"
if [ ! -f "$MAIN_PLUGIN" ] || [ ! -f "$KB_CLASS" ] || [ ! -f "$KB_CSS" ] || [ ! -f "$KB_JS" ] || [ ! -f "$KB_CORPUS" ] || [ ! -f "$BACKEND_MAIN" ]; then
  echo "ERROR: Required WordPress, Knowledge Base, corpus, or FastAPI v5.1.0 files are missing."
  exit 1
fi
if ! grep -Fq 'Version: 5.1.0' "$MAIN_PLUGIN" || \
   ! grep -Fq "const VERSION = '5.1.0';" "$KB_CLASS" || \
   ! grep -Fq "VERSION='5.1.0'" "$BACKEND_MAIN"; then
  echo "ERROR: WordPress, Knowledge Base, or FastAPI version markers do not match v5.1.0."
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
if [ "$PHP_FILES" -lt 60 ]; then
  echo "ERROR: Expected at least 60 PHP files; found $PHP_FILES."
  exit 1
fi
echo "PASS - $PHP_FILES PHP files"

echo "Running WordPress contract tests..."
PHP_TESTS=0
PHP_CHECKS=0
while IFS= read -r test_file; do
  output="$(php "$test_file")"
  printf '%s\n' "$output"
  count="$(printf '%s\n' "$output" | awk '/checks passed/{print $1}' | tail -1)"
  if [ -n "$count" ]; then PHP_CHECKS=$((PHP_CHECKS + count)); fi
  PHP_TESTS=$((PHP_TESTS + 1))
done < <(find tests -maxdepth 1 -type f -name 'test-*.php' | sort)
if [ "$PHP_TESTS" -lt 39 ] || [ "$PHP_CHECKS" -lt 639 ]; then
  echo "ERROR: Expected at least 39 WordPress test files and 639 checks; found $PHP_TESTS files and $PHP_CHECKS checks."
  exit 1
fi
echo "PASS - $PHP_TESTS WordPress test files, $PHP_CHECKS checks"

if command -v node >/dev/null 2>&1; then
  echo "Validating JavaScript syntax..."
  JS_FILES=0
  while IFS= read -r -d '' js_file; do
    node --check "$js_file"
    JS_FILES=$((JS_FILES + 1))
  done < <(find wordpress/sustainable-catalyst-feature-suggestions/assets -maxdepth 1 -type f -name '*.js' -print0)
  if [ "$JS_FILES" -lt 10 ]; then
    echo "ERROR: Expected at least 10 JavaScript files; found $JS_FILES."
    exit 1
  fi
  echo "PASS - $JS_FILES JavaScript files"
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
if [ "$JSON_COUNT" -lt 57 ]; then
  echo "ERROR: Expected at least 57 JSON records; found $JSON_COUNT."
  exit 1
fi
echo "PASS - $JSON_COUNT JSON files"

echo "Validating integrated Knowledge Base corpus..."
CORPUS_COUNTS="$($VENV_DIR/bin/python - "$KB_CORPUS" <<'PY'
import json,sys
from pathlib import Path
path=Path(sys.argv[1])
data=json.loads(path.read_text(encoding='utf-8'))
articles=data.get('articles',[])
products={item.get('product_slug','') for item in articles if item.get('product_slug')}
samples={item.get(field,'') for item in articles for field in ('sample_csv','sample_json') if item.get(field)}
missing=[sample for sample in samples if not (path.parent / sample).is_file()]
if len(articles)!=96 or len(products)!=16 or len(samples)!=32 or missing:
    raise SystemExit(f"invalid corpus: articles={len(articles)} products={len(products)} samples={len(samples)} missing={len(missing)}")
print(f"{len(articles)} {len(products)} {len(samples)}")
PY
)"
echo "PASS - Knowledge Base corpus $CORPUS_COUNTS"

mkdir -p dist
rm -f dist/sustainable-catalyst-feature-suggestions.zip
(
  cd wordpress
  zip -qr ../dist/sustainable-catalyst-feature-suggestions.zip sustainable-catalyst-feature-suggestions \
    -x '*/.DS_Store' '*/__MACOSX/*' '*/__pycache__/*'
)
unzip -tq dist/sustainable-catalyst-feature-suggestions.zip >/dev/null
HEADER_FILE="$WORK_DIR/plugin-header.php"
KB_ZIP_FILE="$WORK_DIR/integrated-knowledge-base-class.php"
unzip -p dist/sustainable-catalyst-feature-suggestions.zip sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php > "$HEADER_FILE"
unzip -p dist/sustainable-catalyst-feature-suggestions.zip sustainable-catalyst-feature-suggestions/includes/class-scfs-integrated-knowledge-base.php > "$KB_ZIP_FILE"
if ! grep -Fq 'Version: 5.1.0' "$HEADER_FILE"; then
  echo "ERROR: WordPress distribution ZIP does not contain plugin version 5.1.0."
  exit 1
fi
if ! grep -Fq "const VERSION = '5.1.0';" "$KB_ZIP_FILE"; then
  echo "ERROR: WordPress distribution ZIP does not contain the v5.1.0 integrated Knowledge Base class."
  exit 1
fi
ROOT_ENTRY_COUNT="$(zipinfo -1 dist/sustainable-catalyst-feature-suggestions.zip | awk -F/ 'NF && $1!=""{print $1}' | sort -u | wc -l | tr -d ' ')"
if [ "$ROOT_ENTRY_COUNT" != "1" ]; then
  echo "ERROR: WordPress ZIP must contain exactly one plugin root folder."
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
  echo "No repository changes remain. v5.1.0 may already be installed."
else
  git commit -m "Release Feature Suggestions v5.1.0 integrated Knowledge Base"
fi

echo "Rebasing over any newer remote commits..."
git pull --rebase origin main

echo "Pushing main..."
git push origin main
SUCCESS=1

echo
echo "Feature Suggestions v5.1.0 was validated, committed, and pushed successfully."
echo "Repository: $REPOSITORY"
