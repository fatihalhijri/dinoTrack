# Progress Pengembangan Backend

Status: ⬜ belum · 🟨 sedang dikerjakan · ✅ selesai

Tahap berikutnya: **Tahap 01** (database, model & enum).

| Tahap | Nama | Status | Tanggal | Catatan |
|---|---|---|---|---|
| 00 | Setup proyek & tooling | ✅ | 2026-10-04 | Package, kontrak, fake, stub, Money, script composer. Fitur Teams starter kit dibuang (lihat T6). Pint, PHPStan, test hijau |
| 01 | Database, model & enum | ⬜ | | |
| 02 | Role, permission & otorisasi | ⬜ | | |
| 03 | Master data (paket, router, pelanggan) | ⬜ | | |
| 04 | Tagihan otomatis | ⬜ | | |
| 05 | Pembayaran manual & QRIS | ⬜ | | |
| 06 | Mikrotik: isolir & aktivasi | ⬜ | | |
| 07 | Notifikasi WhatsApp | ⬜ | | |
| 08 | Laporan & metrik dashboard | ⬜ | | |
| 09 | Controller & route | ⬜ | | |
| 10 | Review keamanan | ⬜ | | |
| 11 | Kesiapan deploy | ⬜ | | |

## Keputusan penting

Catat di sini setiap keputusan yang menyimpang dari `docs/` beserta alasannya.

| Tanggal | Keputusan | Alasan |
|---|---|---|
| 2026-10-04 | Database dev dan test memakai MySQL (bukan SQLite); test memakai database `dinotrack_testing` | `pdo_sqlite` tidak terpasang; sesuai stack MySQL 8 |
| 2026-10-04 | Stack dinaikkan ke Laravel 13 / PHP 8.4; timezone `Asia/Jakarta`, locale `id` | Sesuai versi terpasang dan CLAUDE.md |
| 2026-10-04 | K1 Single-tenant, tanpa `tenant_id` | Lebih sederhana; SaaS di luar cakupan v1 |
| 2026-10-04 | K2 `billing_day` per langganan, bebas dari tanggal pasang; prorata dari tanggal pasang sampai `billing_day` pertama (sama = periode penuh; `prorate_first_month=false` = harga penuh) | Menghapus kontradiksi billing_day vs prorata di docs/04 |
| 2026-10-04 | K3 Pelanggan `pending` diaktifkan admin/kasir (bukan teknisi); tagihan pertama langsung terbit, internet aktif tanpa menunggu bayar | Sebelumnya tidak ada pemicu `pending → active` maupun tagihan pertama |
| 2026-10-04 | K4 Satu pembayaran = satu invoice, nominal pas; tanpa pembayaran sebagian, bayar di muka, kelebihan, atau saldo | Skema `payments` sederhana dan webhook mudah dibuat idempotent |
| 2026-10-04 | K5 Denda tidak dipakai di v1 (`invoices.penalty` selalu 0) | Denda mengubah total setelah charge QRIS terbit sehingga nominal tidak cocok |
| 2026-10-04 | K6 Pembayaran anomali tetap dicatat dengan `review_status = needs_review`; invoice tidak berubah; refund manual | Uang pelanggan tidak boleh hilang tanpa jejak |
| 2026-10-04 | K7 Tanpa biaya untuk pelanggan; fee gateway ditanggung ISP dan tidak dicatat | Total invoice sama untuk semua metode bayar |
| 2026-10-04 | K8 Isolir otomatis setelah `due_at + grace_days` (default 3, bisa 0) dan dibuka otomatis saat lunas; isolir manual (`isolation_reason = manual`) hanya dibuka manual | Admin tetap mengendalikan isolir karena komplain/pelanggaran |
| 2026-10-04 | K9 Pelanggan `terminated` bisa kembali ke `pending` dengan subscription baru; pelanggan ber-invoice tidak boleh di-soft-delete | Riwayat tagihan tetap menyatu |
| 2026-10-04 | K10 Ganti paket hanya mulai periode berikutnya, tanpa prorata; pelanggan `isolated` tidak menimpa profil isolir | Menghindari jenis invoice selisih dan benturan profil router |
| 2026-10-04 | A1 Nomor invoice `INV/YYYY/MM/NNNNN` (5 digit), reset tiap bulan; `order_id` mengikuti (`INV20261000001-1`) | Menyamakan contoh yang tidak konsisten di docs/03 dan docs/05 |
| 2026-10-04 | A2 `invoices.discount` ada di skema tetapi selalu 0 di v1 | Belum ada aturan atau fitur diskon |
| 2026-10-04 | A3 Generator tagihan catch-up dan aman dijalankan ulang | Server mati sehari tidak boleh menghilangkan tagihan |
| 2026-10-04 | A4 Link tagihan tidak kedaluwarsa selama invoice belum `paid`/`cancelled` | Pelanggan bisa memakai link lama |
| 2026-10-04 | A5 Charge QRIS baru hanya jika charge sebelumnya `expired`/`failed` | Mencegah charge ganda setiap halaman dibuka |
| 2026-10-04 | T1 `midtrans/midtrans-php` tidak dipasang; Tahap 05 memakai HTTP client Laravel | Hanya 3 endpoint, lebih mudah di-fake, dependensi lebih sedikit |
| 2026-10-04 | T2 PHPStan tetap level 7 dengan path `app/`, `bootstrap/`, `config/`, `database/`, `routes/` (prompt menyebut level 6, `app/`) | Baseline starter kit sudah lolos di level 7; menurunkan berarti mundur |
| 2026-10-04 | T3 Model `Customer`, `Invoice`, `Router` dibuat sebagai kelas kosong di Tahap 00 | Interface di `app/Contracts` mengetik parameter dengan model itu; skema dan relasi diisi di Tahap 01 |
| 2026-10-04 | T4 `declare_strict_types` diterapkan ke seluruh kode (bukan hanya file baru); `database/migrations` dikecualikan dari Pint | Konsistensi `composer lint`; migration yang sudah di-commit tidak boleh diubah |
| 2026-10-04 | T5 Enum `PaymentChargeStatus` dibuat di Tahap 00 | Dipakai DTO `PaymentChargeResult`/`GatewayNotification`; Tahap 01 memakainya untuk `payment_charges.status` |
| 2026-10-04 | T6 Fitur Teams starter kit dibuang (backend, React, migration `drop_teams_feature`); dashboard menjadi `/dashboard` | Arsitektur single-tenant (K1); Teams tidak dipakai |

## Utang teknis

Hal yang sengaja ditunda untuk dikerjakan nanti.

Belum ada.
