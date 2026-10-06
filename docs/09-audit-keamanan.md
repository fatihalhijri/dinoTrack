# 09 — Audit Keamanan & Ketahanan

Audit Tahap 10, 2026-10-06, atas seluruh backend (commit `eb3ccb4` + perubahan Tahap 10).
Dilakukan dengan membaca kode (route, middleware, controller, Form Request, Policy, Action,
Job, Service, model, Blade publik, konfigurasi), menjalankan `composer audit` dan
`npm audit`, serta memetakan aturan `docs/04-aturan-bisnis.md` ke test.

Tingkat keparahan:

- **Kritis**: bisa dieksploitasi dari luar tanpa syarat khusus dan merusak uang/data.
- **Tinggi**: bisa dieksploitasi dari luar dan menghentikan layanan, atau merusak data dengan syarat ringan.
- **Sedang**: butuh syarat khusus (data bocor, beban tinggi, konfigurasi tertentu) atau dampaknya terbatas.
- **Rendah**: pengerasan (hardening), kebersihan, atau risiko yang sudah diterima.

Status: ✅ diperbaiki (Tahap 10, atau Tahap 11 bila disebut) · ⏳ ditunda (tahap/keputusan berikutnya) · 📝 dicatat/diterima.

## Ringkasan

| ID | Keparahan | Temuan | Status |
|---|---|---|---|
| T-1 | Tinggi | Webhook publik menyimpan payload berukuran bebas sebelum signature diverifikasi | ✅ |
| S-1 | Sedang | Signature Midtrans tidak mengikat `transaction_status`; `status_code` tidak dicocokkan | ✅ |
| S-2 | Sedang | `retry_after` queue (90 s) lebih pendek dari `$timeout` job router (100 s) | ✅ |
| S-3 | Sedang | Rate limit halaman publik per IP, padahal pelanggan berbagi IP NAT ISP | ⏳ keputusan |
| S-4 | Sedang | Kegagalan job/webhook/WA hanya terlihat di log | ✅ Tahap 11 (`billing:health`); indikator dashboard ⏳ |
| S-5 | Sedang | Jadwal pembersihan data lama di docs/02 belum dibuat | ✅ |
| R-1 | Rendah | `npm audit`: `shell-quote` (critical) lewat `concurrently` di `dependencies` | ✅ |
| R-2 | Rendah | Tanpa header keamanan (frame, referrer, sniffing) | ✅ |
| R-3 | Rendah | Shared prop `auth.user` mengirim model User utuh | ⏳ fase frontend |
| R-4 | Rendah | `customers.network_error` dan activity log memuat `host:port` router, terlihat kasir/teknisi | ✅ |
| R-5 | Rendah | 2FA tidak diwajibkan untuk admin | ⏳ fase frontend |
| R-6 | Rendah | Lock QRIS 60 s bisa habis sebelum panggilan gateway beruntun selesai | 📝 |
| R-7 | Rendah | Panggilan router sinkron (status koneksi, tes koneksi) tanpa throttle | 📝 |
| R-8 | Rendah | Cek tagihan `/isolir` bisa ditebak terdistribusi; `kode`/`hp` di query string | 📝 |
| R-9 | Rendah | Semua job di queue `default`; job WA yang ditahan rate limit bangun bersamaan | ✅ Tahap 11 |
| R-10 | Rendah | Konfigurasi production (proxy, debug, cookie, PHP) | ✅ Tahap 11 |
| R-11 | Rendah | `npm audit` (dev): `tinypool` lewat `vite-plus` 0.3.0 | 📝 |

Tidak ada temuan **Kritis**.

## Temuan dan perbaikan

### T-1 — Webhook menyimpan payload berukuran bebas (Tinggi) ✅

- **Lokasi:** `app/Http/Controllers/Webhooks/MidtransWebhookController.php`, limiter `webhooks`.
- **Masalah:** endpoint `POST /webhooks/payments/midtrans` publik dan URL-nya mudah ditebak.
  Setiap request disimpan utuh ke `payment_notifications` (termasuk signature salah) dengan
  batas 120 request/menit per IP. Dengan batas body Nginx bawaan 1 MB, satu IP bisa menulis
  ±7 GB/jam; disk penuh menghentikan database, termasuk pembayaran.
