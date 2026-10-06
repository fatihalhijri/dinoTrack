# 02 — Arsitektur

## Keputusan utama

| Keputusan | Pilihan | Alasan |
|---|---|---|
| Bentuk aplikasi | Monolit Laravel + Inertia + React | Satu proyek, satu deploy, cepat dikembangkan |
| Tenancy v1 | **Single-tenant** (satu ISP per instalasi) — **keputusan final 2026-10-04**, tanpa `tenant_id` | Lebih sederhana. Menjadi SaaS kelak berarti perubahan skema besar dan harus diputuskan ulang |
| Logika bisnis | Action classes | Mudah diuji, bisa dipanggil dari controller, job, command |
| Integrasi eksternal | Interface + implementasi + fake | Bisa ganti penyedia, test tidak menyentuh layanan asli |
| Proses async | Laravel Queue | Webhook cepat, retry otomatis saat router/WA gagal |
| Uang | Integer rupiah | Hindari error pembulatan |
| Status | PHP Enum (backed string) | Type-safe, satu sumber nilai |

## Struktur folder

```
app/
├── Actions/                 Satu kelas = satu aksi bisnis, method handle()
│   ├── Packages/            CreatePackage, UpdatePackage, ActivatePackage, DeactivatePackage, DeletePackage
│   ├── Routers/             CreateRouter, UpdateRouter, DeleteRouter, TestRouterConnection
│   ├── Customers/           CreateCustomer, UpdateCustomer, ChangeCustomerPackage, TerminateCustomer,
│   │                        ReactivateCustomer, DeleteCustomer, ActivateNewCustomer
│   ├── Invoices/            GenerateInvoiceForSubscription, GenerateMonthlyInvoices,
│   │                        IssueInvoice, MarkOverdueInvoices, CancelInvoice, ReissueInvoice,
│   │                        ResendInvoice
│   ├── Payments/            CreateQrisCharge, MarkInvoicePaid, RecordManualPayment,
│   │                        ProcessGatewayNotification, ReconcilePendingCharges, ResolvePayment
│   ├── Network/             IsolateCustomer, ActivateCustomer, ApplyCustomerProfile,
│   │                        IsolateOverdueCustomers, IsolateCustomerManually, ActivateCustomerManually
│   ├── Notifications/       NotifyCustomer, SendInvoiceReminders
│   ├── Users/               CreateUser, UpdateUser, DeactivateUser, ReactivateUser, DeleteUser
│   └── Settings/            UpdateBusinessProfile, UpdateBillingSettings, UpdateMessageTemplate
├── Contracts/               Interface integrasi
│   ├── PaymentGateway.php
│   ├── NetworkController.php
│   └── MessageSender.php
├── Services/                Implementasi integrasi + query service internal
│   ├── Payment/MidtransPaymentGateway.php
│   ├── Network/MikrotikNetworkController.php   (+ RouterOsClientFactory)
│   ├── Messaging/FonnteMessageSender.php       (+ LogMessageSender untuk development)
│   └── Reports/             ReportService, ReportCsvExporter (hanya membaca database,
│                            tanpa interface karena tidak perlu di-fake)
├── Enums/                   CustomerStatus, InvoiceStatus, PaymentMethod, ...
├── Jobs/                    Pembungkus queue untuk Actions yang lambat
├── Console/Commands/        Perintah terjadwal
├── Http/
│   ├── Controllers/         Tipis: validasi → Action → response
│   ├── Controllers/Webhooks/
│   ├── Middleware/          EnsureUserIsActive (keluarkan user nonaktif), HandleInertiaRequests
│   ├── Requests/            Form Request validasi + accessor bertipe untuk Action
│   └── Resources/           API Resource untuk props Inertia (tanpa field sensitif)
├── Models/
├── Policies/
├── Support/                 Helper (Money, BillingPeriod, ProrataCalculator, InvoiceNumberGenerator,
│                            SequenceGenerator, SettingsRepository, ActivityLogger, PhoneNumber,
│                            IsolationRules, CustomerNetworkLock, MessageTemplateRenderer,
│                            InvoicePaymentLink, LastAdminGuard, SearchTerm)
└── Data/                    DTO sederhana (readonly class) bila perlu; Data/Reports untuk laporan
tests/
├── Feature/                 Alur end-to-end (HTTP, job, scheduler)
├── Unit/                    Logika murni (prorata, nomor invoice)
└── Fakes/                   FakePaymentGateway, FakeNetworkController, FakeMessageSender
```

