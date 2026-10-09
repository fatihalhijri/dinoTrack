# 08 — Kontrak Halaman Inertia

Kontrak antara controller (Tahap 09) dan halaman React (fase frontend). Nama
komponen = path file di `resources/js/pages` tanpa `.tsx`. Sumber kebenaran
props adalah controller dan API Resource di `app/Http/Resources`; test di
`tests/Feature/Http` memeriksa props ini. Jika kode dan dokumen ini berbeda,
perbarui dokumen ini.

## Konvensi umum

- **Uang**: integer rupiah (`150000`). Format tampilan di frontend (`Rp150.000`)
  lewat `formatRupiah()` di `resources/js/lib/format.ts`.
- **Tanggal**: kolom `date` = `YYYY-MM-DD`; timestamp = ISO 8601 dengan zona
  waktu (`2026-10-06T14:30:00+07:00`). Di frontend, `YYYY-MM-DD` **tidak boleh**
  diparse dengan `new Date(str)` (dibaca UTC sehingga bisa bergeser hari); pakai
  `parseDateOnly()`/`formatDate()`. Timestamp ditampilkan dalam zona Asia/Jakarta
  (`formatDateTime()`).
- **Enum**: dikirim sebagai `value` + `*_label` Bahasa Indonesia, misalnya
  `status: "isolated"`, `status_label: "Diisolir"`.
- **Opsi dropdown**: `[{ value, label }]` untuk enum, `[{ id, name, ... }]` untuk data.
- **Pagination** (semua halaman daftar): `Paginated<T>` dari `Model::paginate()` +
  `withQueryString()` lewat `ResourceCollection` (bentuk diuji di
  `PackageControllerTest`):
  `{ data: T[], links: { first, last, prev, next }, meta: { current_page, from, last_page, links: { url, label, page, active }[], path, per_page, to, total } }`.
  Query string: `page`, `per_page` (10/20/50/100, default 20), `search`, dan
  filter per halaman.
- **`filters`**: nilai filter yang sudah divalidasi (hanya yang dikirim), untuk
  mengisi ulang form filter.
- **Validasi**: error Form Request dan penolakan aturan bisnis dari Action
  sama-sama datang sebagai `errors` Inertia (`errors.<field>`). Penolakan Action
  bisa memakai key yang bukan field form (`status`, `customer`, `package`,
  `router`, `invoice`, `user`, `role`) dan perlu ditampilkan sebagai pesan umum
  (`FormErrorAlert`).
- **Flash**: data flash Inertia (`Inertia::flash('toast', ...)`, bukan props):
  `toast = { type: 'success'|'info'|'warning'|'error', message }`, ditangani
  `use-flash-toast.ts`.
- **Akses**: 403 jika permission kurang. Tombol disembunyikan memakai
  `auth.permissions` (lihat props bersama dan bagian Permission); otorisasi tetap
  di backend.
- **Data sensitif yang tidak pernah dikirim**: password router, hash password
  user, rahasia/recovery code 2FA, `remember_token`, `payment_charges.raw_response`,
  payload `payment_notifications`.

## Props bersama (semua halaman)

| Prop | Tipe | Keterangan |
|---|---|---|
| `name` | string | `APP_NAME` (DinoTrack) |
| `auth.user` | AuthUser \| null | `{ id: number, name: string, email: string, role: Role \| null, role_label: string \| null, email_verified_at: string \| null, two_factor_enabled: boolean }`, dibentuk eksplisit di `HandleInertiaRequests` (bukan model mentah); `null` untuk tamu |
| `auth.permissions` | Permission[] | permission user; admin mendapat semuanya |
| `sidebarOpen` | boolean | |

## Permission

Nilai `auth.permissions` (enum `App\Enums\Permission`, tipe TS `Permission`).
Admin selalu mendapat semuanya.

| Permission | Admin | Kasir | Teknisi |
|---|:-:|:-:|:-:|
| `customers.view` | ✓ | ✓ | ✓ |
| `customers.create` | ✓ | | ✓ |
| `customers.update` | ✓ | | |
| `customers.delete` | ✓ | | |
| `customers.activate` | ✓ | ✓ | |
| `customers.terminate` | ✓ | | |
| `customers.isolate` | ✓ | | |
| `packages.view` | ✓ | ✓ | ✓ |
| `packages.manage` | ✓ | | |
| `routers.manage` | ✓ | | |
| `invoices.view` | ✓ | ✓ | |
| `invoices.cancel` | ✓ | | |
| `invoices.resend` | ✓ | ✓ | |
| `payments.view` | ✓ | ✓ | |
| `payments.record` | ✓ | ✓ | |
| `payments.review` | ✓ | | |
| `reports.view` | ✓ | | |
| `settings.manage` | ✓ | | |
| `users.manage` | ✓ | | |