- **Perbaikan:**
  - body di atas 16 KB (`MAX_PAYLOAD_BYTES`; notifikasi asli ±1–2 KB) dibalas **413** tanpa disimpan;
  - payload dengan signature salah hanya disimpan field auditnya (`order_id`, `status_code`,
    `gross_amount`, `transaction_status`, `transaction_id`, waktu, `payment_type`,
    `fraud_status`, `signature_key`; masing-masing maks. 255 karakter);
  - baris lama dihapus terjadwal (S-5).
- **Risiko sisa:** ±2 KB × 120/menit per IP untuk payload palsu. Tahap 11: Nginx membatasi
  `/webhooks/*` dengan `client_max_body_size 16k` dan `limit_req` 2 r/s per IP (docs/10 bagian 3).
- **Test:** `MidtransWebhookTest` — "menolak body di atas batas ukuran dengan 413 tanpa
  menyimpannya", "hanya menyimpan field audit dari notifikasi dengan signature salah".

### S-1 — `transaction_status` tidak diikat signature (Sedang) ✅

- **Lokasi:** `app/Services/Payment/MidtransPaymentGateway.php` (`parseNotification`).
- **Masalah:** `signature_key = sha512(order_id + status_code + gross_amount + server_key)`.
  Pemetaan status hanya membaca `transaction_status`, sehingga payload sah berstatus
  `pending` (201) atau `expire` (407) bisa diubah menjadi `settlement` tanpa merusak signature
  lalu melunasi invoice. Syaratnya penyerang memegang payload asli (dari database, log, atau
  dashboard), karena itu Sedang.
- **Perbaikan:** status lunas (`settlement`, `capture` + `accept`) hanya diterima jika
  `status_code = "200"` (dokumentasi Midtrans: 200 = transaksi berhasil). Selain itu
  `PaymentGatewayException` → `ProcessPaymentNotificationJob` langsung gagal → `Log::error` +
  activity log `payment.notification_failed`. Keputusan P4 (tanpa `checkStatus()` di jalur
  webhook) tetap berlaku.
- **Test:** `MidtransPaymentGatewayTest` — "menolak status lunas yang tidak datang dengan
  status_code 200"; `MidtransWebhookTest` — "tidak melunasi invoice dari notifikasi pending sah
  yang statusnya diubah menjadi settlement".

### S-2 — `retry_after` lebih pendek dari timeout job (Sedang) ✅

- **Lokasi:** `config/queue.php`; `IsolateCustomerJob`, `ActivateCustomerJob`,
  `ApplyCustomerProfileJob`, `DisableCustomerSecretJob` (`$timeout = 100`).
- **Masalah:** dengan `retry_after = 90`, job router yang masih berjalan (menunggu lock 30 s +
  operasi router) dilepas kembali ke antrean dan diambil worker lain sehingga berjalan dobel.
  Dampak diredam `CustomerNetworkLock` dan cek ulang kondisi, tetapi percobaan terbuang dan log
  ganda.
- **Perbaikan:** `retry_after` bawaan `database`, `redis`, dan `beanstalkd` menjadi **150**
  (bisa diatur `DB_QUEUE_RETRY_AFTER` / `REDIS_QUEUE_RETRY_AFTER`, dicontohkan di `.env.example`).
- **Test:** `ConfigTest` — "memberi retry_after queue lebih lama dari timeout setiap job"
  (memeriksa semua kelas di `app/Jobs`, termasuk job baru kelak).

### S-3 — Rate limit per IP di halaman publik (Sedang) ⏳

- **Lokasi:** `AppServiceProvider::isolationPageLimits()`, limiter `public-invoice`.
- **Masalah:** pelanggan ISP umumnya keluar lewat NAT dengan IP publik yang sama. Semua
  perangkat terisolir berbagi kuota 120 tampilan/menit dan 20 cek/menit di `/isolir`, sehingga
  pelanggan sungguhan bisa mendapat 429 tepat saat ingin membayar.
- **Usulan:** (a) daftar IP NAT ISP di `.env` yang dikecualikan dari limit per IP (limit per
  kode pelanggan tetap berlaku), (b) menaikkan batas tampilan, atau (c) diterima.
- **Status:** menunggu keputusan pemilik usaha (bergantung topologi jaringan).

