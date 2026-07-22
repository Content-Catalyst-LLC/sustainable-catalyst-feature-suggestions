#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="7.4.0"
INSTALLER_REVISION="V7_4_0_PRODUCT_REGISTRY_GOVERNANCE"
RELEASE_TITLE="Product Registry Governance"
REMOTE="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-product-support-feedback.git"
DOWNLOADS="${HOME}/Downloads"
CANONICAL_REPO_DIR="$DOWNLOADS/sustainable-catalyst-product-support-feedback"
LEGACY_REPO_DIR="$DOWNLOADS/sustainable-catalyst-feature-suggestions"
REPO_DIR="$CANONICAL_REPO_DIR"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TAG="v${VERSION}"
VALIDATOR_SCRIPT="validate_v7_4_0.sh"
REPO_ARCHIVE="sustainable-catalyst-product-support-feedback-v7.4.0-repository.zip"
BUNDLE_ARCHIVE="sustainable-catalyst-product-support-feedback-platform-v7.4.0-release-bundle.zip"
SUMS_ARCHIVE="sustainable-catalyst-product-support-feedback-v7.4.0-artifacts.sha256"
PLUGIN_SLUG="sustainable-catalyst-feature-suggestions"
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
select_python(){
  local candidate version
  for candidate in \
    /opt/homebrew/opt/python@3.13/bin/python3.13 \
    /opt/homebrew/bin/python3.13 \
    python3.13 \
    /opt/homebrew/opt/python@3.12/bin/python3.12 \
    /opt/homebrew/bin/python3.12 \
    python3.12; do
    if command -v "$candidate" >/dev/null 2>&1; then
      version="$("$candidate" -c 'import sys;print(f"{sys.version_info.major}.{sys.version_info.minor}")')"
      case "$version" in 3.12|3.13) printf '%s' "$candidate"; return 0;; esac
    fi
  done
  return 1
}
remote_tag_ref_oid(){ git ls-remote --tags origin "refs/tags/${TAG}" | awk 'NR==1{print $1}'; }
remote_tag_commit_oid(){
  local peeled direct
  peeled="$(git ls-remote --tags origin "refs/tags/${TAG}^{}" | awk 'NR==1{print $1}')"
  if [ -n "$peeled" ]; then printf '%s' "$peeled"; return; fi
  direct="$(remote_tag_ref_oid)"; [ -n "$direct" ] && printf '%s' "$direct"
}
create_release_tag(){
  if git rev-parse -q --verify "refs/tags/${TAG}" >/dev/null 2>&1; then git tag -d "$TAG" >/dev/null; fi
  git tag -a "$TAG" -m "Product Support and Feedback Platform v${VERSION} — ${RELEASE_TITLE}"
}
push_release_tag(){
  local remote_ref remote_commit remote_tree local_tree
  remote_ref="$(remote_tag_ref_oid)"
  if [ -z "$remote_ref" ]; then git push origin "$TAG"; return; fi
  remote_commit="$(remote_tag_commit_oid)"
  remote_tree="$(git rev-parse "${remote_commit}^{tree}" 2>/dev/null || true)"
  local_tree="$(git rev-parse 'HEAD^{tree}')"
  if [ -n "$remote_tree" ] && [ "$remote_tree" = "$local_tree" ]; then
    git push --force-with-lease="refs/tags/${TAG}:${remote_ref}" origin "refs/tags/${TAG}:refs/tags/${TAG}"
    return
  fi
  fail "Remote tag ${TAG} exists and differs from this release tree."
}
verify_tree_parity(){
  "$VENV_PY" - "$1" "$2" <<'PYVERIFY'
from pathlib import Path
import hashlib,sys
ignored={'.git','.venv','venv','__pycache__'}
def inventory(root):
    root=Path(root).resolve()
    return {p.relative_to(root).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p in root.rglob('*') if p.is_file() and not any(x in ignored for x in p.parts)}
a,b=inventory(sys.argv[1]),inventory(sys.argv[2])
missing=sorted(set(a)-set(b));extra=sorted(set(b)-set(a));changed=sorted(k for k in set(a)&set(b) if a[k]!=b[k])
if missing or extra or changed:
    print('Repository synchronization parity failed.',file=sys.stderr)
    print('Missing: '+', '.join(missing[:20]),file=sys.stderr)
    print('Unexpected: '+', '.join(extra[:20]),file=sys.stderr)
    print('Changed: '+', '.join(changed[:20]),file=sys.stderr)
    raise SystemExit(1)
print(f'PASS - checksum parity confirmed across {len(a)} repository files')
PYVERIFY
}
create_existing_repo_backups(){
  local source_dir timestamp base backup bundle
  source_dir="$1"
  timestamp="$(date +%Y%m%d-%H%M%S)"
  base="$(basename "$source_dir")"
  backup="$DOWNLOADS/${base}-before-v${VERSION}-${timestamp}.zip"
  bundle="$DOWNLOADS/${base}-before-v${VERSION}-${timestamp}.bundle"
  printf '==> Creating safety backups\n'
  (cd "$(dirname "$source_dir")" && zip -qr "$backup" "$base" -x '*/.git/*' '*/.venv/*' '*/venv/*')
  git -C "$source_dir" bundle create "$bundle" --all >/dev/null 2>&1 || rm -f "$bundle"
  printf 'Safety ZIP: %s\n' "$backup"
  [ -f "$bundle" ] && printf 'Git bundle: %s\n' "$bundle"
}
PYTHON_BIN="$(select_python || true)"
[ -n "$PYTHON_BIN" ] || fail "Python 3.13 or 3.12 is required. Install with: brew install python@3.13"
for command_name in git unzip rsync zip shasum; do command -v "$command_name" >/dev/null 2>&1 || fail "$command_name is required."; done
ARCHIVE=""
for candidate in "$SCRIPT_DIR/$BUNDLE_ARCHIVE" "$DOWNLOADS/$BUNDLE_ARCHIVE" "$SCRIPT_DIR/$REPO_ARCHIVE" "$DOWNLOADS/$REPO_ARCHIVE"; do
  if [ -f "$candidate" ]; then ARCHIVE="$candidate"; break; fi
