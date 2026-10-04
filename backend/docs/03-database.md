# 03 — Rancangan Database

Ini rancangan awal. Claude boleh mengusulkan perbaikan, tetapi perubahan
harus disetujui dan dokumen ini diperbarui.

Konvensi: semua tabel punya `id` (bigint) dan `timestamps`. Uang = `unsignedBigInteger`
dalam rupiah. Status = string yang dipetakan ke Enum.

## Relasi

```
users ─┐
       └─< activity_logs >── (polymorphic subject)

routers ──< customers >── packages
               │
               ├──< subscriptions >── packages
               ├──< invoices ──< invoice_items
               │        └──< payments
               │        └──< payment_charges (QRIS)
               └──< message_logs

settings (key-value)
message_templates
```

## Tabel

### users
Bawaan Laravel + role lewat spatie/laravel-permission.

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
| password | text | **cast `encrypted`** |
| use_ssl | boolean | default false |
| isolation_profile | string | default "ISOLIR" |
| is_active | boolean | |
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
| status | string | `pending`, `active`, `isolated`, `terminated` |
| installed_at | date nullable | |
| isolated_at | timestamp nullable | |
| terminated_at | timestamp nullable | |
| notes | text nullable | |
| softDeletes | | |

Index: `status`, unique (`router_id`, `pppoe_username`).

### subscriptions
| Kolom | Tipe | Catatan |
|---|---|---|
| customer_id | foreignId | |
| package_id | foreignId | |
| price | unsignedBigInteger | harga dikunci saat berlangganan |
| billing_day | unsignedTinyInteger | 1–28 |
| starts_at | date | |
| ends_at | date nullable | |
| next_package_id | foreignId nullable | ganti paket periode berikutnya |

Satu pelanggan hanya boleh punya satu subscription aktif (`ends_at` null).

### invoices
| Kolom | Tipe | Catatan |
|---|---|---|
| number | string unique | `INV/2026/10/00001` |
| customer_id | foreignId | |
| subscription_id | foreignId | |
| period_start, period_end | date | |
| issued_at | date | |
| due_at | date | |
| subtotal | unsignedBigInteger | |
| discount | unsignedBigInteger | default 0 |
| penalty | unsignedBigInteger | default 0 |
| total | unsignedBigInteger | |
| status | string | `unpaid`, `paid`, `overdue`, `cancelled` |
| paid_at | timestamp nullable | |
| cancelled_reason | string nullable | |

Unique: (`subscription_id`, `period_start`) — mencegah tagihan ganda.
Index: `status`, `due_at`.

### invoice_items
| Kolom | Tipe |
|---|---|
| invoice_id | foreignId |
| description | string |
| quantity | unsignedInteger |
| unit_price | unsignedBigInteger |
| amount | unsignedBigInteger |

### payment_charges
Permintaan QRIS ke gateway (satu invoice bisa punya beberapa percobaan).

| Kolom | Tipe | Catatan |
|---|---|---|
| invoice_id | foreignId | |
| gateway | string | `midtrans` |
| order_id | string unique | dikirim ke gateway |
| amount | unsignedBigInteger | |
| qr_string | text nullable | |
| qr_url | string nullable | |
| status | string | `pending`, `settled`, `expired`, `failed` |
| expires_at | timestamp nullable | |
| raw_response | json nullable | |

### payments
| Kolom | Tipe | Catatan |
|---|---|---|
| invoice_id | foreignId | |
| payment_charge_id | foreignId nullable | |
| method | string | `qris`, `cash`, `transfer` |
| amount | unsignedBigInteger | |
| paid_at | timestamp | |
| reference | string nullable | ID transaksi gateway, unique jika ada |
| received_by | foreignId nullable → users | untuk pembayaran manual |
| notes | text nullable | |

### payment_notifications
Log mentah setiap webhook yang masuk (untuk audit dan idempotensi).

| Kolom | Tipe |
|---|---|
| gateway | string |
| order_id | string index |
| transaction_status | string |
| payload | json |
| signature_valid | boolean |
| processed_at | timestamp nullable |

### message_templates
| Kolom | Tipe | Catatan |
|---|---|---|
| key | string unique | `invoice_issued`, `reminder_before_due`, `reminder_due`, `isolated`, `payment_received` |
| body | text | placeholder `{nama}`, `{nomor_invoice}`, `{total}`, `{jatuh_tempo}`, `{link_bayar}` |
| is_active | boolean | |

### message_logs
| Kolom | Tipe |
|---|---|
| customer_id | foreignId nullable |
| template_key | string nullable |
| phone | string |
| body | text |
| status | string (`queued`, `sent`, `failed`) |
| provider_message_id | string nullable |
| error | text nullable |
| sent_at | timestamp nullable |

### activity_logs
| Kolom | Tipe |
|---|---|
| user_id | foreignId nullable (null = sistem) |
| subject_type, subject_id | morphs |
| action | string (`customer.isolated`, `payment.received`, ...) |
| properties | json nullable |

### settings
| Kolom | Tipe |
|---|---|
| key | string unique |
| value | json |
