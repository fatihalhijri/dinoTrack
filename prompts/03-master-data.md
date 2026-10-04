# Tahap 03 — Master Data: Paket, Router, Pelanggan

**Tujuan:** logika bisnis untuk mengelola paket, router, pelanggan, dan
langganan, lengkap dengan validasi dan test. Belum ada halaman React.

## Prompt

```
Tahap 03: master data. Baca docs/01-spesifikasi-produk.md (modul 2–5),
docs/04-aturan-bisnis.md (status pelanggan, ganti paket, berhenti), dan
docs/06-standar-kode.md.

Kerjakan dalam bentuk Actions + Form Requests + test (tanpa controller dan
halaman dulu):
1. Paket: CreatePackage, UpdatePackage, DeactivatePackage (tolak hapus jika
   masih dipakai).
2. Router: CreateRouter, UpdateRouter, TestRouterConnection (memakai
   NetworkController).
3. Pelanggan: CreateCustomer (generate kode PLG-000001 berurutan aman dari
   race condition, normalisasi nomor WA ke 62xxx, buat subscription,
   billing_day = tanggal pasang dibatasi maks 28), UpdateCustomer,
   ChangeCustomerPackage (isi next_package_id), TerminateCustomer
   (memanggil disableSecret lewat job).
4. Helper PhoneNumber::normalize() dengan unit test berbagai format.
5. Form Request untuk tiap aksi dengan pesan validasi Bahasa Indonesia.
6. Catat activity log untuk setiap aksi.
7. Test jalur sukses dan gagal untuk semua aksi.

Rencana dulu, tunggu persetujuan.
```

## Kriteria selesai
- Semua Actions punya test sukses dan gagal
- Kode pelanggan unik meski dibuat bersamaan

## Commit
`feat(master): aksi paket, router, pelanggan, dan langganan`
