#!/usr/bin/env bash
set -Eeuo pipefail

VERSION="6.6.0"
INSTALLER_REVISION="V6_6_0_REMOTE_SYNC_SAFE"
RELEASE_TITLE="Knowledge-Assisted Case Resolution"
REMOTE="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git"
DOWNLOADS="${HOME}/Downloads"
REPO_DIR="$DOWNLOADS/sustainable-catalyst-feature-suggestions"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TAG="v${VERSION}"
VALIDATOR_SCRIPT="validate_v6_6_0.sh"

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

select_python() {
  local candidate version
  for candidate in /opt/homebrew/bin/python3.13 python3.13 /opt/homebrew/bin/python3.12 python3.12; do
    if command -v "$candidate" >/dev/null 2>&1; then
      version="$($candidate -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")')"
      case "$version" in
        3.12|3.13) printf '%s' "$candidate"; return 0 ;;
      esac
    fi
  done
  return 1
}

remote_tag_ref_oid() {
  git ls-remote --tags origin "refs/tags/${TAG}" | awk 'NR == 1 {print $1}'
}

remote_tag_commit_oid() {
  local peeled direct
  peeled="$(git ls-remote --tags origin "refs/tags/${TAG}^{}" | awk 'NR == 1 {print $1}')"
  if [ -n "$peeled" ]; then
    printf '%s' "$peeled"
    return 0
  fi
  direct="$(remote_tag_ref_oid)"
  [ -n "$direct" ] && printf '%s' "$direct"
}

create_release_tag() {
  if git rev-parse -q --verify "refs/tags/${TAG}" >/dev/null 2>&1; then
    git tag -d "$TAG" >/dev/null
  fi
  git tag -a "$TAG" -m "Product Support and Feedback Platform v${VERSION} — ${RELEASE_TITLE}"
}

push_release_tag() {
  local remote_ref remote_commit remote_tree local_tree
  remote_ref="$(remote_tag_ref_oid)"
  if [ -z "$remote_ref" ]; then
    git push origin "$TAG"
    return 0
  fi
  remote_commit="$(remote_tag_commit_oid)"
  remote_tree="$(git rev-parse "${remote_commit}^{tree}" 2>/dev/null || true)"
  local_tree="$(git rev-parse 'HEAD^{tree}')"
  if [ -n "$remote_tree" ] && [ "$remote_tree" = "$local_tree" ]; then
    printf 'Remote tag %s already represents the same release tree; aligning it with rebased main.\n' "$TAG"
    git push --force-with-lease="refs/tags/${TAG}:${remote_ref}" origin "refs/tags/${TAG}:refs/tags/${TAG}"
    return 0
  fi
  fail "Remote tag ${TAG} exists and does not match the validated v${VERSION} release tree."
}

PYTHON_BIN="$(select_python || true)"
[ -n "$PYTHON_BIN" ] || fail "Python 3.13 or 3.12 is required. Install with: brew install python@3.13"

for command_name in git unzip rsync zip; do
  command -v "$command_name" >/dev/null 2>&1 || fail "$command_name is required."
done

ARCHIVE=""
for candidate in \
  "$SCRIPT_DIR/sustainable-catalyst-feature-suggestions-v6.6.0-repo.zip" \
  "$DOWNLOADS/sustainable-catalyst-feature-suggestions-v6.6.0-repo.zip" \
  "$SCRIPT_DIR/sustainable-catalyst-product-support-feedback-platform-v6.6.0-release-bundle.zip" \
  "$DOWNLOADS/sustainable-catalyst-product-support-feedback-platform-v6.6.0-release-bundle.zip"; do
  if [ -f "$candidate" ]; then
    ARCHIVE="$candidate"
    break
  fi
done
[ -n "$ARCHIVE" ] || fail "Place the v6.6.0 repository ZIP or release bundle beside this installer or in ~/Downloads."

TMP="$(mktemp -d "${TMPDIR:-/tmp}/scfs-v660.XXXXXX")"
cleanup() { rm -rf "$TMP"; }
trap cleanup EXIT

printf '==> Product Support and Feedback Platform v%s installer\n' "$VERSION"
printf 'Installer revision: %s\n' "$INSTALLER_REVISION"
printf 'Python: %s\n' "$($PYTHON_BIN --version 2>&1)"
printf 'Archive: %s\n' "$ARCHIVE"

unzip -q "$ARCHIVE" -d "$TMP/archive"

REPO_ZIP="$(find "$TMP/archive" -type f -name 'sustainable-catalyst-feature-suggestions-v6.6.0-repo.zip' -print -quit || true)"
if [ -n "$REPO_ZIP" ]; then
  mkdir -p "$TMP/repository"
  unzip -q "$REPO_ZIP" -d "$TMP/repository"
  SOURCE="$(find "$TMP/repository" -maxdepth 2 -type d -name 'sustainable-catalyst-feature-suggestions-v6.6.0-repository' -print -quit)"
else
  SOURCE="$(find "$TMP/archive" -maxdepth 2 -type d -name 'sustainable-catalyst-feature-suggestions-v6.6.0-repository' -print -quit)"
fi

