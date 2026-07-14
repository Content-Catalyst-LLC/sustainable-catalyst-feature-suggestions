#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="3.3.0"
REPO_URL="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git"
DOWNLOADS_DIR="${1:-$HOME/Downloads}"
WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/scfs-v330.XXXXXX")"
CLONE_DIR="$WORK_DIR/repo"
STAGE_DIR="$WORK_DIR/stage"
VENV_DIR="$WORK_DIR/venv"

cleanup() {
  rm -rf "$WORK_DIR"
}
trap cleanup EXIT

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

shopt -s nullglob
ARCHIVE_CANDIDATES=(
  "$DOWNLOADS_DIR"/sustainable-catalyst-feature-suggestions-v3.3.0-repo*.zip
  "$DOWNLOADS_DIR"/sustainable-catalyst-feature-suggestions-v3.3.0-release-bundle*.zip
)
shopt -u nullglob
REPO_ZIP=""
if (( ${#ARCHIVE_CANDIDATES[@]} > 0 )); then
  REPO_ZIP="$(ls -t "${ARCHIVE_CANDIDATES[@]}" | head -1)"
fi

if [[ -z "$REPO_ZIP" ]]; then
  echo "ERROR: Could not find the v3.3.0 repository ZIP in $DOWNLOADS_DIR."
  echo "Download sustainable-catalyst-feature-suggestions-v3.3.0-repo.zip and run again."
  exit 1
fi

echo "Using release archive: $REPO_ZIP"
mkdir -p "$STAGE_DIR"
unzip -q "$REPO_ZIP" -d "$STAGE_DIR"

SOURCE_DIR="$(find "$STAGE_DIR" -maxdepth 4 -type f -name feature_suggestions_manifest.json -print -quit | xargs -I{} dirname "{}")"
if [[ -z "$SOURCE_DIR" || ! -f "$SOURCE_DIR/feature_suggestions_manifest.json" ]]; then
  # A release bundle contains the repository ZIP rather than the extracted repository.
  INNER_ZIP="$(find "$STAGE_DIR" -maxdepth 3 -type f -name 'sustainable-catalyst-feature-suggestions-v3.3.0-repo*.zip' -print -quit)"
  if [[ -n "$INNER_ZIP" ]]; then
    rm -rf "$STAGE_DIR/repository"
    mkdir -p "$STAGE_DIR/repository"
    unzip -q "$INNER_ZIP" -d "$STAGE_DIR/repository"
    SOURCE_DIR="$(find "$STAGE_DIR/repository" -maxdepth 4 -type f -name feature_suggestions_manifest.json -print -quit | xargs -I{} dirname "{}")"
  fi
fi

if [[ -z "$SOURCE_DIR" || ! -f "$SOURCE_DIR/feature_suggestions_manifest.json" ]]; then
  echo "ERROR: The selected archive does not contain the Feature Suggestions repository."
  exit 1
fi

MANIFEST_VERSION="$($PYTHON_BIN - "$SOURCE_DIR/feature_suggestions_manifest.json" <<'PY'
import json, sys
with open(sys.argv[1], encoding='utf-8') as fh:
    print(json.load(fh).get('version', ''))
PY
)"
if [[ "$MANIFEST_VERSION" != "$VERSION" ]]; then
  echo "ERROR: Archive manifest version is $MANIFEST_VERSION, expected $VERSION."
  exit 1
fi

echo "Cloning the latest GitHub main branch..."
git clone "$REPO_URL" "$CLONE_DIR"
cd "$CLONE_DIR"
git checkout main

echo "Applying the complete v3.3.0 repository over the fresh clone..."
rsync -a --delete --exclude='.git/' "$SOURCE_DIR/" "$CLONE_DIR/"

if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: PHP is required for plugin validation."
  exit 1
fi

echo "Running PHP syntax checks..."
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done < <(find wordpress/sustainable-catalyst-feature-suggestions -type f -name '*.php' -print0)

echo "Running WordPress contract and ranking tests..."
for test_file in tests/test-v330-*.php; do
  php "$test_file"
done

echo "Creating an isolated Python validation environment..."
"$PYTHON_BIN" -m venv "$VENV_DIR"
"$VENV_DIR/bin/python" -m pip install --upgrade pip
"$VENV_DIR/bin/python" -m pip install -r backend/requirements.txt pytest

echo "Running Python/FastAPI tests..."
PYTHONPATH=backend "$VENV_DIR/bin/python" -m pytest backend/tests -q

echo "Validating JSON records..."
"$VENV_DIR/bin/python" - <<'PY'
import json
from pathlib import Path
for path in [Path('feature_suggestions_manifest.json'), *Path('examples').glob('*.json')]:
    with path.open(encoding='utf-8') as fh:
        json.load(fh)
    print(f"PASS - {path}")
PY

if [[ ! -f dist/sustainable-catalyst-feature-suggestions.zip ]]; then
  echo "ERROR: WordPress distribution ZIP is missing."
  exit 1
fi
unzip -tq dist/sustainable-catalyst-feature-suggestions.zip >/dev/null

if grep -RInE --exclude='*.zip' --exclude='*.md' \
  '(BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY|AKIA[0-9A-Z]{16}|AIza[0-9A-Za-z_-]{30,}|gh[pousr]_[A-Za-z0-9_]{30,})' .; then
  echo "ERROR: A potential secret was found. Nothing was committed or pushed."
  exit 1
fi

echo "Validation passed. Preparing Git commit..."
git add -A
if git diff --cached --quiet; then
  echo "No uncommitted v3.3.0 changes were found. The remote may already contain this release."
else
  if ! git config user.name >/dev/null; then
    git config user.name "$(git log -1 --format='%an' 2>/dev/null || echo 'Content Catalyst LLC')"
  fi
  if ! git config user.email >/dev/null; then
    git config user.email "$(git log -1 --format='%ae' 2>/dev/null || echo 'release@users.noreply.github.com')"
  fi
  git commit -m "Release Feature Suggestions v3.3.0 search and guided resolution"
fi

echo "Checking for any GitHub changes that arrived during validation..."
git pull --rebase origin main

echo "Pushing Feature Suggestions v3.3.0..."
git push origin main

echo
echo "Feature Suggestions v3.3.0 was validated, installed, committed, and pushed successfully."
echo "Recovery clone: $CLONE_DIR"
# Preserve the validated clone for recovery instead of deleting it.
trap - EXIT
rm -rf "$VENV_DIR" "$STAGE_DIR"
echo "Validated repository retained at: $CLONE_DIR"