done
[ -n "$ARCHIVE" ] || fail "Place the v7.4.0 repository ZIP or release bundle beside this installer or in ~/Downloads."
TMP="$(mktemp -d "${TMPDIR:-/tmp}/scpsf-v740.XXXXXX")"
trap 'rm -rf "$TMP"' EXIT
printf '==> Product Support and Feedback Platform v%s installer\n' "$VERSION"
printf 'Installer revision: %s\n' "$INSTALLER_REVISION"
printf 'Python: %s\n' "$("$PYTHON_BIN" --version 2>&1)"
printf 'Archive: %s\n' "$ARCHIVE"
unzip -q "$ARCHIVE" -d "$TMP/archive"
SUMS_FILE="$(find "$TMP/archive" -maxdepth 3 -type f -name 'SHA256SUMS' -print -quit || true)"
if [ -z "$SUMS_FILE" ]; then
  for candidate in "$SCRIPT_DIR/SHA256SUMS" "$DOWNLOADS/SHA256SUMS" "$SCRIPT_DIR/$SUMS_ARCHIVE" "$DOWNLOADS/$SUMS_ARCHIVE"; do
    if [ -f "$candidate" ]; then SUMS_FILE="$candidate"; break; fi
  done
fi
[ -n "$SUMS_FILE" ] || fail "SHA256SUMS is required and was not found in the release bundle or beside the installer."
printf '==> Verifying release checksums\n'
if [ "$(dirname "$SUMS_FILE")" = "$(dirname "$ARCHIVE")" ] && [ "$(basename "$ARCHIVE")" = "$REPO_ARCHIVE" ]; then
  grep -F "  $REPO_ARCHIVE" "$SUMS_FILE" >/dev/null || fail "SHA256SUMS is missing an entry for $REPO_ARCHIVE."
  (cd "$(dirname "$ARCHIVE")" && shasum -a 256 -c "$(basename "$SUMS_FILE")" --ignore-missing) || fail "Release checksum verification failed."
else
  (cd "$(dirname "$SUMS_FILE")" && shasum -a 256 -c "$(basename "$SUMS_FILE")") || fail "Release checksum verification failed."
fi
printf 'PASS - release checksums verified\n'
REPO_ZIP="$(find "$TMP/archive" -type f -name "$REPO_ARCHIVE" -print -quit || true)"
if [ -n "$REPO_ZIP" ]; then
  mkdir -p "$TMP/repository"
  unzip -q "$REPO_ZIP" -d "$TMP/repository"
  SOURCE="$(find "$TMP/repository" -maxdepth 2 -type d -name 'sustainable-catalyst-product-support-feedback-v7.4.0-repository' -print -quit)"
else
  SOURCE="$(find "$TMP/archive" -maxdepth 2 -type d -name 'sustainable-catalyst-product-support-feedback-v7.4.0-repository' -print -quit)"
