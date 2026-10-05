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
│   ├── Packages/            CreatePackage, UpdatePackage, DeactivatePackage, DeletePackage
│   ├── Routers/             CreateRouter, UpdateRouter, DeleteRouter, TestRouterConnection
│   ├── Customers/           CreateCustomer, UpdateCustomer, ChangeCustomerPackage, TerminateCustomer,
│   │                        ReactivateCustomer, DeleteCustomer, ActivateNewCustomer
│   ├── Invoices/            GenerateInvoiceForSubscription, GenerateMonthlyInvoices,
│   │                        IssueInvoice, MarkOverdueInvoices, CancelInvoice, ReissueInvoice
│   ├── Payments/            RecordManualPayment, ProcessGatewayNotification
│   └── Network/             IsolateCustomer, ActivateCustomer
├── Contracts/               Interface integrasi
│   ├── PaymentGateway.php
│   ├── NetworkController.php
│   └── MessageSender.php
├── Services/                Implementasi integrasi
│   ├── Payment/MidtransPaymentGateway.php
│   ├── Network/MikrotikNetworkController.php
│   └── Messaging/FonnteMessageSender.php
├── Enums/                   CustomerStatus, InvoiceStatus, PaymentMethod, ...
├── Jobs/                    Pembungkus queue untuk Actions yang lambat
├── Console/Commands/        Perintah terjadwal
├── Http/
│   ├── Controllers/         Tipis: validasi → Action → response
│   ├── Controllers/Webhooks/
│   └── Requests/            Form Request validasi
├── Models/
├── Policies/
├── Support/                 Helper (Money, BillingPeriod, ProrataCalculator, InvoiceNumberGenerator,
│                            SequenceGenerator, SettingsRepository, ActivityLogger, PhoneNumber)
└── Data/                    DTO sederhana (readonly class) bila perlu
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
Pelanggan buka link tagihan → CreateQrisCharge (PaymentGateway)
Pelanggan bayar → Payment gateway → POST /webhooks/payments/midtrans
  → verifikasi signature
  → ProcessGatewayNotification (idempotent, dalam transaksi)
      → invoice = paid, simpan payment
      → jika pelanggan isolated dan isolation_reason = overdue → dispatch ActivateCustomerJob
      → pembayaran anomali: simpan payment dengan review_status = needs_review, invoice tidak berubah
      → dispatch SendPaymentConfirmation (hanya pembayaran normal)
  → respons 200 cepat
```

### Isolir otomatis
```
Scheduler (harian 01:00)
  → MarkOverdueInvoices
  → cari pelanggan dengan tagihan lewat masa toleransi
  → dispatch IsolateCustomerJob per pelanggan
      → NetworkController::isolate()  (ganti profil PPP + kick sesi)
      → status = isolated, catat log, kirim WA pemberitahuan
```

## Binding interface

Di `AppServiceProvider` (atau provider khusus):

```php
$this->app->bind(PaymentGateway::class, MidtransPaymentGateway::class);
$this->app->bind(NetworkController::class, MikrotikNetworkController::class);
$this->app->bind(MessageSender::class, FonnteMessageSender::class);
```

Di test: `$this->app->instance(PaymentGateway::class, new FakePaymentGateway);`

## Jadwal (routes/console.php)

| Waktu | Tugas |
|---|---|
| 00:10 harian | Generate tagihan |
| 01:00 harian | Tandai overdue + isolir |
| 09:00 harian | Pengingat H-3 dan hari jatuh tempo |
| tiap jam | Rekonsiliasi pembayaran pending ke gateway |
| harian | Prune log lama, failed jobs lama |

Semua jadwal memakai `->withoutOverlapping()` dan `->onOneServer()`.
