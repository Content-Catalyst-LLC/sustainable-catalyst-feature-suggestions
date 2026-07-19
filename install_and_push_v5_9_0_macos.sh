#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="5.9.0"
RELEASE_TITLE="Public API, Embeds, and Institutional Support Integration"
REMOTE="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git"
DOWNLOADS="${HOME}/Downloads"
REPO_DIR="$DOWNLOADS/sustainable-catalyst-feature-suggestions"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
select_python(){
 for candidate in /opt/homebrew/bin/python3.13 python3.13 /opt/homebrew/bin/python3.12 python3.12; do
  if command -v "$candidate" >/dev/null 2>&1; then
   local v; v="$($candidate -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")')"
   case "$v" in 3.12|3.13) printf '%s' "$candidate"; return 0;; esac
  fi
 done
 return 1
}
PYTHON_BIN="$(select_python || true)"
[ -n "$PYTHON_BIN" ] || fail "Python 3.13 or 3.12 is required. Install with: brew install python@3.13"
command -v git >/dev/null 2>&1 || fail "Git is required."
command -v unzip >/dev/null 2>&1 || fail "unzip is required."
ARCHIVE=""
for candidate in \
 "$SCRIPT_DIR/sustainable-catalyst-feature-suggestions-v5.9.0-repo.zip" \
 "$DOWNLOADS/sustainable-catalyst-feature-suggestions-v5.9.0-repo.zip" \
 "$SCRIPT_DIR/sustainable-catalyst-product-support-feedback-platform-v5.9.0-release-bundle.zip" \
 "$DOWNLOADS/sustainable-catalyst-product-support-feedback-platform-v5.9.0-release-bundle.zip"; do
 [ -f "$candidate" ] && { ARCHIVE="$candidate"; break; }
done
[ -n "$ARCHIVE" ] || fail "Place the v5.9.0 repository ZIP or release bundle beside this installer or in ~/Downloads."
TMP="$(mktemp -d "${TMPDIR:-/tmp}/scfs-v590.XXXXXX")"
cleanup(){ rm -rf "$TMP"; }
trap cleanup EXIT
printf '==> Product Support and Feedback Platform v%s\n' "$VERSION"
printf 'Python: %s\n' "$($PYTHON_BIN --version 2>&1)"
printf 'Archive: %s\n' "$ARCHIVE"
unzip -q "$ARCHIVE" -d "$TMP/archive"
REPO_ZIP="$(find "$TMP/archive" -type f -name 'sustainable-catalyst-feature-suggestions-v5.9.0-repo.zip' -print -quit || true)"
if [ -n "$REPO_ZIP" ]; then mkdir -p "$TMP/repository"; unzip -q "$REPO_ZIP" -d "$TMP/repository"; SOURCE="$(find "$TMP/repository" -maxdepth 2 -type d -name 'sustainable-catalyst-feature-suggestions-v5.9.0-repository' -print -quit)"; else SOURCE="$(find "$TMP/archive" -maxdepth 2 -type d -name 'sustainable-catalyst-feature-suggestions-v5.9.0-repository' -print -quit)"; fi
[ -n "${SOURCE:-}" ] || fail "Could not locate the v5.9.0 repository source."
printf '==> Validating packaged source\n'
PYTHON_BIN="$PYTHON_BIN" bash "$SOURCE/validate_v5_9_0.sh"
if [ -d "$REPO_DIR/.git" ]; then
 BACKUP="$DOWNLOADS/sustainable-catalyst-feature-suggestions-before-v5.9.0-$(date +%Y%m%d-%H%M%S).zip"
 printf '==> Creating safety backup\n'
 (cd "$(dirname "$REPO_DIR")" && zip -qr "$BACKUP" "$(basename "$REPO_DIR")" -x '*/.git/*' '*/.venv/*' '*/venv/*')
else
 printf '==> Cloning repository\n'
 rm -rf "$REPO_DIR"
 git clone "$REMOTE" "$REPO_DIR"
fi
printf '==> Installing v5.9.0 source\n'
rsync -a --delete --exclude '.git/' --exclude '.venv/' --exclude 'venv/' "$SOURCE/" "$REPO_DIR/"
cd "$REPO_DIR"
PYTHON_BIN="$PYTHON_BIN" bash ./validate_v5_9_0.sh
git diff --check
git add -A
if git diff --cached --quiet; then printf 'No source changes to commit.\n'; else git commit -m "Product Support and Feedback Platform v5.9.0 — $RELEASE_TITLE"; fi
if git rev-parse -q --verify refs/tags/v5.9.0 >/dev/null; then git tag -d v5.9.0; fi
git tag -a v5.9.0 -m "Product Support and Feedback Platform v5.9.0"
printf '==> Pushing main and tag\n'
git push origin main
git push origin v5.9.0
printf '\nSUCCESS: v5.9.0 validated, committed, tagged, and pushed.\n'
