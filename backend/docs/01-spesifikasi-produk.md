# 01 — Spesifikasi Produk

## Ringkasan

Aplikasi web untuk ISP / RT-RW Net mengelola siklus penagihan pelanggan
internet dari ujung ke ujung: tagihan dibuat otomatis, pelanggan diingatkan
lewat WhatsApp, membayar dengan QRIS, dan layanan internet diisolir atau
diaktifkan otomatis sesuai status pembayaran.

## Tujuan utama

1. Admin tidak perlu membuat tagihan manual setiap bulan.
2. Pelanggan menerima pengingat otomatis sebelum dan saat jatuh tempo.
3. Pembayaran QRIS terkonfirmasi otomatis tanpa cek mutasi manual.
4. Pelanggan yang menunggak diisolir otomatis, dan langsung aktif kembali
   beberapa detik setelah membayar.
5. Pemilik usaha bisa melihat pendapatan dan tunggakan kapan saja.

## Role pengguna

| Role | Hak akses utama |
|---|---|
| **admin** | Semua fitur, termasuk pengaturan, router, user, dan laporan |
| **kasir** | Lihat pelanggan dan tagihan, catat pembayaran manual, kirim ulang tagihan |
| **teknisi** | Lihat data pelanggan dan status koneksi, tambah pelanggan baru (status menunggu aktivasi) |

## Modul dan fitur

### 1. Autentikasi & pengguna
- Login, logout, lupa password (dari starter kit)
- Manajemen user dan role (admin saja)

### 2. Paket internet
- CRUD paket: nama, kecepatan (contoh "20 Mbps"), harga bulanan, nama profil PPPoE di Mikrotik
- Paket tidak bisa dihapus jika masih dipakai pelanggan; bisa dinonaktifkan

### 3. Router (Mikrotik)
- CRUD router: nama, host, port API, username, password (terenkripsi), status aktif
- Tes koneksi ke router

### 4. Pelanggan
- CRUD pelanggan: kode pelanggan, nama, nomor WhatsApp, alamat, ODP, koordinat (opsional)
- Data koneksi: router, username PPPoE, paket
- Status: `pending`, `active`, `isolated`, `terminated`
- Riwayat tagihan, pembayaran, dan aktivitas per pelanggan

### 5. Langganan (subscription)
- Menghubungkan pelanggan dengan paket, tanggal mulai, tanggal tagih
- Ganti paket berlaku pada periode tagihan berikutnya

### 6. Tagihan (invoice)
- Dibuat otomatis oleh scheduler sesuai `docs/04-aturan-bisnis.md`
- Nomor unik berurutan, periode, jatuh tempo, item, total
- Status: `unpaid`, `paid`, `overdue`, `cancelled`
- Pembatalan tagihan oleh admin wajib menyertakan alasan

### 7. Pembayaran
- Otomatis via QRIS dinamis (payment gateway)
- Manual oleh kasir (tunai/transfer) dengan catatan
- Setiap pembayaran lunas memicu aktivasi jika pelanggan sedang diisolir

### 8. Isolir & aktivasi
- Isolir otomatis jika tagihan lewat masa toleransi
- Aktivasi otomatis setelah lunas
- Isolir/aktivasi manual oleh admin dengan alasan

### 9. Notifikasi WhatsApp
- Tagihan terbit, pengingat H-3, pengingat hari jatuh tempo,
  pemberitahuan isolir, konfirmasi pembayaran
- Template pesan bisa diubah admin
- Log status pengiriman setiap pesan

### 10. Halaman publik pelanggan (tanpa login)
- Halaman tagihan via link bertanda tangan (signed URL): rincian + QRIS
- Halaman isolir (tujuan redirect dari Mikrotik)
- Dibuat dengan Blade biasa agar ringan

### 11. Laporan & dashboard
- Pendapatan per bulan, per metode pembayaran
- Daftar tunggakan dan umur tunggakan
- Pelanggan baru, berhenti, terisolir
- Ekspor CSV

### 12. Pengaturan
- Profil usaha (nama, alamat, logo, nomor WA admin)
- Aturan tagihan (lihat `docs/04-aturan-bisnis.md`)
- Template pesan WhatsApp

## Di luar cakupan versi 1

- Aplikasi mobile
- Multi-tenant / SaaS (lihat keputusan di `docs/02-arsitektur.md`)
- Integrasi OLT
- Akuntansi lengkap (jurnal, neraca)
