# 10 — Deploy Production

Target: satu VPS **Ubuntu 24.04** dengan Nginx, PHP-FPM **8.4**, MySQL 8, Redis, Supervisor,
dan HTTPS Let's Encrypt. Semua file konfigurasi ada di folder `deploy/`; dokumen ini menjelaskan
urutan pemasangannya. Contoh memakai domain `billing.example.com`, user Linux `dinotrack`, dan
folder `/var/www/dinotrack` (root repo git sekaligus folder aplikasi Laravel).

> PHP 8.4, bukan 8.3 seperti prompt Tahap 11: `composer.lock` dan keputusan 2026-10-04 memakai
> PHP 8.4, sedangkan Ubuntu 24.04 bawaannya 8.3. PHP 8.4 dipasang dari PPA `ondrej/php`.

## Gambaran

```
Internet ──443──> Nginx ──fastcgi──> PHP-FPM (pool dinotrack) ──> MySQL 8
   │ 80 → 301 HTTPS                                           └──> Redis (queue, cache, lock)
   └─ Midtrans webhook (/webhooks/*: body ≤16 KB, limit_req)

Supervisor ── queue:work redis --queue=default        ×2  (notifikasi pembayaran)
           ├─ queue:work redis --queue=network        ×2  (perintah Mikrotik)
           └─ queue:work redis --queue=notifications  ×1  (WhatsApp, 1 pesan / 5 detik)

cron ── schedule:run tiap menit (jadwal di routes/console.php, termasuk billing:health tiap 15 menit)
     └─ backup-database.sh 03:30
```

Pembagian queue ada di `App\Enums\QueueName` dan atribut `#[Queue]` setiap job (audit R-9).
Job baru wajib menetapkan queue (dijaga `JobQueueTest`).

| File di repo | Tujuan di server |
|---|---|
| `deploy/nginx/dinotrack.conf` | `/etc/nginx/sites-available/dinotrack` |
| `deploy/php/dinotrack.conf` | `/etc/php/8.4/fpm/pool.d/dinotrack.conf` |
| `deploy/php/99-dinotrack.ini` | `/etc/php/8.4/fpm/conf.d/` dan `/etc/php/8.4/cli/conf.d/` |
| `deploy/supervisor/dinotrack-worker.conf` | `/etc/supervisor/conf.d/dinotrack-worker.conf` |
| `deploy/cron/dinotrack` | `/etc/cron.d/dinotrack` |
| `deploy/deploy.sh` | dijalankan dari repo |
| `deploy/backup-database.sh` | dijalankan cron dari repo |

## 1. Persiapan server (sekali)

```bash
sudo timedatectl set-timezone Asia/Jakarta          # cron backup mengikuti jam server

sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install nginx mysql-server redis-server supervisor certbot git unzip \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-redis php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl
# Ekstensi wajib menurut composer.lock: ctype, dom, fileinfo, filter, hash, iconv, json, libxml,
# mbstring, openssl, pcre, session, sockets (RouterOS API), tokenizer. Sebagian sudah bawaan php8.4-common.

# Composer 2 dan Node.js 22 (build aset Vite saat deploy)
curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install nodejs
```

**User aplikasi.** PHP-FPM, worker, cron, dan deploy berjalan sebagai satu user agar tidak ada
bentrok izin di `storage/` dan `bootstrap/cache/`:

```bash
sudo adduser --disabled-password --gecos "" dinotrack
sudo mkdir -p /var/www/dinotrack /var/backups/dinotrack /var/www/letsencrypt
sudo chown dinotrack:dinotrack /var/www/dinotrack /var/backups/dinotrack
```

`deploy.sh` perlu me-reload PHP-FPM tanpa password (`/etc/sudoers.d/dinotrack`, lewat `visudo -f`):

```
dinotrack ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm
```

**MySQL.** Database `utf8mb4`, user aplikasi, dan user backup terpisah yang hanya bisa membaca:

```sql
CREATE DATABASE dinotrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dinotrack'@'localhost' IDENTIFIED BY '<password-acak>';
GRANT ALL PRIVILEGES ON dinotrack.* TO 'dinotrack'@'localhost';
CREATE USER 'dinotrack_backup'@'localhost' IDENTIFIED BY '<password-acak-lain>';
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON dinotrack.* TO 'dinotrack_backup'@'localhost';
```

