# Tahap F02 — Paket & Router

**Tujuan:** halaman master data paket internet dan router Mikrotik, dengan
tambah/ubah lewat modal.

## Prompt

```
Tahap F02: paket dan router. Baca CLAUDE.md (aturan frontend), docs/08
bagian `packages/index` dan `routers/index`, serta Form Request terkait di
app/Http/Requests/Packages dan app/Http/Requests/Routers.

Kerjakan:
1. pages/packages/index.tsx:
   - DataTable + FilterBar (search, aktif/nonaktif); kolom nama, kecepatan,
     harga, profil Mikrotik, jumlah langganan, status
   - modal tambah/ubah (packages.manage): input harga Rupiah dengan
     pemisah ribuan, dikirim sebagai integer
   - aktifkan/nonaktifkan dan hapus dengan ConfirmDialog; errors.package
     tampil lewat FormErrorAlert/toast
   - kasir/teknisi hanya melihat (tombol disembunyikan)
2. pages/routers/index.tsx (routers.manage):
   - kolom nama, host:port, SSL, profil isolir, jumlah pelanggan, aktif,
     terakhir terhubung
   - modal tambah/ubah: password wajib saat tambah, kosong = tidak diubah
     saat ubah (beri keterangan)
   - tombol "Tes koneksi" dengan status loading; hasil lewat flash toast
   - hapus dengan konfirmasi (errors.router)
3. Tampilan kartu di HP untuk kedua halaman.

Test (perluas PackageControllerTest dan RouterControllerTest): komponen ada,
props per role, password router tidak ada di props, teknisi 403 di /routers.
Rencana dulu, tunggu persetujuan.
```

## Kriteria selesai
- CRUD paket dan router berjalan dari UI, termasuk error aturan bisnis
- Tombol tersembunyi untuk role tanpa permission
- Test hijau

## Commit
`feat(master): halaman paket dan router`