## Alur utama

### Tagihan bulanan
```
Scheduler (harian 00:10)
  → GenerateMonthlyInvoices
      → buat invoice untuk setiap periode yang sudah dimulai dan belum punya invoice
        (catch-up, aman dijalankan ulang)
      → dispatch SendInvoiceNotification (queue)
```

### Aktivasi pelanggan baru
```
Admin/kasir menandai "terpasang" → ActivateNewCustomer (transaksi)
  → status = active, installed_at diisi
  → NetworkController: aktifkan secret PPPoE (via job)
  → buat invoice pertama (prorata bila berlaku) + dispatch SendInvoiceNotification
```

### Pembayaran QRIS
```
Pelanggan buka link tagihan → CreateQrisCharge (cache lock per invoice)
  → pakai ulang charge pending yang masih berlaku, atau buat charge baru (PaymentGateway)
Pelanggan bayar → Payment gateway → POST /webhooks/payments/midtrans (routes/webhooks.php, tanpa session)
  → simpan payload ke payment_notifications
  → verifikasi signature (salah → 403)
  → dispatch ProcessPaymentNotificationJob → respons 200 cepat
      → ProcessGatewayNotification (idempotent, dalam transaksi, lock pelanggan → invoice → charge)
          → normal: MarkInvoicePaid
              → invoice = paid, simpan payment, log payment.received
              → dispatch SendPaymentConfirmationJob (afterCommit)
              → jika isolated + isolation_reason = overdue + tanpa tunggakan lewat toleransi
                → dispatch ActivateCustomerJob (afterCommit)
          → anomali: simpan payment dengan review_status = needs_review, invoice tidak berubah
Scheduler (tiap jam) → ReconcilePendingCharges → checkStatus() → ProcessGatewayNotification
```

### Pembayaran manual
```
Kasir → RecordManualPayment (cash/transfer, nominal pas, tanggal bayar boleh mundur)
  → MarkInvoicePaid (jalur yang sama dengan QRIS)
```

### Isolir otomatis
```
Scheduler (harian 01:00) → MarkOverdueInvoices
Scheduler (harian 01:15) → IsolateOverdueCustomers (dilewati jika billing.auto_isolate = false)
  → pelanggan active dengan invoice lewat toleransi (IsolationRules)
  → dispatch IsolateCustomerJob per pelanggan (unik per pelanggan + alasan)
      → IsolateCustomer, di bawah CustomerNetworkLock
          → cek ulang syarat (bisa sudah bayar) → dilewati tanpa menyentuh router
          → NetworkController::isolate()  (ganti profil PPP + kick sesi)
          → transaksi: lock pelanggan, status = isolated, log customer.isolated
          → dispatch SendIsolationNotificationJob (afterCommit)
          → jika ternyata sudah lunas selama router dipanggil → dispatch ActivateCustomerJob
```

### Perintah router
```
Isolir / buka isolir / pasang profil / nonaktif secret
  → job (4 percobaan, backoff 30s/2m/10m; DisableCustomerSecretJob 5 percobaan)
  → CustomerNetworkLock (cache lock per pelanggan, reentrant dalam satu proses)
  → router dulu, status database hanya berubah jika router berhasil
  → RouterUnreachableException: dicoba ulang
    SecretNotFoundException / RouterCommandException: langsung gagal
  → failed(): Log::error + activity log + customers.network_error_at (tanda admin),
    dikosongkan saat perintah router berikutnya berhasil
```

Pemicu `ApplyCustomerProfileJob` (pelanggan `active` saja): aktivasi pelanggan
baru, ganti paket yang berlaku, koreksi paket saat terbit ulang. Pemicu
`ActivateCustomerJob`: pembayaran, pembatalan invoice, isolir yang balapan
dengan pembayaran, dan admin (`ActivateCustomerManually`).

### Notifikasi WhatsApp
```
Pemicu (afterCommit): SendInvoiceNotificationJob (IssueInvoice), SendPaymentConfirmationJob
(MarkInvoicePaid), SendIsolationNotificationJob (IsolateCustomer, isolir overdue saja)
Scheduler (harian 09:00) → billing:send-reminders → SendInvoiceReminders (H-N dan hari jatuh tempo)
  → NotifyCustomer (transaksi, lock pelanggan)
      → sudah ada message_logs queued/sent untuk invoice + template → dilewati
      → MessageTemplateRenderer (template nonaktif → dilewati), link bayar = InvoicePaymentLink
      → message_logs status queued → dispatch SendWhatsAppMessage (afterCommit)
          → RateLimited('whatsapp'): 1 pesan / 5 detik untuk seluruh aplikasi
          → MessageSender::send() → sent (provider_message_id, sent_at, log message.sent)
            ditolak provider → failed tanpa retry; MessageSendException → retry, failed() → failed
```

