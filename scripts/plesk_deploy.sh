#!/usr/bin/env bash
set -euo pipefail

# Run this script from the Plesk deployment source directory.
# Example use in Plesk "Additional deployment actions":
# bash scripts/plesk_deploy.sh

SRC_DIR="$(pwd)"
VHOST_ROOT="${PLESK_VHOST_ROOT:-$HOME}"
WEBROOT="${PLESK_WEBROOT:-$VHOST_ROOT/httpdocs}"
PRIVATE_ROOT="${PLESK_PRIVATE_ROOT:-$VHOST_ROOT/vvedeurbel-private}"

mkdir -p "$WEBROOT"
mkdir -p "$PRIVATE_ROOT/src" "$PRIVATE_ROOT/migrations" "$PRIVATE_ROOT/scripts" "$PRIVATE_ROOT/arduino" "$PRIVATE_ROOT/data"

sync_dir() {
  local from_dir="$1"
  local to_dir="$2"
  local required="${3:-false}"

  mkdir -p "$to_dir"

  if [[ -d "$from_dir" ]]; then
    rsync -a --delete "$from_dir/" "$to_dir/"
    return
  fi

  if [[ "$required" == "true" ]]; then
    echo "ERROR: required directory missing: $from_dir" >&2
    exit 1
  fi

  echo "Skip optional directory (not present in source): $from_dir"
}

# 1) Deploy only public web files into webroot.
sync_dir "$SRC_DIR/public" "$WEBROOT" true

# 2) Deploy non-public application files outside webroot.
sync_dir "$SRC_DIR/src" "$PRIVATE_ROOT/src" true
sync_dir "$SRC_DIR/migrations" "$PRIVATE_ROOT/migrations"
sync_dir "$SRC_DIR/scripts" "$PRIVATE_ROOT/scripts"
sync_dir "$SRC_DIR/arduino" "$PRIVATE_ROOT/arduino"
sync_dir "$SRC_DIR/data" "$PRIVATE_ROOT/data"

# 3) Keep env file outside webroot. Create once if missing.
if [[ -f "$SRC_DIR/.env" ]]; then
  cp -f "$SRC_DIR/.env" "$PRIVATE_ROOT/.env"
elif [[ ! -f "$PRIVATE_ROOT/.env" && -f "$SRC_DIR/.env.example" ]]; then
  cp -f "$SRC_DIR/.env.example" "$PRIVATE_ROOT/.env"
fi

# 4) Compatibility symlink so current PHP includes keep working:
# public/index.php requires ../src, ../.env etc.
ln -sfn "$PRIVATE_ROOT/src" "$VHOST_ROOT/src"
ln -sfn "$PRIVATE_ROOT/migrations" "$VHOST_ROOT/migrations"
ln -sfn "$PRIVATE_ROOT/scripts" "$VHOST_ROOT/scripts"
ln -sfn "$PRIVATE_ROOT/arduino" "$VHOST_ROOT/arduino"
ln -sfn "$PRIVATE_ROOT/data" "$VHOST_ROOT/data"
ln -sfn "$PRIVATE_ROOT/.env" "$VHOST_ROOT/.env"

echo "Deploy complete"
echo "WEBROOT: $WEBROOT"
echo "PRIVATE_ROOT: $PRIVATE_ROOT"