**Redis** (`/etc/redis/redis.conf`), lalu `sudo systemctl restart redis-server`:

```
bind 127.0.0.1 -::1
requirepass <password-acak>        # sama dengan REDIS_PASSWORD
maxmemory-policy noeviction        # queue tidak boleh dibuang saat memori penuh
appendonly yes                     # job yang antre selamat saat Redis/server restart
```

**Firewall.** Hanya SSH dan web yang terbuka; MySQL dan Redis hanya localhost:

```bash
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
```

Router Mikrotik dihubungi dari server lewat API (8728/8729). Batasi akses API router hanya dari IP
server, sebaiknya lewat VPN (docs/05 "Persiapan di router").

## 2. Checklist `.env` production

Salin `.env.example` menjadi `.env` di `/var/www/dinotrack` (`chmod 600`, pemilik `dinotrack`), lalu isi:

| Variabel | Nilai | Catatan |
|---|---|---|
| `APP_ENV` | `production` | menolak `--date` simulasi dan perintah destruktif (`migrate:fresh`) |
| `APP_DEBUG` | `false` | stack trace tidak pernah tampil ke publik |
| `APP_KEY` | `php artisan key:generate` **sekali** | **backup terpisah**, lihat bagian Backup |
| `APP_URL` | `https://billing.example.com` | dipakai link tagihan bertanda tangan yang dibuat di job queue |
| `APP_TIMEZONE` / `APP_LOCALE` | `Asia/Jakarta` / `id` | |
| `LOG_STACK` | `daily` | |
| `LOG_LEVEL` | `info` | |
| `LOG_DAILY_DAYS` | `14` | |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | user `dinotrack` | |
| `SESSION_DRIVER` | `database` | login tetap utuh saat Redis restart |
| `SESSION_SECURE_COOKIE` | `true` | cookie hanya lewat HTTPS |
| `QUEUE_CONNECTION` | `redis` | |
| `CACHE_STORE` | `redis` | juga lock `onOneServer`, `ShouldBeUnique`, rate limit |
| `REDIS_CLIENT` / `REDIS_PASSWORD` | `phpredis` / sesuai `requirepass` | |
| `REDIS_QUEUE_RETRY_AFTER` | biarkan kosong (150) | wajib lebih lama dari timeout job terlama 100 detik |
| `MIDTRANS_IS_PRODUCTION` | `true` | |
| `MIDTRANS_SERVER_KEY` / `MIDTRANS_CLIENT_KEY` | key production dari dashboard | |
| `WHATSAPP_DRIVER` | `fonnte` | `log` hanya untuk development |
| `FONNTE_TOKEN` | token perangkat | |
| `WHATSAPP_SECONDS_PER_MESSAGE` | `5` | jangan 0 di production |
| `MAIL_*` | SMTP sungguhan | email lupa password |

Sebagian besar baris di atas diperiksa otomatis oleh `billing:health` ("Konfigurasi production").
Setelah mengubah `.env`, jalankan `php artisan config:cache` dan `php artisan queue:restart`.

Di dashboard Midtrans production, isi *Payment Notification URL* dengan
`https://billing.example.com/webhooks/payments/midtrans`.

## 3. Nginx dan HTTPS

```bash
sudo cp deploy/nginx/dinotrack.conf /etc/nginx/sites-available/dinotrack
sudo sed -i 's/billing.example.com/<domain>/g' /etc/nginx/sites-available/dinotrack
```

Sertifikat belum ada saat pertama kali, jadi minta dulu lewat webroot dengan server HTTP sementara
(hanya blok `listen 80` yang aktif; komentari blok 443), lalu aktifkan konfigurasi lengkap:

```bash
sudo ln -s /etc/nginx/sites-available/dinotrack /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
sudo certbot certonly --webroot -w /var/www/letsencrypt -d <domain>
# kembalikan blok 443, lalu:
sudo nginx -t && sudo systemctl reload nginx
```