### Halaman tagihan publik
```
routes/public.php (tanpa session/cookie/CSRF), middleware signed + SubstituteBindings
GET  /tagihan/{invoice}         → Blade rincian + tombol bayar (tidak membuat charge)
POST /tagihan/{invoice}/qris    → CreateQrisCharge → JSON qr_url / 422 lunas-batal / 503 gateway
GET  /tagihan/{invoice}/status  → JSON status invoice + charge terakhir (polling 5 detik)
```

### Laporan & dashboard
```
ReportService (definisi angka: docs/04 "Laporan")
  revenueByMonth(year)          → 1 query GROUP BY bulan + metode (pembayaran normal, menurut paid_at)
  outstandingAging(today)       → 1 query GROUP BY umur (hari), digabung ke OutstandingAgeBucket
  outstandingInvoicesQuery()    → Builder (join customers, kolom age_days) untuk paginate/ekspor
  customerMovement(from, to)    → activity_logs customer.activated / terminated / isolated
                                  (index action + created_at)
  dashboardSummary()            → Cache 5 menit; dihapus setelah commit oleh Payment/Invoice::saved
ReportCsvExporter → ditulis ke stream per chunk lazyById(1000), UTF-8 BOM, pemisah `;`,
  sel berawalan = + - @ diberi awalan ' ; download() membungkus jadi StreamedResponse
```

### Lapisan HTTP admin (Tahap 09)
```
routes/web.php, grup auth + verified (+ EnsureUserIsActive di grup web)
  → PermissionMiddleware::using(<permission dasar modul>)    (packages.view, customers.view, ...)
  → Form Request: authorize() lewat Policy + validasi + accessor bertipe (input form selalu string)
    atau Gate::authorize() untuk aksi tanpa input
  → Action (aturan bisnis dan status data; pelanggaran = ValidationException → errors Inertia)
  → Inertia::render('<modul>/<halaman>', props dari API Resource) atau redirect + flash.toast
```
Daftar halaman dan props: `docs/08-kontrak-halaman.md`. `JsonResource::withoutWrapping()`
(hasil paginate tetap `data`/`links`/`meta`). Status koneksi pelanggan memanggil router lewat
deferred prop agar halaman tidak menunggu router.

## Binding interface

Di `AppServiceProvider` (atau provider khusus):

```php
$this->app->bind(PaymentGateway::class, MidtransPaymentGateway::class);
$this->app->bind(NetworkController::class, MikrotikNetworkController::class);
// MessageSender dipilih dari config('services.whatsapp.driver'): `fonnte` atau `log`.
$this->app->bind(MessageSender::class, fn () => match (config('services.whatsapp.driver')) { ... });
```

Di test: `$this->app->instance(PaymentGateway::class, new FakePaymentGateway);`
(helper `fakeGateway()`). `Tests\TestCase` memasang `FakeNetworkController` dan
`FakeMessageSender` untuk setiap test agar job router dan notifikasi yang ikut
berjalan di queue `sync` tidak pernah menghubungi layanan sungguhan; test yang
memeriksa panggilan memakai helper `fakeNetwork()` / `fakeMessages()`. Test
mematikan rate limit WhatsApp (`WHATSAPP_SECONDS_PER_MESSAGE=0` di
`phpunit.xml`) karena queue `sync` membuang job yang ditahan. `Tests\TestCase` juga
memanggil `withoutVite()`, dan `inertia.testing.ensure_pages_exist` dimatikan selama fase
backend (halaman React belum ada; nyalakan lagi di fase frontend).

## Jadwal (routes/console.php)

| Waktu | Tugas |
|---|---|
| 00:10 harian | Generate tagihan |
| 01:00 harian | Tandai overdue |
| 01:15 harian | Isolir otomatis |
| 09:00 harian | Pengingat H-3 dan hari jatuh tempo |
| tiap jam | Rekonsiliasi pembayaran pending ke gateway |
| harian | Prune log lama, failed jobs lama |

Semua jadwal memakai `->withoutOverlapping()` dan `->onOneServer()`.
