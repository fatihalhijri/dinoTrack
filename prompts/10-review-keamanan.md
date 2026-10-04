# Tahap 10 — Review Keamanan & Ketahanan

**Tujuan:** audit menyeluruh sebelum frontend dan deploy. Temuan dicatat
dulu, perbaikan dikerjakan setelah disetujui.

## Prompt

```
Tahap 10: review keamanan dan ketahanan seluruh backend. JANGAN mengubah kode
dulu. Hasilkan laporan di docs/09-audit-keamanan.md dengan tingkat
keparahan (Kritis / Tinggi / Sedang / Rendah), lokasi file, dan usulan
perbaikan.

Periksa minimal:
1. Webhook: signature, idempotensi, pencocokan nominal, rate limit, replay.
2. Otorisasi: setiap route dan action, IDOR (akses data via ID orang lain),
   mass assignment.
3. Data sensitif: password router terenkripsi, tidak bocor ke props Inertia,
   log, atau pesan error.
4. Konsistensi data: transaksi dan lock pada pembayaran, nomor invoice,
   kode pelanggan.
5. Kegagalan integrasi: router mati, Midtrans timeout, WhatsApp gagal —
   apakah sistem tetap konsisten dan admin tahu?
6. Signed URL halaman publik: masa berlaku, enumerasi.
7. Scheduler dan queue: overlap, job ganda, failed jobs.
8. Dependensi: jalankan composer audit.
9. Cakupan test: aturan bisnis mana yang belum ditest.

Setelah laporan saya setujui, perbaiki temuan Kritis dan Tinggi lebih dulu,
masing-masing dengan test yang membuktikan perbaikannya.
```

## Commit
`fix(security): perbaikan hasil audit keamanan`
