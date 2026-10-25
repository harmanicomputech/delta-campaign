#!/usr/bin/env bash
# Restores an encrypted database backup into a NEW database. Never overwrites
# an existing database: restoring over production is a deliberate, separate
# step (rename databases during a maintenance window, see docs/RUNBOOK.md).
#
#   BACKUP_AGE_IDENTITY=/secure/backup.key scripts/ops/restore.sh db-…dump.age campaign_restored
set -euo pipefail

backup="${1:?Usage: restore.sh <db-backup.dump.age> <new-database-name>}"
target="${2:?Usage: restore.sh <db-backup.dump.age> <new-database-name>}"
: "${BACKUP_AGE_IDENTITY:?Set BACKUP_AGE_IDENTITY (path to the age private key)}"
[[ "$target" =~ ^[a-z_][a-z0-9_]{0,62}$ ]] || { echo "Invalid database name: $target" >&2; exit 2; }

export PGHOST="${DB_HOST:-127.0.0.1}" PGPORT="${DB_PORT:-5432}" PGUSER="${DB_USERNAME:-postgres}" PGPASSWORD="${DB_PASSWORD:-}" PGSSLMODE="${DB_SSLMODE:-prefer}"

manifest="$(dirname "$backup")/manifest-$(basename "$backup" | sed -E 's/^db-(.*)\.dump\.age$/\1/').sha256"
if [[ -f "$manifest" ]]; then
  ( cd "$(dirname "$backup")" && grep " $(basename "$backup")\$" "$(basename "$manifest")" | sha256sum --check --quiet ) || { echo "Checksum mismatch for $backup" >&2; exit 3; }
  echo "Checksum verified."
fi

if psql -d postgres -tAc "select 1 from pg_database where datname = '$target'" | grep -q 1; then
  echo "Database $target already exists. Choose a new name." >&2
  exit 4
fi

createdb "$target"
psql -d "$target" -qc 'create extension if not exists postgis; create extension if not exists pg_trgm;'
age --decrypt --identity "$BACKUP_AGE_IDENTITY" "$backup" | pg_restore --no-owner --no-privileges --exit-on-error -d "$target"
echo "Restored into $target."
