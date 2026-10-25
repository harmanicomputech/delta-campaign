#!/usr/bin/env bash
# Backup → restore into a scratch database → verify → drop. Run monthly and before
# each election-day freeze. Exits non-zero if anything does not match.
#
#   BACKUP_DIR=… BACKUP_AGE_RECIPIENT=… BACKUP_AGE_IDENTITY=… scripts/ops/restore-test.sh
set -euo pipefail
cd "$(dirname "$0")/../.."
export PGHOST="${DB_HOST:-127.0.0.1}" PGPORT="${DB_PORT:-5432}" PGUSER="${DB_USERNAME:-postgres}" PGPASSWORD="${DB_PASSWORD:-}" PGSSLMODE="${DB_SSLMODE:-prefer}"

scripts/ops/backup.sh
latest="$(ls -1t "$BACKUP_DIR"/db-*.dump.age | head -1)"
scratch="restore_check_$(date -u +%Y%m%d%H%M%S)"
trap 'dropdb --if-exists "$scratch" >/dev/null 2>&1 || true' EXIT

started=$(date +%s)
scripts/ops/restore.sh "$latest" "$scratch"
echo "Restore took $(( $(date +%s) - started ))s."

# Same tables, same row counts (exact counts; the source may have moved on only if written to during the test).
count_sql="select string_agg(format('%s=%s', table_name, (xpath('/row/c/text()', query_to_xml(format('select count(*) as c from %I', table_name), false, true, '')))[1]::text), ',' order by table_name)
           from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'"
source_counts="$(psql -d "$DB_DATABASE" -tAc "$count_sql")"
restored_counts="$(psql -d "$scratch" -tAc "$count_sql")"
tables=$(echo "$restored_counts" | tr ',' '\n' | wc -l)
if [[ "$source_counts" != "$restored_counts" ]]; then
  diff <(echo "$source_counts" | tr ',' '\n') <(echo "$restored_counts" | tr ',' '\n') || true
  echo "Row counts differ (expected only if the source was written to during the test)." >&2
  exit 5
fi
echo "Row counts match across $tables tables."

# The audit log's hash chain must verify on the restored copy.
DB_DATABASE="$scratch" php artisan audit:verify
echo "Restore test passed."
