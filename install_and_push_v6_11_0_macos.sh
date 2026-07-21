#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="6.11.0"
INSTALLER_REVISION="V6_11_0_CHECKSUM_SYNC_PARITY"
RELEASE_TITLE="API, Webhooks, and External Integrations"
REMOTE="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git"
DOWNLOADS="${HOME}/Downloads"
REPO_DIR="$DOWNLOADS/sustainable-catalyst-feature-suggestions"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TAG="v${VERSION}"
VALIDATOR_SCRIPT="validate_v6_11_0.sh"
REPO_ARCHIVE="sustainable-catalyst-feature-suggestions-v6.11.0-repo.zip"
BUNDLE_ARCHIVE="sustainable-catalyst-product-support-feedback-platform-v6.11.0-release-bundle.zip"
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
select_python(){ local c v;for c in /opt/homebrew/bin/python3.13 python3.13 /opt/homebrew/bin/python3.12 python3.12;do if command -v "$c" >/dev/null 2>&1;then v="$("$c" -c 'import sys;print(f"{sys.version_info.major}.{sys.version_info.minor}")')";case "$v" in 3.12|3.13)printf '%s' "$c";return 0;;esac;fi;done;return 1; }
remote_tag_ref_oid(){ git ls-remote --tags origin "refs/tags/${TAG}"|awk 'NR==1{print $1}'; }
remote_tag_commit_oid(){ local p d;p="$(git ls-remote --tags origin "refs/tags/${TAG}^{}"|awk 'NR==1{print $1}')";if [ -n "$p" ];then printf '%s' "$p";return;fi;d="$(remote_tag_ref_oid)";[ -n "$d" ]&&printf '%s' "$d"; }
create_release_tag(){ if git rev-parse -q --verify "refs/tags/${TAG}" >/dev/null 2>&1;then git tag -d "$TAG" >/dev/null;fi;git tag -a "$TAG" -m "Product Support and Feedback Platform v${VERSION} — ${RELEASE_TITLE}"; }
push_release_tag(){ local rr rc rt lt;rr="$(remote_tag_ref_oid)";if [ -z "$rr" ];then git push origin "$TAG";return;fi;rc="$(remote_tag_commit_oid)";rt="$(git rev-parse "${rc}^{tree}" 2>/dev/null||true)";lt="$(git rev-parse 'HEAD^{tree}')";if [ -n "$rt" ]&&[ "$rt" = "$lt" ];then git push --force-with-lease="refs/tags/${TAG}:${rr}" origin "refs/tags/${TAG}:refs/tags/${TAG}";return;fi;fail "Remote tag ${TAG} exists and differs from this release tree."; }
verify_tree_parity(){ "$VENV_PY" - "$1" "$2" <<'PYVERIFY'
from pathlib import Path
import hashlib,sys
ignored={'.git','.venv','venv','__pycache__'}
def inv(root):
 root=Path(root).resolve();return {p.relative_to(root).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p in root.rglob('*') if p.is_file() and not any(x in ignored for x in p.parts)}
a,b=inv(sys.argv[1]),inv(sys.argv[2]);missing=sorted(set(a)-set(b));extra=sorted(set(b)-set(a));changed=sorted(k for k in set(a)&set(b) if a[k]!=b[k])
if missing or extra or changed:
 print('Repository synchronization parity failed.',file=sys.stderr);print('Missing: '+', '.join(missing[:20]),file=sys.stderr);print('Unexpected: '+', '.join(extra[:20]),file=sys.stderr);print('Changed: '+', '.join(changed[:20]),file=sys.stderr);raise SystemExit(1)
print(f'PASS - checksum parity confirmed across {len(a)} repository files')
PYVERIFY
}
PYTHON_BIN="$(select_python||true)";[ -n "$PYTHON_BIN" ]||fail "Python 3.13 or 3.12 is required. Install with: brew install python@3.13"
for c in git unzip rsync zip;do command -v "$c" >/dev/null 2>&1||fail "$c is required.";done
ARCHIVE="";for c in "$SCRIPT_DIR/$REPO_ARCHIVE" "$DOWNLOADS/$REPO_ARCHIVE" "$SCRIPT_DIR/$BUNDLE_ARCHIVE" "$DOWNLOADS/$BUNDLE_ARCHIVE";do if [ -f "$c" ];then ARCHIVE="$c";break;fi;done
[ -n "$ARCHIVE" ]||fail "Place the v6.11.0 repository ZIP or release bundle beside this installer or in ~/Downloads."
TMP="$(mktemp -d "${TMPDIR:-/tmp}/scfs-v6110.XXXXXX")";trap 'rm -rf "$TMP"' EXIT
printf '==> Product Support and Feedback Platform v%s installer\n' "$VERSION";printf 'Installer revision: %s\n' "$INSTALLER_REVISION";printf 'Python: %s\n' "$("$PYTHON_BIN" --version 2>&1)";printf 'Archive: %s\n' "$ARCHIVE"
unzip -q "$ARCHIVE" -d "$TMP/archive";REPO_ZIP="$(find "$TMP/archive" -type f -name "$REPO_ARCHIVE" -print -quit||true)"
if [ -n "$REPO_ZIP" ];then mkdir -p "$TMP/repository";unzip -q "$REPO_ZIP" -d "$TMP/repository";SOURCE="$(find "$TMP/repository" -maxdepth 2 -type d -name 'sustainable-catalyst-feature-suggestions-v6.11.0-repository' -print -quit)";else SOURCE="$(find "$TMP/archive" -maxdepth 2 -type d -name 'sustainable-catalyst-feature-suggestions-v6.11.0-repository' -print -quit)";fi
[ -n "${SOURCE:-}" ]||fail "Could not locate the v6.11.0 repository source."
[ -f "$SOURCE/$VALIDATOR_SCRIPT" ]||fail "Missing $VALIDATOR_SCRIPT.";[ -f "$SOURCE/backend/requirements-validation.txt" ]||fail "Missing validation dependencies.";[ -f "$SOURCE/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-api-integrations.php" ]||fail "Missing API integration implementation.";[ -f "$SOURCE/schemas/scfs-help-desk-api-integrations-v1.schema.json" ]||fail "Missing API integration schema."
grep -Fq 'VERSION="6.11.0"' "$SOURCE/$VALIDATOR_SCRIPT"||fail "Wrong validator version.";grep -Fq 'CONTRACT_EXECUTION_MODE="sequential-state-safe"' "$SOURCE/$VALIDATOR_SCRIPT"||fail "Missing state-safe contract mode.";grep -Fq 'Version: 6.11.0' "$SOURCE/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php"||fail "Wrong plugin version."
if grep -Eq '(^|[[:space:]])(mapfile|readarray)([[:space:]]|$)' "$SOURCE/$VALIDATOR_SCRIPT";then fail "Validator requires Bash 4.";fi;bash -n "$SOURCE/$VALIDATOR_SCRIPT"||fail "Invalid validator shell syntax."
if [ "${SCFS_PREFLIGHT_ONLY:-0}" = "1" ];then printf 'PREFLIGHT PASSED: selected the v6.11.0 archive and checksum-sync parity installer.\n';exit 0;fi
printf '==> Preparing compatible Python validation environment\n';"$PYTHON_BIN" -m venv "$TMP/venv";VENV_PY="$TMP/venv/bin/python";"$VENV_PY" -m pip install --upgrade pip >/dev/null
printf '==> Installing backend and validation dependencies\n';"$VENV_PY" -m pip install --only-binary=pydantic-core -r "$SOURCE/backend/requirements-validation.txt";"$VENV_PY" -c 'import fastapi,httpx,pydantic,pytest'||fail "Validation packages missing.";"$VENV_PY" -m pytest --version
printf '==> Validating packaged source\n';PYTHON_BIN="$VENV_PY" bash "$SOURCE/$VALIDATOR_SCRIPT"
if [ -d "$REPO_DIR/.git" ];then TS="$(date +%Y%m%d-%H%M%S)";BACKUP="$DOWNLOADS/sustainable-catalyst-feature-suggestions-before-v6.11.0-${TS}.zip";BUNDLE="$DOWNLOADS/sustainable-catalyst-feature-suggestions-before-v6.11.0-${TS}.bundle";printf '==> Creating safety backups\n';(cd "$(dirname "$REPO_DIR")"&&zip -qr "$BACKUP" "$(basename "$REPO_DIR")" -x '*/.git/*' '*/.venv/*' '*/venv/*');git -C "$REPO_DIR" bundle create "$BUNDLE" --all >/dev/null 2>&1||rm -f "$BUNDLE";printf '==> Synchronizing local main with current origin/main\n';git -C "$REPO_DIR" remote set-url origin "$REMOTE";git -C "$REPO_DIR" fetch origin main --tags;git -C "$REPO_DIR" reset --hard;git -C "$REPO_DIR" clean -fd;git -C "$REPO_DIR" checkout -B main origin/main;else printf '==> Cloning current repository main\n';rm -rf "$REPO_DIR";git clone --branch main "$REMOTE" "$REPO_DIR";fi
printf '==> Installing v6.11.0 source with checksum verification\n';rsync -a --checksum --delete --exclude '.git/' --exclude '.venv/' --exclude 'venv/' "$SOURCE/" "$REPO_DIR/";printf '==> Verifying post-sync source parity\n';verify_tree_parity "$SOURCE" "$REPO_DIR"
cd "$REPO_DIR";PYTHON_BIN="$VENV_PY" bash "./$VALIDATOR_SCRIPT";git diff --check;git add -A
if git diff --cached --quiet;then printf 'No source changes to commit. Current main may already contain v6.11.0.\n';else git commit -m "Product Support and Feedback Platform v6.11.0 — $RELEASE_TITLE";fi
printf '==> Refreshing origin/main before push\n';git fetch origin main;if ! git merge-base --is-ancestor origin/main HEAD;then git rebase origin/main;PYTHON_BIN="$VENV_PY" bash "./$VALIDATOR_SCRIPT";git diff --check;fi
create_release_tag;printf '==> Pushing main\n';if ! git push origin HEAD:main;then git fetch origin main;git rebase origin/main;PYTHON_BIN="$VENV_PY" bash "./$VALIDATOR_SCRIPT";git diff --check;create_release_tag;git push origin HEAD:main;fi
printf '==> Pushing tag %s\n' "$TAG";push_release_tag;printf '\nSUCCESS: v6.11.0 synchronized by checksum, parity-verified, validated, committed, tagged, and pushed.\nLocal repository: %s\n' "$REPO_DIR"
