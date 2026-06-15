#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 5 ]]; then
  echo "Gebruik: $0 <host> <port> <db> <user> <password>"
  exit 1
fi

HOST="$1"
PORT="$2"
DB="$3"
USER="$4"
PASS="$5"

mysql -h "$HOST" -P "$PORT" -u "$USER" -p"$PASS" "$DB" < "$(dirname "$0")/../migrations/001_init.sql"
echo "Migratie uitgevoerd op database: $DB"
