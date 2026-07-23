#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PYTHON_SCRIPT="$SCRIPT_DIR/reconcile_canonical_product_registry.py"

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

command -v python3 >/dev/null 2>&1 || fail "Python 3 is required."
[ -f "$PYTHON_SCRIPT" ] || fail "Missing $PYTHON_SCRIPT"

if [ "$#" -eq 0 ]; then
  cat <<'EOF'
Canonical Product Registry reconciler

Dry-run against WordPress:
  ./reconcile_canonical_product_registry_macos.sh \
    --wordpress-path "/path/to/wordpress"

Apply after reviewing the dry-run files:
  ./reconcile_canonical_product_registry_macos.sh \
    --wordpress-path "/path/to/wordpress" \
    --apply

Optional local repository version scan:
  ./reconcile_canonical_product_registry_macos.sh \
    --wordpress-path "/path/to/wordpress" \
    --products-root "$HOME/Downloads" \
    --apply

Offline exported-registry mode:
  ./reconcile_canonical_product_registry_macos.sh \
    --input canonical-product-registry.json
EOF
  exit 0
fi

exec python3 "$PYTHON_SCRIPT" "$@"
