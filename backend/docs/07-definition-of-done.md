# 07 — Definition of Done

Sebuah tahap atau fitur dianggap **selesai** hanya jika semua poin ini terpenuhi.

## Kode
- [ ] Mengikuti `docs/02-arsitektur.md` dan `docs/06-standar-kode.md`
- [ ] Tidak ada kredensial atau nilai rahasia di kode
- [ ] Tidak ada `dd()`, `dump()`, `var_dump()`, atau kode mati yang tertinggal
- [ ] Migration baru (bukan mengubah yang lama) jika skema berubah
- [ ] `.env.example` diperbarui jika ada variabel baru

## Kualitas
- [ ] `./vendor/bin/pint` bersih
- [ ] `./vendor/bin/phpstan analyse` tanpa error
- [ ] `php artisan test` semua hijau
- [ ] Ada test untuk jalur sukses **dan** jalur gagal / kasus tepi
- [ ] Integrasi eksternal di-fake dalam test

## Keamanan
- [ ] Input divalidasi (Form Request)
- [ ] Akses dibatasi sesuai role (Policy / permission) dan ada test-nya
- [ ] Data sensitif terenkripsi atau tidak dikembalikan ke frontend

## Dokumentasi
- [ ] `docs/` diperbarui jika ada keputusan desain baru
- [ ] `PROGRESS.md` dicentang dan diberi catatan singkat
- [ ] Commit dengan pesan Conventional Commits