[ -n "${SOURCE:-}" ] || fail "Could not locate the v6.6.0 repository source."
[ -f "$SOURCE/$VALIDATOR_SCRIPT" ] || fail "The selected archive does not contain $VALIDATOR_SCRIPT."
[ -f "$SOURCE/backend/requirements-validation.txt" ] || fail "The selected archive is missing backend/requirements-validation.txt."
[ -f "$SOURCE/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-knowledge-assisted-resolution.php" ] || fail "The selected archive is missing the v6.6.0 Knowledge-Assisted Resolution implementation."
[ -f "$SOURCE/schemas/scfs-help-desk-knowledge-resolution-v1.schema.json" ] || fail "The selected archive is missing the v6.6.0 Knowledge-Assisted Resolution schema."
grep -Fq 'VERSION="6.6.0"' "$SOURCE/$VALIDATOR_SCRIPT" || fail "The packaged validator is not the v6.6.0 validator."
grep -Fq 'blank EOF lines' "$SOURCE/$VALIDATOR_SCRIPT" || fail "The selected archive does not contain the v6.6.0 whitespace preflight."
grep -Fq 'Version: 6.6.0' "$SOURCE/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php" || fail "The selected archive does not contain the v6.6.0 plugin source."

if grep -Eq '(^|[[:space:]])(mapfile|readarray)([[:space:]]|$)' "$SOURCE/$VALIDATOR_SCRIPT"; then
  fail "The packaged validation script requires Bash 4. Use the portable v6.6.0 repaired release."
fi
bash -n "$SOURCE/$VALIDATOR_SCRIPT" || fail "The packaged validation script has invalid shell syntax."

if [ "${SCFS_PREFLIGHT_ONLY:-0}" = "1" ]; then
  printf 'PREFLIGHT PASSED: selected the v6.6.0 archive and validator.\n'
  exit 0
fi

printf '==> Preparing compatible Python validation environment\n'
"$PYTHON_BIN" -m venv "$TMP/venv"
VENV_PY="$TMP/venv/bin/python"
"$VENV_PY" -m pip install --upgrade pip >/dev/null

printf '==> Installing backend and validation dependencies\n'
"$VENV_PY" -m pip install --only-binary=pydantic-core -r "$SOURCE/backend/requirements-validation.txt"
"$VENV_PY" -c 'import fastapi, httpx, pydantic, pytest' || fail "The validation environment is missing one or more required Python packages."
"$VENV_PY" -m pytest --version

printf '==> Validating packaged source with %s\n' "$VALIDATOR_SCRIPT"
PYTHON_BIN="$VENV_PY" bash "$SOURCE/$VALIDATOR_SCRIPT"

if [ -d "$REPO_DIR/.git" ]; then
  TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
  BACKUP="$DOWNLOADS/sustainable-catalyst-feature-suggestions-before-v6.6.0-${TIMESTAMP}.zip"
  GIT_BUNDLE="$DOWNLOADS/sustainable-catalyst-feature-suggestions-before-v6.6.0-${TIMESTAMP}.bundle"
  printf '==> Creating safety backups\n'
  (cd "$(dirname "$REPO_DIR")" && zip -qr "$BACKUP" "$(basename "$REPO_DIR")" -x '*/.git/*' '*/.venv/*' '*/venv/*')
  git -C "$REPO_DIR" bundle create "$GIT_BUNDLE" --all >/dev/null 2>&1 || rm -f "$GIT_BUNDLE"

  printf '==> Synchronizing local main with current origin/main\n'
  git -C "$REPO_DIR" remote set-url origin "$REMOTE"
  git -C "$REPO_DIR" fetch origin main --tags
  git -C "$REPO_DIR" reset --hard
  git -C "$REPO_DIR" clean -fd
  git -C "$REPO_DIR" checkout -B main origin/main
else
  printf '==> Cloning current repository main\n'
  rm -rf "$REPO_DIR"
  git clone --branch main "$REMOTE" "$REPO_DIR"
fi

printf '==> Installing v6.6.0 source onto current origin/main\n'
rsync -a --delete --exclude '.git/' --exclude '.venv/' --exclude 'venv/' "$SOURCE/" "$REPO_DIR/"
cd "$REPO_DIR"

PYTHON_BIN="$VENV_PY" bash "./$VALIDATOR_SCRIPT"
git diff --check
git add -A

if git diff --cached --quiet; then
  printf 'No source changes to commit. Current main may already contain v6.6.0.\n'
else
  git commit -m "Product Support and Feedback Platform v6.6.0 — $RELEASE_TITLE"
fi

printf '==> Refreshing origin/main before push\n'
git fetch origin main
if ! git merge-base --is-ancestor origin/main HEAD; then
  printf 'Remote main advanced during installation; rebasing the validated release commit.\n'
  git rebase origin/main
  PYTHON_BIN="$VENV_PY" bash "./$VALIDATOR_SCRIPT"
  git diff --check
fi

create_release_tag

printf '==> Pushing main\n'
if ! git push origin HEAD:main; then
  printf 'Remote main changed again; retrying once after rebase.\n'
  git fetch origin main
  git rebase origin/main
  PYTHON_BIN="$VENV_PY" bash "./$VALIDATOR_SCRIPT"
  git diff --check
  create_release_tag
  git push origin HEAD:main
fi

printf '==> Pushing tag %s\n' "$TAG"
push_release_tag

printf '\nSUCCESS: v6.6.0 synchronized with current origin/main, validated with %s, committed, tagged, and pushed.\n' "$VALIDATOR_SCRIPT"
printf 'Local repository: %s\n' "$REPO_DIR"
