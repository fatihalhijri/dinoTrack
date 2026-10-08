# Tahap F03 — Pelanggan: Daftar & Form

**Tujuan:** daftar pelanggan dan form tambah/ubah yang nyaman dipakai teknisi
di HP saat pemasangan.

## Prompt

```
Tahap F03: daftar dan form pelanggan. Baca CLAUDE.md (aturan frontend),
docs/08 bagian `customers/index`, `customers/create`, `customers/edit`,
docs/04-aturan-bisnis.md (billing_day, status pelanggan), dan Form Request
di app/Http/Requests/Customers.

Kerjakan:
1. pages/customers/index.tsx:
   - FilterBar: pencarian (kode, nama, WA, PPPoE), status, paket, router,
     "galat router" (network_error)
   - kolom kode, nama, WA, paket, router, status (StatusBadge + alasan
     isolir), ikon peringatan jika network_error_at terisi
   - tampilan kartu di HP; tombol "Tambah pelanggan" (customers.create)
2. pages/customers/create.tsx (mobile-first, satu kolom di HP):
   - data diri: nama, WA (inputMode="tel", contoh 08xx), alamat, ODP
   - lokasi: latitude/longitude + tombol "Pakai lokasi saya" (Geolocation
     API, tangani izin ditolak)
   - koneksi: router, username PPPoE, paket (nama · kecepatan · harga),
     billing_day 1–31 dengan keterangan "29–31 dibulatkan ke 28"
   - catatan; submit → redirect ke detail (sudah dari backend)
3. pages/customers/edit.tsx: field sama tanpa paket; router, username
   PPPoE, dan billing_day dinonaktifkan jika status bukan pending (dengan
   keterangan alasannya); error per field dari Action.

Test (perluas CustomerControllerTest): komponen ketiga halaman ada; teknisi
bisa membuka create, kasir 403; filters dikembalikan sesuai query.
Rencana dulu, tunggu persetujuan.
```

## Kriteria selesai
- Teknisi bisa mendaftarkan pelanggan dari HP (360 px) tanpa scroll horizontal
- Error validasi tampil di field yang tepat
- Test hijau

## Commit
`feat(customer): halaman daftar, tambah, dan ubah pelanggan`