fi
[ -n "${SOURCE:-}" ] || fail "Could not locate the v7.4.0 repository source."
[ -f "$SOURCE/$VALIDATOR_SCRIPT" ] || fail "Missing $VALIDATOR_SCRIPT."
[ -f "$SOURCE/backend/requirements-validation.txt" ] || fail "Missing validation dependencies."
[ -f "$SOURCE/wordpress/$PLUGIN_SLUG/$PLUGIN_SLUG.php" ] || fail "WordPress compatibility plugin folder is missing."
[ ! -d "$SOURCE/wordpress/sustainable-catalyst-product-support-feedback" ] || fail "The WordPress plugin folder was incorrectly renamed."
grep -Fq 'VERSION="7.4.0"' "$SOURCE/$VALIDATOR_SCRIPT" || fail "Wrong validator version."
grep -Fq 'Version: 7.4.0' "$SOURCE/wordpress/$PLUGIN_SLUG/$PLUGIN_SLUG.php" || fail "Wrong plugin version."
grep -Fq 'Content-Catalyst-LLC/sustainable-catalyst-product-support-feedback' "$SOURCE/feature_suggestions_manifest.json" || fail "Canonical repository metadata is missing."
grep -Fq '"release_board"' "$SOURCE/feature_suggestions_manifest.json" || fail "Release board metadata is missing."
grep -Fq '"public_title": "Release Console"' "$SOURCE/feature_suggestions_manifest.json" || fail "Release Console metadata is missing."
grep -Fq "'layout' => 'terminal'" "$SOURCE/wordpress/$PLUGIN_SLUG/includes/class-scfs-release-board.php" || fail "Terminal Release Console layout is missing."
grep -Fq "'interval' => '7'" "$SOURCE/wordpress/$PLUGIN_SLUG/includes/class-scfs-release-board.php" || fail "Seven-second Release Console interval is missing."
grep -Fq 'data-console-action="toggle"' "$SOURCE/wordpress/$PLUGIN_SLUG/includes/class-scfs-release-board.php" || fail "Release Console controls are missing."
grep -Fq '<noscript>' "$SOURCE/wordpress/$PLUGIN_SLUG/includes/class-scfs-release-board.php" || fail "Release Console no-JavaScript fallback is missing."
grep -Fq 'data-console-announcer' "$SOURCE/wordpress/$PLUGIN_SLUG/includes/class-scfs-release-board.php" || fail "Release Console announcer is missing."
grep -Fq 'MutationObserver' "$SOURCE/wordpress/$PLUGIN_SLUG/assets/release-console-v7.4.0.js" || fail "Dynamic Release Console initialization is missing."
grep -Fq '[data-console-active="true"]' "$SOURCE/wordpress/$PLUGIN_SLUG/assets/release-board-v7.4.0.css" || fail "Stable Release Console screen layout is missing."
grep -Fq "'Analytics R'" "$SOURCE/wordpress/$PLUGIN_SLUG/includes/class-scfs-canonical-product-registry.php" || fail "Analytics R public label is missing."
grep -Fq "const SCHEMA = 'scfs-canonical-product-registry/2.0';" "$SOURCE/wordpress/$PLUGIN_SLUG/includes/class-scfs-canonical-product-registry.php" || fail "Registry schema 2.0 is missing."
grep -Fq 'integrity_report' "$SOURCE/wordpress/$PLUGIN_SLUG/includes/class-scfs-canonical-product-registry.php" || fail "Registry integrity reporting is missing."
grep -Fq 'apply_v740_governance_migrations' "$SOURCE/wordpress/$PLUGIN_SLUG/includes/class-scfs-canonical-product-registry.php" || fail "Registry migration tooling is missing."
grep -Fq 'product-registry/integrity' "$SOURCE/wordpress/$PLUGIN_SLUG/includes/class-scfs-canonical-product-registry.php" || fail "Registry integrity REST route is missing."
grep -Fq 'product-registry/migrations' "$SOURCE/wordpress/$PLUGIN_SLUG/includes/class-scfs-canonical-product-registry.php" || fail "Registry migration REST route is missing."
[ -f "$SOURCE/schemas/scfs-canonical-product-registry-v2.schema.json" ] || fail "Registry schema 2.0 artifact is missing."
if grep -Eq '(^|[[:space:]])(mapfile|readarray)([[:space:]]|$)' "$SOURCE/$VALIDATOR_SCRIPT"; then fail "Validator requires Bash 4."; fi
bash -n "$SOURCE/$VALIDATOR_SCRIPT" || fail "Invalid validator shell syntax."
if [ "${SCPSF_PREFLIGHT_ONLY:-0}" = "1" ]; then
  printf 'PREFLIGHT PASSED: selected the v7.4.0 Product Registry Governance package and preserved the WordPress plugin identity.\n'
  exit 0