## Bentuk resource

Sumber kebenaran: `app/Http/Resources/*`. Salinan TypeScript ada di
`resources/js/types/models.ts`; ubah keduanya bersamaan. Field bertanda `?`
hanya ada jika relasinya dimuat di halaman tersebut.

```ts
type Role = 'admin' | 'kasir' | 'teknisi'
type CustomerStatus = 'pending' | 'active' | 'isolated' | 'terminated'
type InvoiceStatus = 'unpaid' | 'paid' | 'overdue' | 'cancelled'
type PaymentMethod = 'qris' | 'cash' | 'transfer'
type PaymentReviewStatus = 'none' | 'needs_review' | 'resolved'
type PaymentChargeStatus = 'pending' | 'settled' | 'expired' | 'failed'
type MessageStatus = 'queued' | 'sent' | 'failed'
type MessageTemplateKey = 'invoice_issued' | 'reminder_before_due' | 'reminder_due' | 'isolated' | 'payment_received'

type Package = { id: number; name: string; speed_label: string; price: number; mikrotik_profile: string; is_active: boolean; description: string | null; subscriptions_count?: number }
type Router = { id: number; name: string; host: string; port: number; username: string; use_ssl: boolean; isolation_profile: string; is_active: boolean; last_connected_at: string | null; customers_count?: number }
type PackageSummary = { id: number; name: string; speed_label: string }
type Subscription = { id: number; package?: PackageSummary; next_package?: PackageSummary | null; price: number; billing_day: number; starts_at: string | null; ends_at: string | null }
type Customer = {
  id: number; code: string; name: string; phone: string; address: string; odp: string | null;
  latitude: string | null; longitude: string | null; router?: { id: number; name: string }; pppoe_username: string;
  status: CustomerStatus; status_label: string; isolation_reason: 'overdue' | 'manual' | null; isolation_reason_label: string | null;
  installed_at: string | null; isolated_at: string | null; terminated_at: string | null;
  network_error_at: string | null; network_error: string | null; notes: string | null;
  subscription?: Subscription | null; created_at: string | null
}
type InvoiceItem = { id: number; description: string; quantity: number; unit_price: number; amount: number }
type PaymentCharge = { id: number; attempt: number; order_id: string; amount: number; status: PaymentChargeStatus; status_label: string; expires_at: string | null; created_at: string | null }
type Payment = {
  id: number; invoice?: { id: number; number: string; status: InvoiceStatus; customer: { id: number; code: string; name: string } | null };
  order_id?: string | null; method: PaymentMethod; method_label: string; amount: number; paid_at: string; reference: string | null;
  received_by?: { id: number; name: string } | null; notes: string | null;
  review_status: PaymentReviewStatus; review_status_label: string; review_note: string | null; created_at: string | null
}
type Invoice = {
  id: number; number: string; customer?: { id: number; code: string; name: string; status: CustomerStatus };
  period_start: string; period_end: string; issued_at: string; due_at: string;
  subtotal: number; discount: number; penalty: number; total: number; status: InvoiceStatus; status_label: string;
  paid_at: string | null; cancelled_at: string | null; cancelled_reason: string | null;
  items?: InvoiceItem[]; payments?: Payment[]; payment_charges?: PaymentCharge[]
}
type MessageLog = { id: number; invoice_id: number | null; template_key: MessageTemplateKey | null; template_label: string | null; phone: string; body: string; status: MessageStatus; status_label: string; error: string | null; sent_at: string | null; created_at: string | null }
type ActivityLog = { id: number; action: string; action_label: string; user?: { id: number; name: string } | null; properties: Record<string, unknown> | null; created_at: string | null }
type User = { id: number; name: string; email: string; role: Role | null; role_label: string | null; is_active: boolean; deactivated_at: string | null; created_at: string | null }
type MessageTemplate = { id: number; key: MessageTemplateKey; label: string; body: string; is_active: boolean; updated_at: string | null }
```

