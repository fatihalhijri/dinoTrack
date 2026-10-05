# Progress Pengembangan Backend

Status: ⬜ belum · 🟨 sedang dikerjakan · ✅ selesai

Tahap berikutnya: **Tahap 04** (tagihan otomatis).

| Tahap | Nama | Status | Tanggal | Catatan |
|---|---|---|---|---|
| 00 | Setup proyek & tooling | ✅ | 2026-10-04 | Package, kontrak, fake, stub, Money, script composer. Fitur Teams starter kit dibuang (lihat T6). Pint, PHPStan, test hijau |
| 01 | Database, model & enum | ✅ | 2026-10-04 | Skema 14 tabel, 13 model, 8 enum, factory, seeder esensial + demo; Pint, PHPStan, 137 test hijau |
| 02 | Role, permission & otorisasi | ✅ | 2026-10-05 | Matriks 19 permission × 3 role, 6 policy, `Gate::before` admin, `auth.permissions` di Inertia, registrasi publik dan hapus akun sendiri dibuang; Pint, PHPStan, 249 test hijau |
| 03 | Master data (paket, router, pelanggan) | ✅ | 2026-10-05 | 14 Action + 9 Form Request + `DisableCustomerSecretJob`, kode pelanggan teruji aman di 4 proses paralel, pesan validasi Bahasa Indonesia; Pint, PHPStan, 383 test hijau |
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
| 2026-10-04 | D1 Paket pelanggan hanya lewat subscription aktif (tanpa `customers.package_id`); diagram docs/03 diperbaiki | Menghindari data ganda yang bisa tidak sinkron |
| 2026-10-04 | D2 `subscriptions.starts_at` nullable (diisi saat aktivasi); satu subscription aktif dijaga generated column `is_current` + unique (`customer_id`, `is_current`) | Pelanggan `pending` belum punya tanggal mulai; aturan dijaga di level database (khusus MySQL) |
| 2026-10-04 | D3 Kolom tambahan: `invoices.cancelled_at`, `payment_charges.attempt` (unique per invoice), `message_logs.invoice_id`; tabel baru `sequences` | Laporan pembatalan, order_id aman dari race, cek kiriman WA ganda per invoice, kode/nomor berurutan dengan `lockForUpdate` |
| 2026-10-04 | D4 Index disesuaikan query: (`invoices.status`, `due_at`), `payments.paid_at`/`review_status`, `payment_charges.status`, (`payment_notifications.order_id`, `transaction_status`) | Query isolir, pengingat, laporan, rekonsiliasi, idempotensi webhook |
| 2026-10-04 | D5 Semua FK `restrictOnDelete` (termasuk ke `users`), kecuali `invoice_items` cascade; morph map alias | Data uang/audit tidak ikut terhapus; fitur hapus akun sendiri perlu ditinjau di Tahap 02 |
| 2026-10-04 | D6 Enum tambahan `IsolationReason`, `PaymentReviewStatus`, `MessageTemplateKey` | Rule 9 CLAUDE.md: status pakai Enum |
| 2026-10-04 | D7 `User` memakai `HasRoles`; `RoleSeeder` membuat 3 role tanpa permission; seeder esensial vs `DemoSeeder` (hanya local/testing); demo belum berisi invoice/pelanggan isolated | Permission di Tahap 02; tagihan dibuat generator Tahap 04 |
| 2026-10-04 | D8 Model memakai atribut `#[Fillable]`/`#[Hidden]`/`#[Scope]` (Laravel 13) mengikuti `User.php`; kolom `date` diserialisasi `Y-m-d` | Konsisten dengan kode starter kit; mencegah tanggal bergeser karena konversi UTC |
| 2026-10-05 | R1 Matriks 19 permission × 3 role disetujui (lihat docs/01); sumber tunggal `Role::permissions()`; `RoleSeeder` diganti nama `RolePermissionSeeder` dan menyinkronkan matriks (perubahan manual di database ditimpa, permission usang dihapus) | Matriks tidak diedit lewat UI di v1; kode jadi satu sumber kebenaran |
| 2026-10-05 | R2 Policy hanya mengecek permission; syarat status data di Action. Admin lolos lewat `Gate::before` dan tetap di-seed dengan semua permission | `Gate::before` melewati seluruh policy, sehingga aturan status di policy tidak akan berlaku untuk admin |
| 2026-10-05 | R3 Enum `Permission` dan `Role` (menggantikan string `'admin'` dan `RoleSeeder::ROLES`) | Rule 9 CLAUDE.md; tanpa string tersebar di policy |
| 2026-10-05 | R4 Registrasi publik Fortify dimatikan: `CreateNewUser`, `RegisterResponse`, halaman `auth/register`, link di login/welcome dihapus | Akun pegawai dibuat admin; user tanpa role tidak boleh bisa masuk sendiri |
| 2026-10-05 | R5 Fitur hapus akun sendiri dibuang (route, `ProfileController::destroy`, `ProfileDeleteRequest`, komponen `delete-user`) | Akun pegawai adalah jejak audit (FK `restrict`, D5); menyelesaikan utang teknis Tahap 01 |
| 2026-10-05 | R6 `auth.permissions` (`string[]`) dibagikan ke Inertia; admin selalu mendapat semua permission | Frontend menyembunyikan tombol; otorisasi tetap di backend |
| 2026-10-05 | M1 `billing_day` adalah input wajib 1–31 saat mendaftar (29–31 dibulatkan ke 28), bukan diturunkan dari tanggal pasang; prompts/03 dianggap usang | Mengikuti K2; saat pendaftaran pelanggan masih `pending` sehingga tanggal pasang belum ada |
| 2026-10-05 | M2 `DeletePackage` ditolak jika paket dirujuk subscription mana pun (aktif, riwayat, `next_package_id`); `DeactivatePackage` terpisah. Paket/router nonaktif tidak bisa dipilih untuk pelanggan baru atau ganti paket | Pesan jelas sebelum FK `restrict` menolak; subscription lama tetap berjalan |
| 2026-10-05 | M3 Cakupan tambahan: `DeleteRouter` (ditolak jika masih punya pelanggan, termasuk soft delete), `DeleteCustomer` (ditolak jika sudah punya invoice), `ReactivateCustomer` (`terminated → pending` + subscription baru) | Policy sudah ada; murni master data |
| 2026-10-05 | M4 `TestRouterConnection` sinkron (menyimpang dari aturan 3 CLAUDE.md); implementasi Mikrotik Tahap 06 wajib timeout pendek. Isolir, aktivasi, dan disable secret tetap lewat job | Admin menunggu hasil tes di layar |
| 2026-10-05 | M5 `lang/id/validation.php` lengkap + `attributes()` per Form Request; `messages()` hanya untuk aturan khusus | Locale `id`; pesan validasi starter kit ikut berbahasa Indonesia |
| 2026-10-05 | M6 Pelanggaran aturan bisnis di Action melempar `ValidationException::withMessages()`; Action menerima array hasil `validated()` + `?User $by` | Langsung tampil di form Inertia tanpa kelas exception baru |
| 2026-10-05 | M7 `UpdateCustomer`: router, username PPPoE, dan `billing_day` hanya bisa diubah selama `pending`. `ChangeCustomerPackage`: `pending` diganti langsung (paket + harga), `active`/`isolated` mengisi `next_package_id`, `null` membatalkan rencana | Setelah terpasang, perubahan itu berarti memindahkan secret router atau menggeser periode; K10 |
| 2026-10-05 | M8 `TerminateCustomer` hanya dari `active`/`isolated`; status, `terminated_at`, dan subscription (`ends_at`, `next_package_id = null`) langsung berubah dalam transaksi, `DisableCustomerSecretJob` di-dispatch `afterCommit`. `DeleteCustomer` hanya untuk `pending` tanpa invoice dan ikut mengakhiri subscription | Tagihan harus berhenti meski router mati; pelanggan yang pernah terpasang punya secret aktif di router sehingga wajib lewat berhenti |
| 2026-10-05 | M9 `DisableCustomerSecretJob`: status dicek ulang saat berjalan (dilewati jika pelanggan sudah tidak `terminated`); `RouterUnreachableException` dicoba ulang (5x, backoff 10s–15m); `SecretNotFoundException` langsung gagal; `failed()` menulis `Log::error` + activity log `customer.secret_disable_failed`; `uniqueFor` 1 jam | Job lama tidak boleh menonaktifkan secret pelanggan yang sudah diaktifkan kembali; secret tak ditemukan bisa berarti username salah |
| 2026-10-05 | M10 Helper `SequenceGenerator` (satu perintah `INSERT … ON DUPLICATE KEY UPDATE` lalu baca dengan `lockForUpdate`, ikut transaksi pemanggil), `ActivityLogger` (properti `changes` [kolom => [lama, baru]], kolom tersembunyi disamarkan `***`), `PhoneNumber::normalize()` (hanya `08` dan `+62` sesuai docs/05) | Pola awal `insertOrIgnore` + `SELECT FOR UPDATE` terbukti deadlock (SQLSTATE 40001) di test 4 proses paralel; versi baru lolos berulang. Password router tidak masuk log |
| 2026-10-05 | M11 Konvensi lock: aksi yang mengubah pelanggan atau subscription-nya membaca ulang baris pelanggan dengan `lockForUpdate()` di dalam transaksi lalu memvalidasi status di sana; paket/router yang dipilih dibaca dengan `sharedLock()`. Tahap 04 (aktivasi, generator) wajib mengikuti urutan lock yang sama | Mencegah balapan berhenti vs ganti paket, aktivasi vs ganti router, hapus vs terbit invoice |
| 2026-10-05 | M12 Action mengubah angka input ke integer (`validated()` tidak mengubah tipe) dan hanya mengambil field yang diizinkan (`Arr::only`); Form Request menyediakan accessor bertipe (`packageId()`, `billingDay()`) untuk aksi berparameter `int` | Input form React selalu string; Action juga dipanggil dari job/command sehingga tidak mengandalkan `validated()` |