Perpanjangan otomatis memakai timer `certbot.timer` bawaan paket. Agar Nginx memakai sertifikat
baru, buat hook `/etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh` (`chmod +x`) berisi
`systemctl reload nginx`, lalu uji dengan `sudo certbot renew --dry-run`.

Isi konfigurasi:

- HTTP → HTTPS (301) kecuali `/.well-known/acme-challenge/`. Halaman isolir juga dialihkan:
  firewall isolir Mikrotik wajib mengizinkan port **443** ke domain aplikasi.
- `/webhooks/*` langsung ke PHP dengan `client_max_body_size 16k` dan `limit_req` 2 r/s
  (burst 20) per IP (sisa risiko audit T-1). Halaman publik **tidak** diberi `limit_req` karena
  pelanggan berbagi IP NAT (audit S-3).
- Access log tanpa referer, dan query `/isolir` (kode pelanggan + 4 digit WA) disamarkan (audit R-8).
- HSTS 1 tahun; header keamanan lain dikirim aplikasi. Aset `/build/` di-cache `immutable`.
- Tanpa `trustProxies`: Nginx menerima koneksi langsung, sehingga skema HTTPS (dari
  `fastcgi_param HTTPS`) dan IP klien sudah benar. Jika kelak memakai Cloudflare/load balancer,
  pasang `trustProxies` dengan daftar IP proxy itu, karena signed URL dan rate limit per IP
  bergantung padanya.

## 4. PHP-FPM

```bash
sudo cp deploy/php/dinotrack.conf /etc/php/8.4/fpm/pool.d/dinotrack.conf
sudo cp deploy/php/99-dinotrack.ini /etc/php/8.4/fpm/conf.d/99-dinotrack.ini
sudo cp deploy/php/99-dinotrack.ini /etc/php/8.4/cli/conf.d/99-dinotrack.ini
sudo mv /etc/php/8.4/fpm/pool.d/www.conf /etc/php/8.4/fpm/pool.d/www.conf.disabled   # pool bawaan tidak dipakai
sudo systemctl restart php8.4-fpm
```

Pool `dinotrack` berjalan sebagai user `dinotrack` (socket milik `www-data` untuk Nginx).
`zend.exception_ignore_args=On` agar argumen fungsi (password router, token) tidak ikut log (audit
R-10). `opcache.validate_timestamps=0`: kode baru hanya terbaca setelah PHP-FPM di-reload, yang
dilakukan `deploy.sh`.

## 5. Queue worker dan scheduler

```bash
sudo cp deploy/supervisor/dinotrack-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status 'dinotrack-workers:*'

sudo cp deploy/cron/dinotrack /etc/cron.d/dinotrack
sudo chown root:root /etc/cron.d/dinotrack && sudo chmod 644 /etc/cron.d/dinotrack
```

- Satu program Supervisor per queue: `default` ×2, `network` ×2, `notifications` ×1.
- `stopwaitsecs=130` lebih lama dari timeout job router (100 detik) agar restart tidak memotong
  perintah router di tengah jalan. `--max-time=3600` membuat worker diganti tiap jam (mencegah
  kebocoran memori).
- Timeout per job ditetapkan di kelas job (`$timeout`) dan dijaga `ConfigTest` agar lebih pendek
  dari `retry_after` 150 detik.
- Cron memanggil `schedule:run` tiap menit. Jadwal yang memakai `onOneServer()` dan
  `withoutOverlapping()` mengunci lewat cache Redis.
- Scheduler tidak menjalankan tugas selama mode maintenance (saat deploy); tugas harian bersifat
  catch-up sehingga tidak ada yang hilang.

## 6. Deploy pertama

Sebagai user `dinotrack`:

```bash
git clone https://github.com/<pemilik>/dinoTrack.git /var/www/dinotrack
cd /var/www/dinotrack
cp .env.example .env && chmod 600 .env         # isi sesuai checklist bagian 2
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --force      # di production hanya seeder esensial (docs/03), tanpa DemoSeeder
php artisan optimize
```

Akun admin pertama dibuat lewat tinker (password minimal 12 karakter dengan huruf besar, kecil,
angka, dan simbol, sama dengan aturan production), setelah itu pegawai lain dibuat admin dari aplikasi:

