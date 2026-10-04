# Tahap 11 — Kesiapan Deploy

**Tujuan:** backend siap dijalankan di VPS production dengan queue,
scheduler, HTTPS, dan pemantauan dasar.

## Prompt

```
Tahap 11: kesiapan deploy. Target: VPS Ubuntu 24.04, Nginx, PHP-FPM 8.3,
MySQL 8, Redis, Supervisor, HTTPS (Let's Encrypt).

Kerjakan dan dokumentasikan di docs/10-deploy.md:
1. Checklist .env production (APP_DEBUG=false, APP_ENV=production, queue redis,
   cache redis, session secure, MIDTRANS_IS_PRODUCTION, dsb).
2. Contoh konfigurasi Nginx untuk aplikasi.
3. Konfigurasi Supervisor untuk queue worker (pisahkan queue: default,
   network, notifications) dan cron untuk schedule:run.
4. Script deploy (deploy.sh): git pull, composer install --no-dev
   --optimize-autoloader, npm ci && npm run build, migrate --force,
   config/route/view/event cache, queue:restart.
5. Endpoint health check (/up bawaan Laravel) dan command billing:health
   yang memeriksa koneksi database, redis, router, dan antrean macet.
6. Strategi backup database harian.
7. Opsional: GitHub Actions yang menjalankan lint, analyse, dan test di
   setiap push.

Jangan menjalankan perintah apa pun terhadap server sungguhan.
```

## Commit
`chore(deploy): konfigurasi dan dokumentasi deploy production`

---

**Setelah tahap ini, backend selesai.** Lanjut ke fase frontend: buat halaman
React berdasarkan `docs/08-kontrak-halaman.md`.