### S-4 — Kegagalan tidak terlihat admin (Sedang) ✅ Tahap 11

- **Lokasi:** `ReportService::dashboardSummary()`.
- **Masalah:** dashboard menampilkan galat router (`network_error_at`) dan pembayaran
  `needs_review`, tetapi tidak menampilkan `failed_jobs`, `payment.notification_failed`
  (pelanggan sudah membayar tetapi invoice belum lunas), pesan WhatsApp `failed`, atau
  rekonsiliasi yang gagal. Semuanya hanya ada di log file.
- **Usulan:** digabung dengan command `billing:health` di Tahap 11. Bisa juga ditambah
  indikator dashboard: pesan WA gagal 7 hari, notifikasi webhook valid yang belum diproses
  lebih dari 15 menit, dan jumlah `failed_jobs`.
- **Perbaikan (Tahap 11):** `billing:health` tiap 15 menit memeriksa notifikasi pembayaran valid
  yang belum diproses lebih dari 15 menit (gagal), `failed_jobs`, pesan WA `failed` 24 jam dan
  `queued` lebih dari 2 jam (peringatan); masalah ditulis ke log dan `/up` membalas 500 bila
  database/Redis mati (docs/10 bagian 8). Rekonsiliasi gagal tetap hanya di log.
- **Sisa:** indikator di dashboard admin dan notifikasi WA ke admin ditunda ke fase frontend.
- **Test:** `HealthCheckerTest`, `HealthCommandTest`, `HealthEndpointTest`.

### S-5 — Pembersihan data lama belum dijadwalkan (Sedang) ✅

- **Lokasi:** `routes/console.php`, `app/Models/PaymentNotification.php`.
- **Masalah:** docs/02 mencantumkan "harian: prune log lama, failed jobs lama", tetapi
  jadwalnya belum ada. `payment_notifications` dan `failed_jobs` tumbuh tanpa batas (memperparah T-1).
- **Perbaikan (retensi disetujui 2026-10-06):**
  - `PaymentNotification` memakai `MassPrunable`:
    - signature salah: dihapus setelah 30 hari;
    - valid dan sudah diproses: dihapus setelah 365 hari;
    - valid tetapi belum diproses (job gagal): **tidak pernah** dihapus otomatis;
  - `model:prune` harian 02:00 dan `queue:prune-failed --hours=720` harian 02:10, keduanya
    `withoutOverlapping(60)` + `onOneServer()`;
  - `activity_logs` dan `message_logs` **tidak** dihapus (laporan dan riwayat bergantung padanya).
- **Catatan:** entri cache tabel `cache` yang kedaluwarsa tidak dibersihkan otomatis. Production
  memakai Redis, jadi tidak berdampak; untuk server yang tetap memakai cache database, cukup
  pantau ukurannya.
- **Test:** `PaymentNotificationTest` — retensi; `BillingCommandsTest` — jadwal.

### R-1 — `shell-quote` lewat `concurrently` (Rendah) ✅

`concurrently` (hanya untuk `composer run dev`) dipindah ke `devDependencies`, dan
`overrides.shell-quote = ^1.11.0` ditambahkan karena `concurrently` 10.0.5 (versi terbaru)
mengunci `shell-quote` 1.9.0 (GHSA-pqg4-j6r4-53mv, rentang 1.8.4–1.10.0). Kini terpasang
1.12.0. `npm audit --omit=dev` bersih. `composer audit` bersih.

### R-2 — Header keamanan (Rendah) ✅

Middleware global baru `AddSecurityHeaders` (juga berlaku untuk route publik dan webhook yang
tidak memakai grup `web`):

- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: same-origin`

Header referrer penting karena halaman tagihan publik memakai signed URL tanpa masa berlaku:
URL lengkapnya tidak ikut terkirim ke Midtrans (gambar QR) atau `wa.me`. CSP belum dipasang
karena halaman publik memakai skrip inline; bisa ditambah bersama fase frontend.
**Test:** `SecurityHeadersTest`.

### R-3 — `auth.user` utuh di props Inertia (Rendah) ⏳

`HandleInertiaRequests` membagikan model `User` utuh. Kolom rahasia sudah `#[Hidden]`, tetapi
relasi `roles`/`permissions` yang termuat oleh `hasRole()` ikut terkirim. Rapikan ke bentuk
minimal saat fase frontend, karena komponen starter kit membaca beberapa field user.