fi
printf '==> Verifying canonical GitHub repository access\n'
if ! git ls-remote "$REMOTE" HEAD >/dev/null 2>&1; then
  fail "The canonical GitHub repository is not reachable. Confirm the repository name and SSH access, then run this installer again."
fi
printf 'PASS - canonical GitHub repository is reachable\n'
printf '==> Preparing compatible Python validation environment\n'
"$PYTHON_BIN" -m venv "$TMP/venv"
VENV_PY="$TMP/venv/bin/python"
"$VENV_PY" -m pip install --upgrade pip >/dev/null
printf '==> Installing backend and validation dependencies\n'
"$VENV_PY" -m pip install --only-binary=pydantic-core -r "$SOURCE/backend/requirements-validation.txt"
"$VENV_PY" -c 'import fastapi,httpx,pydantic,pytest' || fail "Validation packages missing."
printf '==> Validating packaged source\n'
PYTHON_BIN="$VENV_PY" bash "$SOURCE/$VALIDATOR_SCRIPT"
if [ -d "$CANONICAL_REPO_DIR/.git" ]; then
  REPO_DIR="$CANONICAL_REPO_DIR"
  create_existing_repo_backups "$REPO_DIR"
elif [ -d "$LEGACY_REPO_DIR/.git" ]; then
  [ ! -e "$CANONICAL_REPO_DIR" ] || fail "Both legacy and canonical local paths exist. Move or remove the conflicting canonical path before continuing."
  create_existing_repo_backups "$LEGACY_REPO_DIR"
  printf '==> Renaming local repository folder\n'
  mv "$LEGACY_REPO_DIR" "$CANONICAL_REPO_DIR"
  REPO_DIR="$CANONICAL_REPO_DIR"
  printf 'PASS - local repository renamed to %s\n' "$REPO_DIR"
elif [ -e "$CANONICAL_REPO_DIR" ]; then
  fail "$CANONICAL_REPO_DIR exists but is not a Git repository."
elif [ -e "$LEGACY_REPO_DIR" ]; then
  fail "$LEGACY_REPO_DIR exists but is not a Git repository."
else
  printf '==> Cloning canonical repository main\n'
  git clone --branch main "$REMOTE" "$CANONICAL_REPO_DIR"
  REPO_DIR="$CANONICAL_REPO_DIR"
fi
printf '==> Synchronizing local main with canonical origin/main\n'
git -C "$REPO_DIR" remote set-url origin "$REMOTE"
git -C "$REPO_DIR" fetch origin main --tags
git -C "$REPO_DIR" reset --hard
git -C "$REPO_DIR" clean -fd
git -C "$REPO_DIR" checkout -B main origin/main
printf '==> Installing v7.4.0 source with checksum verification\n'
rsync -a --checksum --delete --exclude '.git/' --exclude '.venv/' --exclude 'venv/' "$SOURCE/" "$REPO_DIR/"
printf '==> Verifying post-sync source parity\n'
verify_tree_parity "$SOURCE" "$REPO_DIR"
cd "$REPO_DIR"
PYTHON_BIN="$VENV_PY" bash "./$VALIDATOR_SCRIPT"
git diff --check
git add -A
if git diff --cached --quiet; then
  printf 'No source changes to commit. Current main may already contain v7.4.0.\n'
else
  git commit -m "Product Support and Feedback Platform v7.4.0 — $RELEASE_TITLE"
fi
printf '==> Refreshing origin/main before push\n'
git fetch origin main
if ! git merge-base --is-ancestor origin/main HEAD; then
  git rebase origin/main
  PYTHON_BIN="$VENV_PY" bash "./$VALIDATOR_SCRIPT"
  git diff --check
fi
create_release_tag
printf '==> Pushing main\n'
if ! git push origin HEAD:main; then
  git fetch origin main
  git rebase origin/main
  PYTHON_BIN="$VENV_PY" bash "./$VALIDATOR_SCRIPT"
  git diff --check
  create_release_tag
  git push origin HEAD:main
fi
printf '==> Pushing tag %s\n' "$TAG"
push_release_tag
printf '\nSUCCESS: v7.4.0 validated, installed, committed, tagged, and pushed.\n'
printf 'Local repository: %s\n' "$REPO_DIR"
printf 'WordPress plugin folder preserved: %s\n' "$PLUGIN_SLUG"
