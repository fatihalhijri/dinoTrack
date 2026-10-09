#!/usr/bin/env bash
#
# Deploy rutin DinoTrack di server production (docs/10-deploy.md).
# Jalankan sebagai user aplikasi, bukan root:
#
#   sudo -iu dinotrack bash /var/www/dinotrack/deploy/deploy.sh
#
# Variabel opsional: DEPLOY_BRANCH (default master), PHP_FPM_SERVICE (default php8.4-fpm).
# Deploy pertama kali tidak memakai script ini; ikuti "Deploy pertama" di docs/10-deploy.md.

set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_DIR="$(git -C "$APP_DIR" rev-parse --show-toplevel)"
BRANCH="${DEPLOY_BRANCH:-master}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"

log() {
    printf '\n[%s] %s\n' "$(date '+%H:%M:%S')" "$*"
}

on_error() {
    echo >&2
    echo "Deploy GAGAL di baris $1. Aplikasi dibiarkan dalam mode maintenance agar tidak berjalan setengah jadi." >&2
    echo "Perbaiki penyebabnya lalu jalankan ulang script ini, atau kembalikan versi lama (docs/10-deploy.md, Rollback)." >&2
}
trap 'on_error $LINENO' ERR

# Dua deploy bersamaan akan saling menimpa vendor/ dan public/build/.
exec 9>"/tmp/dinotrack-deploy.lock"
if ! flock -n 9; then
    echo "Deploy lain sedang berjalan." >&2
    exit 1
fi

cd "$APP_DIR"

if [[ -n "$(git -C "$REPO_DIR" status --porcelain --untracked-files=no)" ]]; then
    echo "Ada perubahan lokal di server yang belum di-commit; deploy dibatalkan:" >&2
    git -C "$REPO_DIR" status --short --untracked-files=no >&2
    exit 1
fi

# Fetch sebelum maintenance agar downtime tidak termasuk waktu unduh.
log "Mengambil perubahan dari origin/${BRANCH}"
git -C "$REPO_DIR" fetch --prune origin "$BRANCH"
previous_commit="$(git -C "$REPO_DIR" rev-parse --short HEAD)"

log "Masuk mode maintenance"
php artisan down --retry=60 --refresh=15

log "Memperbarui kode (${previous_commit} -> origin/${BRANCH})"
git -C "$REPO_DIR" merge --ff-only "origin/${BRANCH}"

log "Memasang dependensi PHP"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress

log "Membangun aset frontend"
npm ci --no-audit --no-fund
npm run build

# Logo usaha dilayani dari public/storage; perintah ini aman diulang (link yang ada dilewati).
log "Memastikan symlink public/storage"
php artisan storage:link

# Cache dibuat ulang sebelum migrate agar migrate memakai konfigurasi versi baru.
log "Membuat cache config, event, route, dan view"
php artisan optimize

log "Menjalankan migration"
php artisan migrate --force

# opcache.validate_timestamps=0: tanpa reload, PHP-FPM tetap menjalankan kode lama.
log "Reload ${PHP_FPM_SERVICE}"
sudo -n systemctl reload "$PHP_FPM_SERVICE"

log "Keluar dari mode maintenance"
php artisan up

# Worker menyelesaikan job yang sedang berjalan lalu dimulai ulang Supervisor dengan kode baru.
log "Memulai ulang queue worker"
php artisan queue:restart

log "Deploy selesai: ${previous_commit} -> $(git -C "$REPO_DIR" rev-parse --short HEAD)"
php artisan billing:health || echo "Periksa hasil billing:health di atas." >&2
