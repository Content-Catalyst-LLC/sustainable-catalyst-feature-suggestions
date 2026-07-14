#!/usr/bin/env bash
set -euo pipefail

REPO_URL="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git"
RELEASE_ZIP="sustainable-catalyst-feature-suggestions-v3.1.0-repo.zip"
COMMIT_MESSAGE="Build Feature Suggestions v3.1.0 — Product Taxonomy and Platform Integration"
SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
SOURCE_ZIP="$SCRIPT_DIR/$RELEASE_ZIP"

if [ ! -f "$SOURCE_ZIP" ]; then
  echo "ERROR: $RELEASE_ZIP was not found beside this script."
  exit 1
fi

TARGET_REPO="${1:-}"
if [ -z "$TARGET_REPO" ]; then
  if [ -d "$PWD/.git" ]; then
    TARGET_REPO="$PWD"
  elif [ -d "$HOME/Documents/GitHub/sustainable-catalyst-feature-suggestions/.git" ]; then
    TARGET_REPO="$HOME/Documents/GitHub/sustainable-catalyst-feature-suggestions"
  elif [ -d "$HOME/GitHub/sustainable-catalyst-feature-suggestions/.git" ]; then
    TARGET_REPO="$HOME/GitHub/sustainable-catalyst-feature-suggestions"
  else
    TARGET_REPO="$HOME/Documents/GitHub/sustainable-catalyst-feature-suggestions"
    mkdir -p "$(dirname "$TARGET_REPO")"
    echo "Cloning repository into $TARGET_REPO"
    git clone "$REPO_URL" "$TARGET_REPO"
  fi
fi

if [ ! -d "$TARGET_REPO/.git" ]; then
  echo "ERROR: Target is not a Git repository: $TARGET_REPO"
  echo "Pass the existing repository path as the first argument."
  exit 1
fi

TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/scfs-v310.XXXXXX")"
cleanup() {
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT INT TERM

unzip -q "$SOURCE_ZIP" -d "$TMP_DIR"
SOURCE_DIR="$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -n 1)"
if [ -z "$SOURCE_DIR" ] || [ ! -f "$SOURCE_DIR/feature_suggestions_manifest.json" ]; then
  echo "ERROR: Could not locate the release repository inside $RELEASE_ZIP"
  exit 1
fi

cd "$TARGET_REPO"
CURRENT_BRANCH="$(git branch --show-current)"
if [ -z "$CURRENT_BRANCH" ]; then
  echo "ERROR: The repository is in detached HEAD state."
  exit 1
fi

if [ -n "$(git status --porcelain)" ]; then
  echo "ERROR: The target repository has uncommitted changes. Commit or stash them first."
  git status --short
  exit 1
fi

echo "Updating $TARGET_REPO on branch $CURRENT_BRANCH"
git pull --ff-only

# Copy the release while preserving the target repository's .git directory.
find "$TARGET_REPO" -mindepth 1 -maxdepth 1 ! -name '.git' -exec rm -rf {} +
cp -R "$SOURCE_DIR"/. "$TARGET_REPO"/

find wordpress/sustainable-catalyst-feature-suggestions -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
php tests/test-v310-structure.php
php tests/test-v310-bootstrap.php
PYTHONPATH=backend python3 -m pytest backend/tests -q

git add -A
if git diff --cached --quiet; then
  echo "No changes to commit. The repository already matches v3.1.0."
  exit 0
fi

git commit -m "$COMMIT_MESSAGE"
git push origin "$CURRENT_BRANCH"

echo "Feature Suggestions v3.1.0 was validated, committed, and pushed."