### R-4 — Alamat router terlihat kasir/teknisi (Rendah) ✅

`RouterUnreachableException` kini membawa `summary()` tanpa alamat (`Router tidak bisa
dijangkau (Router Pusat).`). `HandlesRouterFailures` memakai ringkasan ini untuk
`customers.network_error` dan properti activity log; pesan lengkap dengan `host:port` hanya
masuk log aplikasi. Hasil tes koneksi untuk admin tetap menampilkan detail.
**Test:** `MikrotikNetworkControllerTest`, `NetworkJobsTest`.

### R-5 — 2FA admin tidak wajib (Rendah) ⏳

Admin mengendalikan pengaturan, router, dan pembatalan tagihan. Pertimbangkan mewajibkan 2FA
(middleware yang mengarahkan admin tanpa 2FA ke halaman keamanan) di fase frontend.

### R-6 — Lock charge QRIS bisa habis (Rendah) 📝

`CreateQrisCharge` memegang `Cache::lock` 60 s. Jika ada beberapa charge `pending` kedaluwarsa,
`checkStatus()` dipanggil untuk masing-masing (timeout 15 s, 2 percobaan), lalu charge baru
dibuat. Kasus terburuk melewati 60 s; request kedua bisa bentrok di unique
(`invoice_id`, `attempt`) dan mendapat 500. Langka karena rekonsiliasi per jam menutup charge
lama. Usulan bila terjadi: batasi satu `checkStatus()` per request atau tangkap
`UniqueConstraintViolationException` menjadi 503.

### R-7 — Panggilan router sinkron tanpa throttle (Rendah) 📝

Deferred prop `connection` di `customers/show` dan `POST routers/{router}/test` membuka koneksi
ke router di dalam request (timeout 5 + 10 s). Pengguna yang login bisa menahan worker
PHP-FPM dengan memuat ulang berkali-kali. Usulan: `throttle` per pengguna bila menjadi masalah.

### R-8 — Cek tagihan `/isolir` (Rendah) 📝

Risiko yang diterima di keputusan N9: 4 digit nomor WA dengan batas 10 cek/jam per kode bisa
ditebak secara terdistribusi ke banyak kode (peluang 1/10.000 per tebakan). Yang terbuka hanya
nama pelanggan dan tagihan terbuka beserta link bayar. `kode` dan `hp` dikirim lewat GET
sehingga tercatat di access log web server. Tahap 11: Nginx menyamarkan query `/isolir` dan tidak
mencatat referer, log dirotasi harian (docs/10 bagian 3 dan 10).

### R-9 — Antrean bersama (Rendah) ✅ Tahap 11

Semua job memakai queue `default`. Pada hari tagih, ratusan `SendWhatsAppMessage` yang ditahan
`RateLimited` dilepas ulang bersamaan setiap ±5 detik, dan job router aktivasi setelah bayar
ikut mengantre di belakangnya. Tahap 11 memisahkan queue (`default`, `network`, `notifications`)
lewat atribut `#[Queue(QueueName::...)]` di setiap job (dijaga `JobQueueTest`) dengan worker
Supervisor terpisah (docs/02 "Queue", docs/10 bagian 5).

### R-10 — Konfigurasi production (Rendah) ✅ Tahap 11

Diselesaikan di docs/10 (checklist `.env`, Nginx, PHP) dan diperiksa otomatis oleh
`billing:health` ("Konfigurasi production"):

- `APP_DEBUG=false`
- `SESSION_SECURE_COOKIE=true`
- `trustProxies` bila memakai CDN/load balancer (signed URL dan rate limit per IP bergantung
  pada skema dan IP yang benar); v1 tanpa proxy sehingga tidak dipasang
- `zend.exception_ignore_args=On`
- rotasi log
- **PHP 8.4** (prompt Tahap 11 menyebut 8.3, proyek memakai 8.4)

### R-11 — `tinypool` lewat `vite-plus` (Rendah) 📝

`npm audit` (termasuk dev) melaporkan `tinypool` ≤2.1.1 lewat `vite-plus` 0.3.0 yang dipasang
dengan versi tetap. Hanya alat build/format di mesin pengembang, tidak ikut ke production
runtime. Perbaikannya menaikkan `vite-plus` ke ≥0.3.3; ditunda agar toolchain frontend tidak
berubah di fase backend.

