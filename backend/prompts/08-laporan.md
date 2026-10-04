# Tahap 08 — Laporan & Metrik Dashboard

**Tujuan:** query laporan yang akurat dan efisien, siap dipakai halaman
dashboard di fase frontend.

## Prompt

```
Tahap 08: laporan. Baca docs/01-spesifikasi-produk.md modul Laporan.

Kerjakan:
1. ReportService dengan method:
   - revenueByMonth(year): total per bulan, dipisah per metode pembayaran
   - outstandingInvoices(): daftar tunggakan + umur (0–7, 8–30, >30 hari)
   - customerMovement(from, to): baru, berhenti, terisolir
   - dashboardSummary(): pendapatan bulan ini, total tunggakan, jumlah
     pelanggan aktif/isolir, tagihan jatuh tempo minggu ini
2. Query efisien (agregasi di database, tanpa N+1). Tambahkan index jika perlu
   lewat migration baru, jelaskan alasannya.
3. Ekspor CSV yang di-stream (aman untuk data besar).
4. Cache dashboardSummary singkat (misalnya 5 menit), dihapus saat ada
   pembayaran baru.
5. Test dengan data factory yang angkanya bisa dihitung manual.
```

## Commit
`feat(report): laporan pendapatan, tunggakan, dan ringkasan dashboard`
