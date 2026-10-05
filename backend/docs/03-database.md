# 03 — Rancangan Database

Dokumen ini sesuai dengan migration Tahap 01. Claude boleh mengusulkan
perbaikan, tetapi perubahan harus disetujui dan dokumen ini diperbarui.

Konvensi:
- Semua tabel punya `id` (bigint) dan `timestamps`.
- Uang = `unsignedBigInteger` dalam rupiah.
- Status = string yang dipetakan ke Enum di `app/Enums`.
- Foreign key bisnis memakai `restrictOnDelete` (data uang dan riwayat tidak
  pernah ikut terhapus). Pengecualian: `invoice_items` ikut terhapus bersama
  invoice-nya. FK ke `users` juga `restrict` agar jejak audit utuh.
- Kolom polimorfik menyimpan alias morph (`customer`, `invoice`, ...), bukan
  nama kelas (`Relation::enforceMorphMap` di `AppServiceProvider`).
- Kolom `date` diserialisasi sebagai `Y-m-d` (tanpa jam/zona waktu).

## Relasi

```
users ─┬─< activity_logs >── (polymorphic subject, nullable)
       └─< payments (received_by)

routers ──< customers
               │
               ├──< subscriptions >── packages (package_id, next_package_id)
               │        └──< invoices
               ├──< invoices ──< invoice_items
               │        ├──< payments
               │        ├──< payment_charges (QRIS) ──< payments
               │        └──< message_logs
               └──< message_logs

settings (key-value)
message_templates
sequences (penghitung berurutan)
payment_notifications (log webhook mentah, tanpa FK)
```

Paket pelanggan **tidak** disimpan di `customers`; selalu diambil dari
subscription aktif (`Customer::activeSubscription()`).

## Tabel

### users
Bawaan Laravel + role lewat spatie/laravel-permission (`admin`, `kasir`, `teknisi`).

### packages
| Kolom | Tipe | Catatan |
|---|---|---|
| name | string | "Home 20 Mbps" |
| speed_label | string | "20 Mbps" |
| price | unsignedBigInteger | rupiah per bulan |
| mikrotik_profile | string | nama PPP profile di router |
| is_active | boolean | default true |
| description | text nullable | |

### routers
| Kolom | Tipe | Catatan |
|---|---|---|
| name | string | |
| host | string | IP / hostname |
| port | unsignedSmallInteger | default 8728 |
| username | string | |
| password | text | **cast `encrypted`**, disembunyikan dari serialisasi |
| use_ssl | boolean | default false |
| isolation_profile | string | default "ISOLIR" |
| is_active | boolean | default true |
| last_connected_at | timestamp nullable | |

### customers
| Kolom | Tipe | Catatan |
|---|---|---|
| code | string unique | contoh `PLG-000123` |
| name | string | |
| phone | string | format 62xxxxxxxxxx |
| address | text | |
| odp | string nullable | |
| latitude, longitude | decimal(10,7) nullable | |
| router_id | foreignId | |
| pppoe_username | string | unique per router |
| status | string | `pending`, `active`, `isolated`, `terminated` (`CustomerStatus`) |
| installed_at | date nullable | |
| isolated_at | timestamp nullable | |
| isolation_reason | string nullable | `overdue` (otomatis) atau `manual` (`IsolationReason`); null jika tidak diisolir. Hanya isolir `overdue` yang dibuka otomatis saat lunas |
| terminated_at | timestamp nullable | |
| notes | text nullable | |
| softDeletes | | hanya untuk salah input; pelanggan yang sudah punya invoice tidak boleh dihapus (gunakan `terminated`) |

Index: `status`, unique (`router_id`, `pppoe_username`).

### subscriptions
| Kolom | Tipe | Catatan |
|---|---|---|
| customer_id | foreignId | |
| package_id | foreignId | |
| price | unsignedBigInteger | harga dikunci saat berlangganan |
| billing_day | unsignedTinyInteger | 1–28; diisi per langganan, bebas dari tanggal pasang (lihat `docs/04-aturan-bisnis.md`) |
| starts_at | date nullable | null selama pelanggan `pending`; diisi tanggal pasang saat aktivasi |
| ends_at | date nullable | null = subscription aktif |
| next_package_id | foreignId nullable → packages | ganti paket periode berikutnya |
| is_current | boolean nullable, **generated** | `IF(ends_at IS NULL, 1, NULL)`; jangan diisi aplikasi |

Unique: (`customer_id`, `is_current`) — menjamin di level database bahwa satu
pelanggan hanya punya satu subscription aktif (NULL boleh berulang untuk
riwayat). Pelanggan `terminated` yang diaktifkan kembali mendapat subscription
baru; subscription lama tetap sebagai riwayat.

### invoices
| Kolom | Tipe | Catatan |
|---|---|---|
| number | string unique | `INV/2026/10/00001` (5 digit, urutan di-reset tiap bulan) |
| customer_id | foreignId | sama dengan `subscription.customer_id` (denormalisasi untuk query) |
| subscription_id | foreignId | |
| period_start, period_end | date | |
| billed_period_start | date nullable, **generated** | `IF(status <> 'cancelled', period_start, NULL)`; jangan diisi aplikasi (migration `2026_10_05_173334`) |
| issued_at | date | |
| due_at | date | |
| subtotal | unsignedBigInteger | |
| discount | unsignedBigInteger | default 0; selalu 0 di v1 (belum ada fitur diskon) |
| penalty | unsignedBigInteger | default 0; selalu 0 di v1 (denda tidak dipakai) |
| total | unsignedBigInteger | |
| status | string | `unpaid`, `paid`, `overdue`, `cancelled` (`InvoiceStatus`) |
| paid_at | timestamp nullable | |
| cancelled_at | timestamp nullable | |
| cancelled_reason | string nullable | |

