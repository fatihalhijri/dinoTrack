#!/usr/bin/env bash
#
# Backup harian database DinoTrack (docs/10-deploy.md, Backup). Dijalankan cron sebagai user
# dinotrack pukul 03:30, setelah pembersihan data lama (02:00-02:10).
#
# Variabel opsional:
#   BACKUP_DIR           folder backup lokal (default /var/backups/dinotrack)
#   RETENTION_DAYS       umur backup lokal sebelum dihapus (default 14)
#   DB_NAME              nama database (default dinotrack)
#   MYSQL_DEFAULTS_FILE  file kredensial user backup (default ~/.my-backup.cnf, chmod 600)
#   RCLONE_REMOTE        tujuan salinan offsite, misalnya "offsite:dinotrack-backup"; kosong = lokal saja

set -Eeuo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/dinotrack}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
DB_NAME="${DB_NAME:-dinotrack}"
MYSQL_DEFAULTS_FILE="${MYSQL_DEFAULTS_FILE:-$HOME/.my-backup.cnf}"
RCLONE_REMOTE="${RCLONE_REMOTE:-}"

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

umask 077
mkdir -p "$BACKUP_DIR"

target="${BACKUP_DIR}/${DB_NAME}-$(date +%Y%m%d-%H%M%S).sql.gz"
partial="${target}.partial"
trap 'rm -f "$partial"; log "Backup GAGAL."' ERR

# Password di file kredensial, bukan argumen, agar tidak terlihat di daftar proses.
# --single-transaction: snapshot konsisten InnoDB tanpa mengunci tabel (aplikasi tetap berjalan).
log "Membuat backup ${DB_NAME}"
mysqldump \
    --defaults-extra-file="$MYSQL_DEFAULTS_FILE" \
    --single-transaction \
    --quick \
    --no-tablespaces \
    --set-gtid-purged=OFF \
    --default-character-set=utf8mb4 \
    "$DB_NAME" | gzip -9 > "$partial"

gzip -t "$partial"
mv "$partial" "$target"
log "Tersimpan: ${target} ($(du -h "$target" | cut -f1))"

if [[ -n "$RCLONE_REMOTE" ]]; then
    log "Menyalin ke ${RCLONE_REMOTE}"
    rclone copy "$target" "$RCLONE_REMOTE"
fi

find "$BACKUP_DIR" -maxdepth 1 -name "${DB_NAME}-*.sql.gz" -mtime +"$RETENTION_DAYS" -print -delete
log "Selesai."