## Hasil per area yang diminta

### 1. Webhook

| Aspek | Hasil |
|---|---|
| Signature | `hash_equals`, Server Key kosong = selalu 403, field hilang/bukan string = 403. `status_code` kini dicocokkan untuk status lunas (S-1) |
| Idempotensi | Satu charge paling banyak satu pembayaran: lock pelanggan → invoice → charge lalu locking read `payments` (P10); teruji 4 proses paralel. `processed_at` mencegah proses ulang notifikasi yang sama |
| Pencocokan nominal | `gross_amount` vs `payment_charges.amount` vs `invoices.total`; selisih = anomali `needs_review`, invoice tidak berubah |
| Rate limit | 120/menit per IP + batas ukuran body (T-1) |
| Replay | Replay `settlement` tidak membuat pembayaran baru; replay `pending`/`expire` tidak menimpa status akhir |

### 2. Otorisasi

- Setiap route admin berada di grup `auth` + `verified` + `PermissionMiddleware` modul, lalu
  Policy lewat Form Request `authorize()` atau `Gate::authorize()`. Tidak ditemukan route atau
  aksi tanpa pengecekan. Tidak ada endpoint JSON admin di luar Inertia.
- **IDOR:** aplikasi single-tenant (K1); semua data dimiliki satu usaha dan dibatasi per
  permission, bukan per pemilik. Halaman publik memakai signed URL (tagihan) atau kode + 4
  digit WA (isolir).
- **Mass assignment:** semua model memakai `#[Fillable]` eksplisit; Action memakai `Arr::only`
  atau accessor bertipe Form Request (M12, H3). Field seperti `status` diabaikan saat
  mendaftar/mengubah pelanggan (ada test).
- Admin tidak bisa mengubah role/menonaktifkan/menghapus diri sendiri; admin aktif terakhir
  dilindungi `LastAdminGuard`.

### 3. Data sensitif

- Password router: cast `encrypted`, `#[Hidden]`, disamarkan `***` di activity log, tidak ada
  di `RouterResource`, tidak masuk pesan exception (ada test).
- User: password/2FA/remember token `#[Hidden]`; `UserResource` tanpa field rahasia.
- `payment_charges.raw_response` tidak dikirim ke frontend; `PaymentChargeResource` tanpa respons mentah.
- Token Fonnte dan Server Key Midtrans tidak masuk pesan galat; kredensial hanya lewat `.env` → `config/services.php`.
- Alamat router kini tidak tampil ke kasir/teknisi (R-4); `auth.user` masih utuh (R-3).
- Raw SQL (`selectRaw`, `DB::raw`) semuanya memakai binding atau literal. Blade publik memakai
  `{{ }}` dan `@json` (ter-escape; ada test escaping). Ekspor CSV menetralkan formula.

### 4. Konsistensi data

- Pembayaran: `MarkInvoicePaid` dan `ProcessGatewayNotification` dalam transaksi dengan urutan
  lock M11; job lanjutan `afterCommit`.
- Nomor invoice dan kode pelanggan: `SequenceGenerator` (`INSERT … ON DUPLICATE KEY UPDATE` +
  `lockForUpdate`, ikut transaksi pemanggil), teruji 4 proses paralel tanpa celah atau duplikat.
- Tagihan ganda dicegah unique (`subscription_id`, `billed_period_start`); satu subscription
  aktif dijaga unique (`customer_id`, `is_current`).

### 5. Kegagalan integrasi

| Integrasi | Konsistensi | Admin tahu? |
|---|---|---|
| Router mati | Router dulu, status DB hanya berubah jika berhasil; 4 percobaan; lock per pelanggan | `network_error_at` di dashboard + activity log |
| Midtrans timeout (charge) | Charge ditandai `failed`, percobaan berikutnya order_id baru; pelanggan mendapat 503 ramah | Log warning |
| Midtrans webhook terlewat | Rekonsiliasi per jam (5 menit–7 hari) | Log error bila gagal (S-4) |
| Webhook gagal diproses | Transaksi rollback, job dicoba 5x, charge tetap `pending` sehingga rekonsiliasi mencoba lagi | Activity log `payment.notification_failed` + log (S-4) |
| WhatsApp gagal | `message_logs` `failed`, tidak memengaruhi tagihan/pembayaran | Riwayat pesan pelanggan + activity log (S-4) |

