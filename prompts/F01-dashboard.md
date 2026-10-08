# Tahap F01 — Dashboard

**Tujuan:** halaman pertama setelah login yang menampilkan kondisi usaha
dalam sekali lihat, sesuai role.

## Prompt

```
Tahap F01: dashboard. Baca CLAUDE.md (aturan frontend), docs/08 bagian
`dashboard`, dan app/Http/Controllers/DashboardController.php.

Kerjakan:
1. pages/dashboard.tsx memakai StatCard dari F00:
   - summary (reports.view): pendapatan bulan ini (+ jumlah pembayaran),
     tunggakan (nominal + jumlah invoice), jatuh tempo 7 hari ke depan,
     pelanggan aktif/terisolir/pending
   - blok "Perlu perhatian": pembayaran needs_review (nominal + jumlah →
     /payments?review_status=needs_review) dan pelanggan dengan galat router
     (→ /customers?network_error=1); disembunyikan jika semuanya 0
   - keterangan "Diperbarui <generated_at>" (cache 5 menit)
2. Role tanpa reports.view (teknisi, dan kasir sesuai permission) melihat
   customer_counts per status, masing-masing menautkan ke daftar pelanggan
   terfilter.
3. Grid responsif 1/2/4 kolom; angka besar dan mudah dibaca.
4. Breadcrumb dan judul "Dashboard".

Test (perluas test dashboard yang ada): komponen 'dashboard' ada
(component('dashboard', true)); admin menerima summary; teknisi
summary = null dan customer_counts ada.
Rencana dulu, tunggu persetujuan.
```

## Kriteria selesai
- Tampilan admin, kasir, dan teknisi sesuai props masing-masing
- Semua tautan kartu menuju daftar terfilter yang benar
- Test dashboard hijau

## Commit
`feat(dashboard): halaman dashboard ringkasan`
