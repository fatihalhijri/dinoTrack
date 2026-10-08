# Tahap F09 — Poles Akhir

**Tujuan:** aplikasi terasa utuh: halaman error, pengalihan beranda, audit
tampilan di HP/laptop, dan penutupan utang teknis frontend.

## Prompt

```
Tahap F09: poles akhir. Baca CLAUDE.md (aturan frontend), docs/08,
docs/09-audit-keamanan.md (R-3, R-5, R-11), dan bagian "Utang teknis" di
PROGRESS.md.

Kerjakan:
1. Halaman error Inertia v3 (celah G8): pages/errors/error.tsx dengan prop
   status untuk 403, 404, 419, 500, 503 dalam Bahasa Indonesia dan tombol
   kembali; 419 memuat ulang halaman. Cek search-docs untuk penanganan
   exception Inertia v3.
2. Beranda (celah G9): `/` diarahkan ke dashboard (sudah login) atau login;
   halaman welcome dihapus beserta test terkait (minta persetujuan).
3. Nyalakan config inertia.testing.ensure_pages_exist = true (utang teknis)
   dan jalankan wayfinder:generate; semua test tetap hijau.
4. Audit UX setiap halaman di 360 px dan 1366 px, mode terang dan gelap:
   empty state, skeleton/loading, fokus keyboard, label aria, judul tab,
   konsistensi format Rupiah/tanggal. Laporkan temuan dalam tabel sebelum
   memperbaiki.
5. Utang teknis frontend: R-11 (naikkan vite-plus ≥ 0.3.3 dan cek
   npm audit), rapikan temuan `npm run check` di markdown, hapus komponen
   starter yang tidak terpakai (placeholder-pattern, app-header bila tidak
   dipakai). Catat sisa R-5 (CSP) bila belum bisa dikerjakan.
6. Perbarui docs/08 menjadi final dan MULAI-DI-SINI.md bila perlu.

Rencana dulu, tunggu persetujuan.
```

## Kriteria selesai
- Semua halaman docs/08 ada dan `ensure_pages_exist` menyala
- Pint, PHPStan, seluruh test, `types:check`, `check`, dan `build` bersih
- Utang teknis frontend tertutup atau tercatat dengan alasan

## Commit
`chore(ui): poles akhir dan halaman error`
