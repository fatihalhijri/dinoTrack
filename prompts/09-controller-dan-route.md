# Tahap 09 — Controller & Route (Lapisan HTTP)

**Tujuan:** semua Actions terhubung ke route dan controller yang siap
dipakai halaman React. Halaman React sendiri dibuat di fase frontend.

## Prompt

```
Tahap 09: controller dan route. Baca docs/02-arsitektur.md dan
docs/06-standar-kode.md.

Kerjakan:
1. Resource controller tipis untuk Package, Router, Customer, Invoice,
   Payment, User, Settings, MessageTemplate, Report. Setiap method:
   Form Request → authorize → Action → Inertia::render / redirect dengan
   flash message Bahasa Indonesia.
2. Gunakan API Resource / data array eksplisit untuk props Inertia. Jangan
   pernah mengirim password router atau field sensitif ke frontend.
3. Pagination, pencarian, dan filter di index (status, paket, router, periode).
4. Kelompokkan route di routes/web.php dengan middleware auth + permission;
   route publik (halaman tagihan, isolir, webhook) dipisah dan diberi
   rate limit.
5. Untuk setiap Inertia::render, nama komponen mengikuti pola
   'customers/index', 'customers/show', dst. Buat daftar semua komponen
   yang dibutuhkan beserta props-nya di docs/08-kontrak-halaman.md sebagai
   kontrak untuk fase frontend.
6. Feature test HTTP: akses per role (403 untuk yang tidak berhak), validasi,
   props tidak mengandung data sensitif (assertInertia).

Rencana dulu, tunggu persetujuan.
```

## Kriteria selesai
- `docs/08-kontrak-halaman.md` berisi daftar halaman dan props lengkap
- Feature test HTTP hijau

## Commit
`feat(http): controller, route, dan kontrak halaman Inertia`