### 6. Signed URL halaman publik

Tanpa masa berlaku (keputusan A4/W1); HMAC SHA-256 dengan `APP_KEY` atas URL absolut. ID invoice
berurutan, tetapi URL tanpa/salah signature ditolak 403, termasuk endpoint `status` dan `qris`
(ada test). Membuka halaman tidak membuat charge. Link yang bocor memberi akses baca rincian
tagihan + nama/kode pelanggan dan kemampuan membayar, tanpa nomor HP/alamat. Referrer kini
dibatasi (R-2). Mengganti `APP_KEY` membatalkan semua link.

### 7. Scheduler dan queue

- Semua jadwal `withoutOverlapping(60)` + `onOneServer()` (ada test), termasuk prune baru.
- Job ganda: job router `ShouldBeUnique` per pelanggan + mode/alasan (N3);
  `retry_after` > `$timeout` (S-2).
- `failed_jobs` kini dibersihkan 30 hari (S-5); visibilitasnya di S-4.

### 8. Dependensi

- `composer audit`: tidak ada advisory.
- `npm audit --omit=dev`: bersih setelah R-1.
- `npm audit` (termasuk dev): `tinypool` via `vite-plus` (R-11).

### 9. Cakupan test aturan bisnis

Pemetaan `docs/04` ke test (nama file tanpa `tests/Feature/`). ✅ ada test, ◐ sebagian, ❌ belum.

