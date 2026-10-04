# Progress Pengembangan Backend

Status: ⬜ belum · 🟨 sedang dikerjakan · ✅ selesai

| Tahap | Nama | Status | Tanggal | Catatan |
|---|---|---|---|---|
| 00 | Setup proyek & tooling | ⬜ | | |
| 01 | Database, model & enum | ⬜ | | |
| 02 | Role, permission & otorisasi | ⬜ | | |
| 03 | Master data (paket, router, pelanggan) | ⬜ | | |
| 04 | Tagihan otomatis | ⬜ | | |
| 05 | Pembayaran manual & QRIS | ⬜ | | |
| 06 | Mikrotik: isolir & aktivasi | ⬜ | | |
| 07 | Notifikasi WhatsApp | ⬜ | | |
| 08 | Laporan & metrik dashboard | ⬜ | | |
| 09 | Controller & route | ⬜ | | |
| 10 | Review keamanan | ⬜ | | |
| 11 | Kesiapan deploy | ⬜ | | |

## Keputusan penting

Catat di sini setiap keputusan yang menyimpang dari `docs/` beserta alasannya.

| Tanggal | Keputusan | Alasan |
|---|---|---|
| 2026-10-04 | Database dev dan test memakai MySQL (bukan SQLite); test memakai database `dinotrack_testing` | `pdo_sqlite` tidak terpasang; sesuai stack MySQL 8 |
| 2026-10-04 | Stack dinaikkan ke Laravel 13 / PHP 8.4; timezone `Asia/Jakarta`, locale `id` | Sesuai versi terpasang dan CLAUDE.md |

## Utang teknis

Hal yang sengaja ditunda untuk dikerjakan nanti.

- Fitur Teams bawaan starter kit (model, controller, middleware, test) masih ada, padahal arsitektur single-tenant. Putuskan di Tahap 00: dibuang atau dipertahankan.
