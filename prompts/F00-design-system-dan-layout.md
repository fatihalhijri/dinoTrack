# Tahap F00 — Design System & Layout

**Tujuan:** fondasi visual dan komponen bersama dinoTrack (tema biru tua laut,
sidebar per permission, tipe data, format Rupiah/tanggal) agar tahap
berikutnya tinggal menyusun halaman.

## Prompt

```
Tahap F00: design system dan layout. Baca CLAUDE.md (aturan frontend),
docs/08-kontrak-halaman.md (konvensi umum, props bersama, bentuk resource),
dan app/Http/Resources/*. Periksa resources/js/components, layouts, hooks,
types, dan resources/css/app.css yang sudah ada.

Kerjakan:
1. Tema di resources/css/app.css: token oklch biru tua laut untuk --primary,
   --ring, --sidebar* (sidebar navy gelap dengan teks terang), --chart-1..5
   yang selaras, mode terang dan gelap, kontras teks minimal AA. Tentukan
   font (tetap Instrument Sans atau ganti Inter) dan sebutkan alasannya.
2. Branding: APP_NAME=dinoTrack di .env (tunjukkan perubahannya dulu),
   ikon logo dinoTrack (SVG sederhana) di app-logo-icon.tsx, favicon, dan
   judul tab "%s · dinoTrack". Hapus tautan footer starter kit
   (Repository/Documentation).
3. Sidebar per permission dengan grup: Utama (Dashboard), Operasional
   (Pelanggan, Tagihan, Pembayaran), Master (Paket, Router), Laporan, Admin
   (Pengguna, Pengaturan). Grup kosong disembunyikan. Di HP memakai sheet
   bawaan sidebar shadcn. Route belum punya halaman tetap boleh ditautkan.
4. Tipe:
   - types/models.ts: semua bentuk resource docs/08 dengan tipe field persis
     dari API Resource (nullability lengkap, enum sebagai union string)
   - types/pagination.ts: Paginated<T> sesuai bentuk ResourceCollection
     paginated ({ data, links, meta })
   - types/permissions.ts: union 19 permission dari App\Enums\Permission
   - global.d.ts: shared props bertipe tegas, termasuk flash.toast
5. Props bersama auth.user: bentuk eksplisit { id, name, email, role,
   role_label, email_verified_at, two_factor_enabled } lewat UserResource
   atau array eksplisit di HandleInertiaRequests (menutup audit R-3 dan
   celah G3), dengan test.
6. lib/format.ts: formatRupiah (Rp150.000), formatDate (6 Okt 2026),
   formatDateTime (6 Okt 2026 14.30, Asia/Jakarta), formatPeriod,
   formatMonth, formatPhone (62812… → 0812-…). Tanggal YYYY-MM-DD diparse
   sebagai tanggal lokal, bukan UTC (celah G12). hooks/use-can.ts.
7. Komponen bersama di resources/js/components:
   - PageHeader (judul, deskripsi, slot aksi)
   - DataTable (definisi kolom + render kartu untuk lebar < md)
   - Pagination (dari links/meta)
   - FilterBar (pencarian ber-debounce, per_page, preserveState,
     preserveScroll)
   - StatusBadge (peta warna status pelanggan, invoice, charge, review,
     dan pesan)
   - Money, StatCard, EmptyState
   - ConfirmDialog (opsional dengan field alasan min 5 karakter, memakai
     useForm)
   - FormErrorAlert (error non-field: status, customer, package, router,
     invoice, user, role)
8. Komponen shadcn lewat CLI: table, textarea, switch, alert-dialog, tabs,
   radio-group, scroll-area. Sebutkan paket @radix-ui yang ikut terpasang.
9. docs/08: tambahkan tipe field lengkap (G1), bagian "Permission" berisi
   19 permission dan matriks role (G11), serta aturan parsing tanggal (G12).

Belum membuat halaman fitur; komponen dipakai mulai F01.
Rencana dulu, tunggu persetujuan.
```

## Kriteria selesai
- Tema terang/gelap konsisten dan sidebar sesuai role (admin, kasir, teknisi)
- Layout layak di lebar 360 px dan 1366 px
- Test props bersama (`auth.user` tanpa field sensitif, `auth.permissions` per role) hijau
- `npm run types:check`, `npm run check`, `npm run build` bersih

## Commit
`feat(ui): design system, tema dinoTrack, dan layout sidebar`
