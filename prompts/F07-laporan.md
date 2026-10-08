# Tahap F07 — Laporan

**Tujuan:** pemilik usaha melihat pendapatan, tunggakan, dan pergerakan
pelanggan, serta mengunduh CSV.

## Prompt

```
Tahap F07: laporan. Baca CLAUDE.md (aturan frontend), docs/08 bagian
`reports/index` dan `reports/outstanding`, PROGRESS.md keputusan L2–L6, dan
app/Http/Controllers/ReportController. Baca skill dataviz sebelum membuat
grafik.

Kerjakan:
1. Package: tambahkan komponen chart lewat `npx shadcn@latest add chart`
   (memasang recharts). Alasan: grafik batang bertumpuk yang aksesibel dan
   konsisten dengan tema, tanpa menulis SVG manual.
2. pages/reports/index.tsx:
   - pemilih tahun → grafik pendapatan 12 bulan bertumpuk per metode
     (QRIS/tunai/transfer) + tabel angka di bawahnya (total per bulan dan
     setahun)
   - umur tunggakan: 3 kelompok (0–7, 8–30, 31+ hari) dengan jumlah invoice
     dan nominal, tautan ke reports/outstanding
   - pergerakan pelanggan dengan rentang tanggal from/to: baru, berhenti,
     terisolir
   - tombol unduh CSV (pembayaran from/to, tunggakan, pendapatan tahunan)
     sebagai tautan biasa (bukan kunjungan Inertia)
3. pages/reports/outstanding.tsx: daftar tunggakan paling lama dulu, umur
   (hari), tautan ke invoice dan pelanggan, pencarian.
4. Backend (celah G2): verifikasi bentuk paginator reports/outstanding
   (paginate()->through() menghasilkan paginator datar, bukan
   { data, links, meta }). Samakan dengan konvensi docs/08 dan tambahkan
   status_label + customer_status_label; test + docs/08.

Test (perluas ReportControllerTest): komponen ada; bentuk pagination
outstanding sesuai konvensi; kasir/teknisi 403.
Rencana dulu, tunggu persetujuan.
```

## Kriteria selesai
- Grafik terbaca di mode terang/gelap dan di HP (tabel angka tetap tersedia)
- Unduhan CSV berjalan dari tombol
- Bentuk props outstanding sesuai konvensi pagination
- Test hijau

## Commit
`feat(report): halaman laporan dan tunggakan`