| Aturan docs/04 | Test | Status |
|---|---|---|
| Batas pengaturan `due_days`/`grace_days`/`reminder_days_before` | `Actions/Settings/SettingsActionsTest`, `Http/Settings/BillingSettingsControllerTest` | ✅ |
| `billing_day` 29–31 dibulatkan ke 28 | `Actions/Customers/CreateCustomerTest` | ✅ |
| Terbit tepat `billing_day`, periode, catch-up, aman dijalankan ulang | `GenerateMonthlyInvoicesTest`, `GenerateInvoiceForSubscriptionTest` | ✅ |
| Subscription tanpa invoice hanya ditagih periode berjalan | `GenerateMonthlyInvoicesTest` | ✅ |
| `due_at = issued_at + due_days`, nomor `INV/YYYY/MM/NNNNN` | `GenerateInvoiceForSubscriptionTest`, `Support/InvoiceNumberGeneratorTest` | ✅ |
| `terminated`/`pending` tidak ditagih | `GenerateMonthlyInvoicesTest` | ✅ |
| Aktivasi: tagihan pertama, tanggal pasang mundur, router nonaktif ditolak | `ActivateNewCustomerTest` | ✅ |
| Prorata, pembulatan Rp100, batas harga sebulan, prorata mati | `Unit/ProrataCalculatorTest`, `ActivateNewCustomerTest` | ✅ |
| Status invoice, overdue sehari setelah jatuh tempo | `MarkOverdueInvoicesTest` | ✅ |
| Pembatalan beralasan, terbit ulang, paket koreksi | `CancelInvoiceTest`, `ReissueInvoiceTest` | ✅ |
| Pembatalan memicu aktivasi isolir otomatis | `CancelInvoiceTest` | ✅ |
| Nominal pas, satu pembayaran = satu invoice | `MarkInvoicePaidTest`, `RecordManualPaymentTest` | ✅ |
| Link tagihan tanpa kedaluwarsa, status lunas/dibatalkan, rate limit QRIS | `Public/PublicInvoicePageTest` | ✅ |
| Rate limit tampilan halaman tagihan (120/menit per IP) | — (hanya keberadaan middleware di `Http/PublicRoutesTest`) | ◐ |
| Charge dipakai ulang, charge habis dicek ke gateway, charge baru setelah expired/failed | `CreateQrisChargeTest` | ✅ |
| Invoice pelanggan `terminated` tetap bisa dibayar QRIS | — | ❌ |
| Pembayaran manual: metode, tanggal mundur, batas tanggal | `RecordManualPaymentTest`, `Requests/PaymentRequestsTest` | ✅ |
| Anomali (lunas/batal/nominal), charge expired tetap diterapkan normal, tinjauan | `ProcessGatewayNotificationTest`, `ResolvePaymentTest` | ✅ |
| Refund/chargeback hanya dicatat | `ProcessGatewayNotificationTest` | ✅ |
| Status pelanggan, perubahan router/username/`billing_day` hanya saat `pending` | `UpdateCustomerTest` | ✅ |
| Isolir otomatis lewat toleransi, toleransi 0, isolir otomatis dimatikan | `IsolateOverdueCustomersTest`, `IsolateCustomerTest` | ✅ |
| Aktivasi otomatis hanya isolir `overdue`, tunggakan lain dalam toleransi tidak menghalangi | `MarkInvoicePaidTest`, `ActivateCustomerTest` | ✅ |
| Pembayaran invoice sisa pelanggan berhenti tidak mengaktifkan layanan | `MarkInvoicePaidTest` (dataset "berhenti") | ✅ |
| Isolir/buka isolir manual beralasan, isolir manual atas otomatis | `ManualIsolationTest` | ✅ |
| Cek ulang syarat saat job; balapan bayar vs isolir | `IsolateCustomerTest`, `ActivateCustomerTest` | ✅ |
| Router gagal → status tidak berubah, tanda `network_error_at` | `IsolateCustomerTest`, `NetworkJobsTest` | ✅ |
| Halaman `/isolir`: tanpa session, kode + 4 digit, pesan seragam, limit per kode | `Public/IsolationPageTest` | ✅ |
| Limit `/isolir` per IP (20 cek/menit, 120 tampilan/menit) | — | ❌ |
| Ganti paket periode berikutnya, `pending` langsung, isolated tidak menimpa profil | `ChangeCustomerPackageTest`, `GenerateInvoiceForSubscriptionTest`, `ActivateCustomerTest` | ✅ |
| Berhenti: status, subscription, secret dinonaktifkan via job, cek ulang saat job | `TerminateCustomerTest`, `Jobs/DisableCustomerSecretJobTest` | ✅ |
| Job nonaktif secret tidak terkirim bila transaksi berhenti di-rollback | — (`Queue::fake()` mengabaikan `afterCommit`; utang teknis) | ❌ |
| Reaktivasi `terminated → pending` | `ReactivateCustomerTest` | ✅ |
| Soft delete hanya `pending` tanpa invoice; username boleh dipakai lagi | `DeleteCustomerTest`, `Requests/CustomerRequestsTest` | ✅ |
| Hapus paket/router yang masih dirujuk ditolak | `PackageActionsTest`, `RouterActionsTest` | ✅ |
| Laporan: pendapatan basis kas, anomali bukan pendapatan, tunggakan, pergerakan, cache dashboard | `Services/Reports/ReportServiceTest` | ✅ |
| Ekspor CSV (BOM, `;`, formula) | `Services/Reports/ReportCsvExporterTest`, `Http/ReportExportControllerTest` | ✅ |
| Pesan WA: tanpa ganda, `failed` tidak menghalangi, template nonaktif | `NotifyCustomerTest` | ✅ |
| Pengingat H-N dan hari jatuh tempo, N = 0, pelanggan berhenti | `SendInvoiceRemindersTest` | ✅ |
| Pengingat yang terlewat tidak dikirim belakangan | — (perilaku tersirat dari query `due_at` tepat) | ◐ |
| Pesan isolir hanya `overdue`, invoice tertua, dilewati bila sudah lunas | `Jobs/NotificationJobsTest` | ✅ |
| Isolir ulang tunggakan sama tidak mengirim pesan lagi | `NotifyCustomerTest` (mekanisme umum) | ◐ |
| Rate limit 1 pesan / 5 detik, penolakan provider tanpa retry | `Jobs/SendWhatsAppMessageTest` | ✅ |
| Kirim ulang tagihan: `sent` tidak menghalangi, `queued` menolak, 6/menit | `ResendInvoiceTest`, `Http/InvoiceControllerTest` | ✅ |
| Akun nonaktif keluar dari session (termasuk passkey) | `Auth/DeactivatedUserTest` (passkey: utang teknis) | ◐ |

Celah ❌/◐ di atas berisiko rendah; usulan test tambahan dicatat di utang teknis `PROGRESS.md`.