```bash
php artisan tinker --execute '$user = new App\Models\User(["name" => "Admin", "email" => "admin@<domain>", "password" => "<password-awal>"]); $user->forceFill(["email_verified_at" => now()])->save(); $user->assignRole(App\Enums\Role::Admin);'
```

Lalu pasang Nginx, PHP-FPM, Supervisor, dan cron (bagian 3–5) dan jalankan `php artisan billing:health`.
Router Mikrotik ditambahkan admin dari aplikasi, dan tes koneksinya dari sana.

## 7. Deploy rutin

```bash
sudo -iu dinotrack bash /var/www/dinotrack/deploy/deploy.sh
```

Urutan `deploy.sh`:

1. Tolak jika ada deploy lain berjalan (`flock`) atau ada perubahan lokal di server.
2. `git fetch` (sebelum maintenance agar downtime singkat).
3. `php artisan down --retry=60 --refresh=15`.
4. `git merge --ff-only origin/master` (branch lewat `DEPLOY_BRANCH`).
5. `composer install --no-dev --optimize-autoloader`.
6. `npm ci && npm run build`.
7. `php artisan optimize` (cache config, event, route, view), dibuat **sebelum** migrate agar
   migrate memakai konfigurasi versi baru.
8. `php artisan migrate --force`.
9. Reload PHP-FPM (opcache).
10. `php artisan up`, lalu `php artisan queue:restart` (worker menyelesaikan job yang berjalan,
    lalu Supervisor memulainya lagi dengan kode baru).
11. `billing:health` sebagai pemeriksaan akhir.

Jika satu langkah gagal, script berhenti dan aplikasi **tetap dalam mode maintenance** agar tidak
berjalan dengan kode dan database setengah jadi. Perbaiki lalu jalankan ulang script, atau rollback.

Downtime sekitar 1–2 menit. Selama itu webhook Midtrans dibalas 503: Midtrans mengirim ulang
notifikasi dan rekonsiliasi per jam menangkap sisanya. Halaman isolir dan link tagihan juga
menampilkan 503. Deploy di luar jam sibuk tagihan: **hindari 00:00–02:30** (generate tagihan,
overdue, isolir, pembersihan) dan sekitar **09:00** (pengingat).

**Rollback.** Migration selalu ditambah (bukan diubah), jadi biasanya cukup kembali ke commit lama:

```bash
cd /var/www/dinotrack
php artisan down
git reset --hard <commit-lama>
# lalu jalankan langkah 5–10 secara manual (composer, npm, optimize, reload, up, queue:restart)
```

Jika migration baru merusak data, pulihkan dari backup (bagian 9). `migrate:rollback` hanya untuk
migration yang `down()`-nya sudah diperiksa.

## 8. Health check dan pemantauan

**`/up`** (bawaan Laravel, tanpa session): membalas 200, atau 500 jika database atau Redis tidak bisa
dipakai (listener `DiagnosingHealth` di `AppServiceProvider`). Router tidak dicek di sini karena
lambat. Daftarkan ke pemantau uptime eksternal (misalnya UptimeRobot, interval 5 menit) agar
pemilik usaha mendapat notifikasi saat aplikasi mati.

**`php artisan billing:health`** — pemeriksaan lengkap, terjadwal tiap 15 menit:

| Pemeriksaan | Gagal (fail) | Peringatan |
|---|---|---|
| Database | `select 1` gagal | |
| Redis | `ping` gagal (dilewati jika queue/cache/session tidak memakai redis) | |
| Antrean `default`, `network` | job pending tertua > 10 menit (worker mati) | |
| Antrean `notifications` | hanya jumlah job (umur tidak bermakna karena rate limit) | |
| Notifikasi pembayaran | notifikasi valid belum diproses > 15 menit dalam 24 jam terakhir: pelanggan sudah bayar tetapi invoice belum lunas | |
| Job gagal | | ada isi `failed_jobs` (tinjau `php artisan queue:failed`, lalu `queue:retry` atau `queue:forget`) |
| Pesan WhatsApp | | pesan `queued` > 2 jam, atau pesan `failed` dalam 24 jam |
| Router aktif | | tidak terjangkau (perintah router tetap dicoba ulang queue) |
| Konfigurasi production | checklist bagian 2 tidak terpenuhi | |