## Halaman

### `dashboard` — `GET /dashboard` (semua user login)

| Prop | Tipe | Keterangan |
|---|---|---|
| `summary` | object \| null | hanya `reports.view`: `revenue_this_month`, `payments_this_month`, `outstanding_amount`, `outstanding_invoices`, `active_customers`, `isolated_customers`, `pending_customers`, `due_this_week_amount`, `due_this_week_invoices`, `payments_needing_review`, `payments_needing_review_amount`, `customers_with_network_error`, `generated_at` (cache 5 menit) |
| `customer_counts` | object \| null | `customers.view`: `{ pending, active, isolated, terminated, network_error }`. Tautan galat router → `/customers?network_error=1` |

### `packages/index` — `GET /packages` (`packages.view`)

Tambah/ubah lewat modal di halaman ini (tanpa halaman create/edit).

| Prop | Tipe |
|---|---|
| `packages` | Paginated\<Package\> (dengan `subscriptions_count`) |
| `filters` | `{ search?, is_active?, per_page? }` |

Aksi (`packages.manage`): `POST /packages`, `PUT /packages/{id}` (`name`,
`speed_label`, `price`, `mikrotik_profile`, `description`), `DELETE /packages/{id}`
(ditolak jika pernah dipakai → `errors.package`), `POST /packages/{id}/activate`,
`POST /packages/{id}/deactivate`. Status aktif tidak diubah lewat form ubah.

### `routers/index` — `GET /routers` (`routers.manage`)

| Prop | Tipe |
|---|---|
| `routers` | Paginated\<Router\> (dengan `customers_count`, tanpa password) |

Aksi: `POST /routers` (password wajib), `PUT /routers/{id}` (password kosong =
tidak diubah), `DELETE /routers/{id}` (`errors.router` jika masih punya
pelanggan), `POST /routers/{id}/test` (hasil di `flash.toast`, `success`/`error`).

### `customers/index` — `GET /customers` (`customers.view`)

| Prop | Tipe |
|---|---|
| `customers` | Paginated\<Customer\> (dengan `router`, `subscription.package`) |
| `filters` | `{ search?, status?, package_id?, router_id?, network_error?, per_page? }` |
| `statuses` | `{ value, label }[]` |
| `packages` | `{ id, name, speed_label, price }[]` (semua paket, untuk filter) |
| `routers` | `{ id, name }[]` (semua router, untuk filter) |

`search` mencari kode, nama, nomor WA, dan username PPPoE.

### `customers/create` — `GET /customers/create` (`customers.create`)

| Prop | Tipe |
|---|---|
| `packages` | `{ id, name, speed_label, price }[]` (aktif saja) |
| `routers` | `{ id, name }[]` (aktif saja) |

Aksi: `POST /customers` (`name`, `phone`, `address`, `odp`, `latitude`,
`longitude`, `router_id`, `pppoe_username`, `notes`, `package_id`,
`billing_day` 1–31) → redirect ke `customers/show`.

### `customers/show` — `GET /customers/{id}` (`customers.view`)

| Prop | Tipe | Keterangan |
|---|---|---|
| `customer` | Customer | dengan `router`, `subscription.package`, `subscription.next_package` |
| `invoices` | Invoice[] \| null | 24 terbaru; `null` tanpa `invoices.view` (teknisi). Riwayat lengkap: `/invoices?customer_id=` |
| `payments` | Payment[] \| null | 24 terbaru; `null` tanpa `payments.view` |
| `messages` | MessageLog[] \| null | 24 terbaru; `null` tanpa `invoices.view` |
| `activities` | ActivityLog[] | 24 terbaru, dengan `user`; `action_label` dari `App\Support\ActivityActionLabel` (aksi tanpa label = nama aksi mentah) |
| `packages` | `{ id, name, speed_label, price }[]` \| null | paket aktif untuk ganti paket/aktifkan kembali; `null` tanpa `customers.update` |
| `connection` | `{ online: boolean\|null, error: string\|null }` | **deferred** (`<Deferred data="connection">`); `online: null` jika router tidak terjangkau |

Aksi di halaman ini:

