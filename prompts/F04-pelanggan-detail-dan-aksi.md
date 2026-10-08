# Tahap F04 — Pelanggan: Detail & Aksi

**Tujuan:** satu halaman detail pelanggan yang memuat data, status koneksi,
riwayat, dan semua aksi siklus hidup pelanggan.

## Prompt

```
Tahap F04: detail dan aksi pelanggan. Baca CLAUDE.md (aturan frontend),
docs/08 bagian `customers/show`, docs/04-aturan-bisnis.md (status pelanggan,
isolir, ganti paket, berhenti), dan app/Http/Controllers/CustomerController,
CustomerLifecycleController, CustomerIsolationController.

Kerjakan:
1. pages/customers/show.tsx:
   - header: kode, nama, StatusBadge, alasan isolir, WA (tautan wa.me),
     alamat/ODP/koordinat (tautan peta jika ada)
   - kartu koneksi: router, username PPPoE, paket aktif, rencana ganti
     paket (next_package), billing_day, status online dari prop deferred
     `connection` (<Deferred> dengan skeleton; online null = "router tidak
     terjangkau")
   - Alert merah jika network_error_at terisi (pesan network_error)
2. Tab Tagihan, Pembayaran, Pesan WA, dan Aktivitas. Tab disembunyikan jika
   prop-nya null (teknisi). Tautan "lihat semua tagihan" ke
   /invoices?customer_id=.
3. Aksi lewat dialog, ditampilkan sesuai permission DAN status:
   - Tandai terpasang (pending; installed_at default hari ini)
   - Berhentikan (active/isolated; alasan opsional)
   - Daftar kembali (terminated; paket + billing_day)
   - Ganti paket / batalkan rencana (package_id null)
   - Isolir manual (alasan min 5)
   - Buka isolir (alasan min 5; toast warning dari backend)
   - Hapus (hanya pending tanpa tagihan)
   Error aturan bisnis (errors.status, errors.customer, …) tampil di dialog.
4. Layout dua kolom di laptop, satu kolom di HP; aksi utama di menu
   dropdown "Aksi" di HP.

Test (perluas CustomerControllerTest): komponen ada; teknisi menerima
invoices/payments/messages null; connection deferred; packages null tanpa
customers.update.
Rencana dulu, tunggu persetujuan.
```

## Kriteria selesai
- Semua aksi docs/08 bisa dijalankan dari UI dan hanya muncul saat valid
- Halaman tidak menunggu router (status koneksi deferred)
- Test hijau

## Commit
`feat(customer): halaman detail dan aksi pelanggan`
