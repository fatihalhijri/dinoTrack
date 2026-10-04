# Tahap 06 — Mikrotik: Isolir & Aktivasi Otomatis

**Tujuan:** pelanggan yang menunggak diisolir otomatis dan aktif kembali
otomatis setelah membayar, dengan penanganan kegagalan router yang andal.

**Prasyarat (opsional untuk uji manual):** Mikrotik CHR di VirtualBox dengan
API aktif, PPP profile `ISOLIR`, dan beberapa PPP secret uji.

## Prompt

```
Tahap 06: integrasi Mikrotik. Baca docs/05-integrasi.md bagian Mikrotik dan
docs/04-aturan-bisnis.md bagian status pelanggan.

Kerjakan:
1. MikrotikNetworkController (implementasi NetworkController) memakai
   evilfreelancer/routeros-api-php: testConnection, isolate, activate,
   disableSecret, isOnline. Koneksi dengan timeout, dukung SSL, tutup koneksi
   dengan benar. Lempar RouterUnreachableException dan SecretNotFoundException.
2. Action IsolateCustomer dan ActivateCustomer: panggil router dulu, ubah
   status database HANYA jika router berhasil, catat activity log, set
   isolated_at.
3. Job IsolateCustomerJob dan ActivateCustomerJob: ShouldBeUnique per
   pelanggan, retry dengan backoff [30, 120, 600], tidak retry untuk
   SecretNotFoundException, failed() mencatat log dan menandai butuh tindakan.
4. Action IsolateOverdueCustomers + command billing:isolate-overdue sesuai
   aturan grace_days dan setting auto_isolate. Jadwalkan setelah mark-overdue.
5. Isolir/aktivasi manual oleh admin dengan alasan wajib.
6. Lengkapi pemanggilan ActivateCustomerJob dari Tahap 05.
7. Halaman isolir publik (Blade, ringan, mobile-first): tampilkan nama usaha,
   pesan isolir, dan cara bayar. Pelanggan dikenali dari IP jika
   memungkinkan, jika tidak tampilkan form cek tagihan via kode pelanggan.
8. Test dengan FakeNetworkController: router gagal → status tidak berubah,
   retry, pelanggan masih punya tunggakan lain tidak diaktifkan, auto_isolate
   false, aktivasi ganda tidak dijalankan bersamaan.

Rencana dulu, tunggu persetujuan.
```

## Uji manual
Jalankan `php artisan tinker` lalu panggil `TestRouterConnection` ke CHR, coba
isolir dan aktivasi satu secret uji, cek di Winbox.

## Commit
`feat(network): isolir dan aktivasi otomatis via Mikrotik`