## Utang teknis

Hal yang sengaja ditunda untuk dikerjakan nanti.

- Pelanggan yang di-soft-delete tetap memegang `pppoe_username` di router-nya (unique index mencakup baris terhapus), sehingga data yang salah input tidak bisa didaftarkan ulang dengan username yang sama. Opsi: ubah username saat dihapus, atau hard delete untuk pelanggan tanpa invoice. Putuskan sebelum Tahap 09.
- "Flag" untuk admin saat job router gagal belum punya kolom/mekanisme; sementara hanya activity log `customer.secret_disable_failed` + log error. Rancang di Tahap 06 bersama isolir/aktivasi.
- Tahap 06: implementasi `MikrotikNetworkController::testConnection()` wajib memakai timeout pendek dan mengubah semua kegagalan koneksi (termasuk gagal login API) menjadi `false` atau `RouterUnreachableException`; `TestRouterConnection` hanya menangkap exception itu (review T03 #12).
- Paket bisa dinonaktifkan lewat dua jalan: `DeactivatePackage` (log `package.deactivated`) dan `UpdatePackage` dengan `is_active` (log `package.updated`). Pertimbangkan `ActivatePackage` dan keluarkan `is_active` dari `UpdatePackage` saat controller dibuat di Tahap 09 (review T03 #9).
- `ReactivateCustomer` mengosongkan `installed_at`; tanggal pasang pertama hanya tersisa di activity log. Putuskan definisi "pelanggan baru" sebelum laporan Tahap 08 (review T03 #10).
- Aturan validasi bersama memakai dua pola: method statis di `StorePackageRequest`/`StoreRouterRequest` dan trait `app/Concerns/CustomerValidationRules`. Satukan ke pola trait saat menyentuh Form Request lagi (review T03 #11).
- Hapus user oleh admin (Tahap 09) akan gagal untuk user yang punya pembayaran/log karena FK `restrict` (D5). Action `DeleteUser` perlu menolak dengan pesan jelas (atau menonaktifkan user) dan menolak admin menghapus dirinya sendiri.
- `npm run check` (vp) melaporkan format markdown di `docs/`, `prompts/`, `PROGRESS.md`, `MULAI-DI-SINI.md`, `pint.json` sejak sebelum Tahap 02; belum dirapikan.
