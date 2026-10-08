# Tahap F08 — Pengguna & Pengaturan

**Tujuan:** admin mengelola akun pengguna, profil usaha (termasuk logo),
aturan tagihan, dan template pesan WhatsApp.

## Prompt

```
Tahap F08: pengguna dan pengaturan. Baca CLAUDE.md (aturan frontend),
docs/08 bagian `users/index`, `settings/business`, `settings/billing`,
`settings/message-templates`, docs/04-aturan-bisnis.md (pengaturan default,
pesan WhatsApp), dan PROGRESS.md keputusan H6–H7.

Kerjakan:
1. pages/users/index.tsx:
   - FilterBar (pencarian, role); kolom nama, email, role, status aktif
   - modal tambah/ubah (password kosong = tidak diubah), nonaktifkan,
     aktifkan, hapus dengan konfirmasi
   - aksi terhadap diri sendiri disembunyikan (auth.user.id);
     errors.user / errors.role tampil sebagai pesan
   - Backend (celah G4): `can_delete` per user (tanpa jejak audit); tombol
     Hapus hanya jika true; test + docs/08
2. Navigasi pengaturan: layouts/settings mendapat grup "Usaha" (Profil
   usaha, Aturan tagihan, Template WA) khusus settings.manage, di samping
   Profil, Keamanan, Tampilan.
3. pages/settings/business.tsx + logo usaha (celah G7, utang teknis):
   - backend: POST /settings/business/logo (gambar png/jpg/webp ≤ 1 MB,
     disk public, simpan path di settings) dan DELETE; prop logo_url;
     logo dipakai di layout Blade halaman publik; activity log
   - form nama, alamat, WA (dinormalisasi backend); placeholder nama =
     default_name
4. pages/settings/billing.tsx: setiap aturan dengan penjelasan singkat
   (contoh: "Toleransi 3 hari: pelanggan diisolir 3 hari setelah jatuh
   tempo"), batas dari `limits`, switch untuk boolean.
5. pages/settings/message-templates.tsx: satu kartu per template, textarea
   dengan penghitung karakter (max_length), chip placeholder (klik untuk
   menyisipkan di posisi kursor), pratinjau dengan data contoh, switch aktif.

Test: komponen semua halaman ada; can_delete; upload logo (sukses, bukan
gambar, terlalu besar, non-admin 403, hapus logo).
Rencana dulu, tunggu persetujuan.
```

## Kriteria selesai
- Admin bisa mengelola user tanpa bisa mengunci dirinya sendiri dari UI
- Logo usaha tersimpan dan tampil di halaman publik
- Test hijau

## Commit
`feat(settings): halaman pengguna, pengaturan usaha, tagihan, dan template`