| Aksi | Route | Permission | Input |
|---|---|---|---|
| Tandai terpasang | `POST /customers/{id}/activate` | `customers.activate` | `installed_at?` (YYYY-MM-DD, default hari ini) |
| Berhentikan | `POST /customers/{id}/terminate` | `customers.terminate` | `reason?` |
| Daftar kembali | `POST /customers/{id}/reactivate` | `customers.terminate` | `package_id`, `billing_day` |
| Ganti paket | `PUT /customers/{id}/package` | `customers.update` | `package_id` (null = batalkan rencana) |
| Isolir manual | `POST /customers/{id}/isolate` | `customers.isolate` | `reason` (min 5) |
| Buka isolir | `POST /customers/{id}/release` | `customers.isolate` | `reason` (min 5); `flash.toast.type = 'warning'` jika masih menunggak lewat toleransi |
| Hapus (salah input) | `DELETE /customers/{id}` | `customers.delete` | — (hanya `pending` tanpa tagihan) |

### `customers/edit` — `GET /customers/{id}/edit` (`customers.update`)

| Prop | Tipe |
|---|---|
| `customer` | Customer |
| `routers` | `{ id, name }[]` (aktif + router yang sedang dipakai) |

Aksi: `PUT /customers/{id}` (field sama dengan tambah, tanpa `package_id`;
`billing_day` opsional). Router, username PPPoE, dan `billing_day` hanya bisa
diubah selama `pending` (ditolak Action dengan error per field).

### `invoices/index` — `GET /invoices` (`invoices.view`)

| Prop | Tipe |
|---|---|
| `invoices` | Paginated\<Invoice\> (dengan `customer`) |
| `filters` | `{ search?, status?, period? (YYYY-MM), customer_id?, due?, per_page? }` |
| `statuses` | `{ value, label }[]` |
| `customer` | `{ id, code, name }` \| null — pelanggan dari filter `customer_id` (chip "Tagihan milik …"); `null` tanpa filter atau jika pelanggan tidak ditemukan |

`search` mencari nomor tagihan serta kode dan nama pelanggan. `period` mencocokkan bulan
`period_start`. `due=this_week` = jatuh tempo minggu ini (docs/04 "Laporan": `unpaid`/`overdue`
dengan `due_at` hari ini s.d. H+6, scope `Invoice::dueSoon`, sama dengan kartu dashboard);
nilai lain ditolak validasi.

### `invoices/show` — `GET /invoices/{id}` (`invoices.view`)

| Prop | Tipe | Keterangan |
|---|---|---|
| `invoice` | Invoice | dengan `customer`, `items`, `payments` (+ `received_by`, `order_id`), `payment_charges` (terbaru dulu) |
| `payment_link` | string | signed URL halaman tagihan publik untuk disalin/dibagikan |
| `messages` | MessageLog[] | pesan WhatsApp untuk tagihan ini |
| `packages` | `{ id, name, speed_label, price }[]` \| null | paket aktif untuk koreksi terbit ulang; `null` tanpa `invoices.cancel` |
| `replacement` | `{ id, number }` \| null | invoice aktif (bukan `cancelled`) untuk subscription dan periode yang sama, hasil terbit ulang; hanya diisi untuk invoice `cancelled`. Jika terisi, periode itu tidak bisa diterbitkan ulang lagi |
| `business` | `{ name: string, address: string\|null, whatsapp: string\|null }` | identitas usaha untuk tampilan cetak; `name` = `APP_NAME` jika belum diisi |
| `payment_methods` | `{ value: 'cash'\|'transfer', label }[]` \| null | metode untuk dialog catat pembayaran (QRIS hanya dicatat gateway); `null` tanpa `payments.record` |

Aksi:

| Aksi | Route | Permission | Input |
|---|---|---|---|
| Catat pembayaran | `POST /invoices/{id}/payments` | `payments.record` | `method` (`cash`/`transfer`), `amount` (= total), `paid_at?`, `notes?` |
| Batalkan | `POST /invoices/{id}/cancel` | `invoices.cancel` | `reason` (min 5) |
| Terbit ulang | `POST /invoices/{id}/reissue` | `invoices.cancel` | `package_id?` (paket koreksi) → redirect ke invoice baru |
| Kirim ulang WA | `POST /invoices/{id}/resend` | `invoices.resend` | — (maks 6/menit; ditolak jika masih antre/template nonaktif) |

### `payments/index` — `GET /payments` (`payments.view`)

