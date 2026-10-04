# Tahap 00 — Setup Proyek & Tooling

**Tujuan:** fondasi proyek siap: package inti, kualitas kode, konfigurasi
dasar, dan kerangka integrasi (interface + fake) sebelum fitur apa pun.

**Prasyarat:** proyek Laravel dibuat dengan starter kit React + Pest, kit ini
sudah disalin, `.env` sudah diisi koneksi MySQL.

## Prompt

```
Kita mulai Tahap 00: setup proyek. Baca CLAUDE.md, docs/02-arsitektur.md,
docs/05-integrasi.md, dan docs/06-standar-kode.md.

Kerjakan:
1. Konfigurasi aplikasi: timezone Asia/Jakarta, locale id, faker_locale id_ID.
2. Pasang dan konfigurasi:
   - spatie/laravel-permission (publish config + migration)
   - larastan/larastan (phpstan.neon level 6, path app/)
   - laravel/pint (pint.json preset laravel + declare_strict_types)
   - evilfreelancer/routeros-api-php
   - midtrans/midtrans-php HANYA jika memang dibutuhkan; jika HTTP client
     Laravel cukup, jelaskan dan jangan pasang.
3. Buat struktur folder sesuai docs/02-arsitektur.md.
4. Buat interface di app/Contracts: PaymentGateway, NetworkController,
   MessageSender, beserta DTO hasil yang dibutuhkan.
5. Buat fake untuk masing-masing di tests/Fakes yang merekam panggilan dan
   bisa diatur untuk gagal.
6. Buat implementasi kosong (stub yang melempar exception "belum
   diimplementasikan") di app/Services dan bind di service provider.
7. Tambahkan konfigurasi di config/services.php dan variabel di .env.example
   untuk Midtrans, WhatsApp, dan queue.
8. Buat helper app/Support/Money.php (format rupiah) dengan unit test.
9. Tambahkan script composer: "test", "lint" (pint --test), "analyse".

Susun rencana dulu dan tunggu persetujuan saya. Setelah selesai, jalankan
pint, phpstan, dan test, lalu laporkan hasilnya.
```

## Kriteria selesai
- `composer test`, `composer lint`, `composer analyse` berjalan tanpa error
- Interface dan fake tersedia dan bisa di-resolve dari container
- `.env.example` lengkap

## Commit
`chore: setup tooling, struktur folder, dan kontrak integrasi`