Unique: (`subscription_id`, `billed_period_start`) — mencegah tagihan ganda
untuk invoice yang tidak dibatalkan, tetapi mengizinkan periode yang
invoice-nya `cancelled` diterbitkan ulang (sebelumnya unique
(`subscription_id`, `period_start`), kini index biasa).
Index: (`status`, `due_at`) — untuk query overdue/isolir/pengingat dan juga
query berdasarkan `status` saja.

### invoice_items
| Kolom | Tipe |
|---|---|
| invoice_id | foreignId (cascade on delete) |
| description | string |
| quantity | unsignedInteger |
| unit_price | unsignedBigInteger |
| amount | unsignedBigInteger |

### payment_charges
Permintaan QRIS ke gateway (satu invoice bisa punya beberapa percobaan).

| Kolom | Tipe | Catatan |
|---|---|---|
| invoice_id | foreignId | |
| attempt | unsignedSmallInteger | urutan percobaan per invoice, dipakai di `order_id` |
| gateway | string | `midtrans` |
| order_id | string unique | `{nomor_invoice_tanpa_slash}-{attempt}`, contoh `INV20261000001-1` |
| amount | unsignedBigInteger | |
| qr_string | text nullable | |
| qr_url | string nullable | |
| status | string | `pending`, `settled`, `expired`, `failed` (`PaymentChargeStatus`) |
| expires_at | timestamp nullable | |
| raw_response | json nullable | |

Unique: (`invoice_id`, `attempt`). Index: `status` (rekonsiliasi per jam).

### payments
| Kolom | Tipe | Catatan |
|---|---|---|
| invoice_id | foreignId | |
| payment_charge_id | foreignId nullable | |
| method | string | `qris`, `cash`, `transfer` (`PaymentMethod`) |
| amount | unsignedBigInteger | |
| paid_at | timestamp | index (laporan) |
| reference | string nullable unique | ID transaksi gateway; banyak NULL diperbolehkan |
| received_by | foreignId nullable → users | untuk pembayaran manual |
| notes | text nullable | |
| review_status | string | `none` (default), `needs_review`, `resolved` (`PaymentReviewStatus`); index |
| review_note | text nullable | catatan tinjauan admin |

Satu pembayaran = satu invoice (`invoice_id` wajib dan tunggal), nominal harus
sama dengan total invoice. `invoice_id` sengaja **tidak** unique: pembayaran
anomali (`needs_review`) bisa menambah baris lain untuk invoice yang sama.

### payment_notifications
Log mentah setiap webhook yang masuk (untuk audit dan idempotensi).

| Kolom | Tipe |
|---|---|
| gateway | string |
| order_id | string |
| transaction_status | string |
| payload | json |
| signature_valid | boolean |
| processed_at | timestamp nullable |

Index: (`order_id`, `transaction_status`).

### message_templates
| Kolom | Tipe | Catatan |
|---|---|---|
| key | string unique | `invoice_issued`, `reminder_before_due`, `reminder_due`, `isolated`, `payment_received` (`MessageTemplateKey`) |
| body | text | placeholder `{nama}`, `{nomor_invoice}`, `{total}`, `{jatuh_tempo}`, `{link_bayar}` |
| is_active | boolean | default true |

### message_logs
| Kolom | Tipe |
|---|---|
| customer_id | foreignId nullable |
| invoice_id | foreignId nullable |
| template_key | string nullable (`MessageTemplateKey`) |
| phone | string |
| body | text |
| status | string (`queued`, `sent`, `failed`; `MessageStatus`) |
| provider_message_id | string nullable |
| error | text nullable |
| sent_at | timestamp nullable |

Index: (`invoice_id`, `template_key`) — cek agar pesan untuk kejadian yang sama
pada invoice yang sama tidak dikirim ganda. Tidak unique karena kasir boleh
mengirim ulang tagihan.

### activity_logs
| Kolom | Tipe |
|---|---|
| user_id | foreignId nullable (null = sistem) |
| subject_type, subject_id | nullableMorphs (alias morph) |
| action | string (`customer.isolated`, `payment.received`, ...) |
| properties | json nullable |

### settings
| Kolom | Tipe |
|---|---|
| key | string unique |
| value | json |

Diisi `SettingSeeder` dengan default `billing.*` dari `docs/04-aturan-bisnis.md`
(nilai yang sudah diubah admin tidak ditimpa). `billing.penalty_amount` tidak
di-seed karena denda tidak dipakai di v1.

### sequences
Penghitung berurutan yang dibaca dengan `lockForUpdate` agar aman dari race
condition.

| Kolom | Tipe | Catatan |
|---|---|---|
| key | string unique | `customer` (kode `PLG-`), `invoice:YYYY-MM` (nomor invoice per bulan) |
| last_value | unsignedBigInteger | default 0 |

## Seeder

- **Esensial** (aman diulang, boleh di production): `RolePermissionSeeder`,
  `SettingSeeder`, `MessageTemplateSeeder`.
- **Demo** (`DemoSeeder`, hanya `local`/`testing`): user `admin@example.com`,
  `kasir@example.com`, `teknisi@example.com` (password `password`), 4 paket,
  1 router, 30 pelanggan (22 active, 5 pending, 3 terminated) dengan
  subscription. Belum ada invoice; tagihan dibuat generator di Tahap 04.
