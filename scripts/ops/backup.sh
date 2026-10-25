#!/usr/bin/env bash
# Encrypted backup of the database and private files.
#
#   BACKUP_DIR=/backups BACKUP_AGE_RECIPIENT=age1… scripts/ops/backup.sh
#
# Uses the DB_* variables from the environment. Output is encrypted with age
# to the recipient's public key; only the offline private key can decrypt it.
# Writes a SHA-256 manifest next to the files and prunes old backups.
set -euo pipefail

: "${BACKUP_DIR:?Set BACKUP_DIR}"
: "${BACKUP_AGE_RECIPIENT:?Set BACKUP_AGE_RECIPIENT (age public key)}"
: "${DB_DATABASE:?Set DB_DATABASE}"
STORAGE_PATH="${STORAGE_PATH:-$(cd "$(dirname "$0")/../.." && pwd)/storage/app}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
umask 077
mkdir -p "$BACKUP_DIR"

export PGHOST="${DB_HOST:-127.0.0.1}" PGPORT="${DB_PORT:-5432}" PGUSER="${DB_USERNAME:-postgres}" PGPASSWORD="${DB_PASSWORD:-}" PGSSLMODE="${DB_SSLMODE:-prefer}"

db="$BACKUP_DIR/db-$STAMP.dump.age"
files="$BACKUP_DIR/files-$STAMP.tar.gz.age"

# Custom format: compressed, restorable table by table, consistent snapshot.
pg_dump --format=custom --no-owner --no-privileges "$DB_DATABASE" | age --encrypt --recipient "$BACKUP_AGE_RECIPIENT" > "$db"
# Private uploads (evidence, exports, media). Livewire temporary uploads are skipped.
tar --exclude='livewire-tmp' -C "$STORAGE_PATH" -czf - . | age --encrypt --recipient "$BACKUP_AGE_RECIPIENT" > "$files"

( cd "$BACKUP_DIR" && sha256sum "$(basename "$db")" "$(basename "$files")" > "manifest-$STAMP.sha256" )

find "$BACKUP_DIR" -maxdepth 1 -type f \( -name 'db-*.age' -o -name 'files-*.age' -o -name 'manifest-*.sha256' \) -mtime "+$RETENTION_DAYS" -delete

echo "Backup $STAMP written to $BACKUP_DIR ($(du -h "$db" | cut -f1) database, $(du -h "$files" | cut -f1) files)."
