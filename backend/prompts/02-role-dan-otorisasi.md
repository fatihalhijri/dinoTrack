# Tahap 02 — Role, Permission & Otorisasi

**Tujuan:** role admin, kasir, teknisi dengan permission granular dan Policy
untuk setiap model utama.

## Prompt

```
Tahap 02: role dan otorisasi. Baca docs/01-spesifikasi-produk.md bagian Role.

Kerjakan:
1. Definisikan daftar permission (contoh: customers.view, customers.create,
   invoices.cancel, payments.record, routers.manage, settings.manage,
   reports.view, users.manage). Tampilkan matriks role × permission untuk
   saya setujui dulu.
2. Seeder RolePermissionSeeder yang idempotent (aman dijalankan ulang).
3. Policy untuk Customer, Package, Router, Invoice, Payment, User.
4. Admin melewati semua pengecekan lewat Gate::before.
5. Bagikan permission user yang login ke Inertia (HandleInertiaRequests)
   agar frontend nanti bisa menyembunyikan tombol.
6. Test untuk setiap policy: role yang boleh dan yang tidak boleh.
```

## Kriteria selesai
- Matriks permission disetujui dan tercatat di `docs/01-spesifikasi-produk.md`
- Semua test policy hijau

## Commit
`feat(auth): role, permission, dan policy`