| Prop | Tipe |
|---|---|
| `payments` | Paginated\<Payment\> (dengan `invoice.customer`, `order_id`, `received_by`) |
| `filters` | `{ search?, method?, review_status?, from?, to?, customer_id?, per_page? }` |
| `methods` | `{ value, label }[]` |
| `review_statuses` | `{ value, label }[]` |
| `customer` | `{ id, code, name }` \| null — pelanggan dari filter `customer_id` (chip "Pembayaran milik …", tautan "Lihat semua pembayaran" di `customers/show`); `null` tanpa filter atau jika pelanggan tidak ditemukan |

`search` mencari referensi gateway, nomor tagihan, serta kode dan nama pelanggan. `from`/`to`
menyaring tanggal `paid_at` (inklusif). `customer_id` mencocokkan pemilik tagihan.

Aksi: `PATCH /payments/{id}/review` (`payments.review`, `review_note` min 5) —
menandai anomali `needs_review` → `resolved`; catatan ditambahkan di bawah alasan anomali.

### `users/index` — `GET /users` (`users.manage`)

| Prop | Tipe |
|---|---|
| `users` | Paginated\<User\> |
| `filters` | `{ search?, role?, per_page? }` |
| `roles` | `{ value, label }[]` |

Aksi: `POST /users` (`name`, `email`, `password`, `password_confirmation`,
`role`), `PUT /users/{id}` (password kosong = tidak diubah),
`POST /users/{id}/deactivate`, `POST /users/{id}/reactivate`,
`DELETE /users/{id}` (hanya akun tanpa jejak audit). Admin tidak bisa
mengubah role/menonaktifkan/menghapus dirinya sendiri, dan admin aktif
terakhir dilindungi (`errors.user` / `errors.role`).

### `settings/business` — `GET /settings/business` (`settings.manage`)

| Prop | Tipe |
|---|---|
| `business` | `{ name: string\|null, address: string\|null, whatsapp: string\|null }` |
| `default_name` | string (`APP_NAME`, dipakai jika `name` kosong) |

Aksi: `PUT /settings/business` (semua opsional; WA dinormalisasi ke `62xxx`).
Logo usaha menyusul di fase frontend.

### `settings/billing` — `GET /settings/billing` (`settings.manage`)

| Prop | Tipe |
|---|---|
| `billing` | `{ due_days, grace_days, reminder_days_before, prorate_first_month, auto_isolate, auto_activate }` |
| `limits` | `{ max_due_days: 31, max_grace_days: 30 }` |

Aksi: `PUT /settings/billing` (semua wajib; `reminder_days_before` < `due_days`).

### `settings/message-templates` — `GET /settings/message-templates` (`settings.manage`)

| Prop | Tipe |
|---|---|
| `templates` | MessageTemplate[] |
| `placeholders` | `Record<string, string>` (`{nama}` → arti, dst.) |
| `max_length` | number (2000) |

Aksi: `PUT /settings/message-templates/{id}` (`body`, `is_active`).

### `reports/index` — `GET /reports` (`reports.view`)

| Prop | Tipe |
|---|---|
| `year`, `from`, `to` | number, string, string (default tahun ini, awal bulan ini s.d. hari ini) |
| `revenue` | `{ month (1–12), by_method: { qris, cash, transfer }, total, payment_count }[]` (12 bulan) |
| `aging` | `{ bucket: '0-7'\|'8-30'\|'31+', label, invoice_count, amount }[]` |
| `movement` | `{ from, to, new_customers, terminated_customers, isolated_customers }` |

Query: `year`, `from`, `to`. Unduhan CSV (UTF-8 BOM, pemisah `;`):
`GET /reports/export/payments?from=&to=` (wajib), `GET /reports/export/outstanding`,
`GET /reports/export/revenue?year=`.

### `reports/outstanding` — `GET /reports/outstanding` (`reports.view`)

| Prop | Tipe |
|---|---|
| `invoices` | Paginated\<{ id, number, customer_id, customer_code, customer_name, customer_status, due_at, age_days, total, status }\> (paling lama dulu) |
| `filters` | `{ search?, per_page? }` |

### Halaman bawaan starter kit (tidak berubah)

`welcome`, `auth/*`, `settings/profile`, `settings/security`,
`settings/appearance`. Halaman login menampilkan `status` (misalnya pesan akun
dinonaktifkan).

### Halaman publik (Blade, bukan Inertia)

`/isolir`, `/tagihan/{invoice}` (signed) — lihat `docs/02-arsitektur.md`.