Command hanya membaca: tidak mengubah `routers.last_connected_at` dan tidak menulis activity log.
Setiap gagal dicatat `Log::error`, setiap peringatan `Log::warning`, dan kode keluar gagal hanya
jika ada status gagal. Ini menutup audit S-4 dari sisi operasional. Indikator yang sama di dashboard
admin dan notifikasi WA ke admin ditunda (utang teknis).

## 9. Backup dan restore

**Database.** `deploy/backup-database.sh`, dijalankan cron tiap hari pukul 03:30:

- `mysqldump --single-transaction --quick` memakai user `dinotrack_backup`. Snapshot konsisten
  tanpa mengunci tabel, jadi aplikasi tetap berjalan.
- Kredensial di `~/.my-backup.cnf` milik `dinotrack` (`chmod 600`), bukan di argumen perintah:

  ```
  [client]
  user=dinotrack_backup
  password=<password>
  ```

- Hasil gzip (`dinotrack-YYYYmmdd-HHMMSS.sql.gz`) diuji `gzip -t`, disimpan di
  `/var/backups/dinotrack` selama 14 hari (`RETENTION_DAYS`). Log di `storage/logs/backup.log`.
- **Offsite wajib**: backup di disk yang sama ikut hilang bila VPS rusak. Pasang `rclone`
  (Google Drive, S3, VPS lain) lalu set `RCLONE_REMOTE` di baris cron, misalnya
  `RCLONE_REMOTE=offsite:dinotrack-backup /bin/bash .../backup-database.sh`. Atur retensi di
  sisi tujuan (misalnya 90 hari).

**`APP_KEY` dan `.env`.** Simpan salinan `.env` di password manager pemilik usaha, terpisah dari
backup database. Tanpa `APP_KEY` yang sama, password router (kolom terenkripsi) tidak bisa dibaca
dan semua link tagihan yang sudah terkirim ke pelanggan tidak berlaku lagi.

Redis tidak di-backup: isinya job antre dan cache yang bisa dibuat ulang (invoice dan pembayaran
ada di MySQL, rekonsiliasi dan catch-up menutup celahnya).

**Restore** (uji minimal sebulan sekali ke database lain, misalnya `dinotrack_restore_test`):

```bash
php artisan down
gunzip -c /var/backups/dinotrack/dinotrack-YYYYmmdd-HHMMSS.sql.gz | mysql -u root -p dinotrack
php artisan up && php artisan queue:restart && php artisan billing:health
```

## 10. Log

| Log | Rotasi |
|---|---|
| Laravel `storage/logs/laravel-YYYY-MM-DD.log` | `LOG_STACK=daily`, `LOG_DAILY_DAYS=14` |
| Worker `storage/logs/worker-*.log` | Supervisor (10 MB × 5) |
| Nginx `/var/log/nginx/dinotrack.*.log` | logrotate bawaan paket nginx (harian, 14 file) |
| PHP-FPM `/var/log/php8.4-fpm-dinotrack.log` | tambahkan ke logrotate bila membesar |
| Backup `storage/logs/backup.log` | kecil (dua baris per hari) |

Pantau log harian dengan `tail -f storage/logs/laravel-$(date +%F).log` atau `php artisan pail`
(hanya terpasang di development).

## 11. CI (GitHub Actions)

`.github/workflows/ci.yml` (GitHub hanya membaca `.github/` di root repo, tempat aplikasi berada)
menjalankan, di setiap push dan pull request, dengan PHP 8.4 (`mbstring`, `pdo_mysql`, `sockets`),
Node 22, dan service MySQL 8.4 (`dinotrack_testing`, sama dengan `phpunit.xml`):
`composer install` → `.env` dari `.env.example` + `key:generate` → `npm ci` →
`php artisan wayfinder:generate` (helper route TS tidak di-commit) → `composer lint` (Pint
`--test`) → `composer analyse` (PHPStan) → `composer test` (Pest) → `npm run build`.
`npm run check` tidak dijalankan karena format markdown masih utang teknis. Dependabot
(`.github/dependabot.yml`) memperbarui versi action mingguan.

Deploy tetap manual lewat `deploy.sh`; tidak ada deploy otomatis dari CI di v1.
