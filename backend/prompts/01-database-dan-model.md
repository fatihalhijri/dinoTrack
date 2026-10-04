# Tahap 01 — Database, Model & Enum

**Tujuan:** seluruh skema database, model dengan relasi, enum status,
factory, dan seeder data contoh.

> Pastikan keputusan single-tenant / multi-tenant di `docs/02-arsitektur.md`
> sudah final sebelum tahap ini.

## Prompt

```
Tahap 01: database dan model. Baca docs/03-database.md, docs/04-aturan-bisnis.md,
dan docs/06-standar-kode.md.

Kerjakan:
1. Tinjau rancangan di docs/03-database.md. Jika ada masalah (index kurang,
   relasi janggal, kolom yang perlu ditambah), sampaikan usulan dulu sebelum
   membuat migration. Jika usulan saya setujui, perbarui dokumennya.
2. Buat Enum: CustomerStatus, InvoiceStatus, PaymentMethod, PaymentChargeStatus,
   MessageStatus, lengkap dengan method label() Bahasa Indonesia.
3. Buat migration untuk semua tabel dengan foreign key, index, dan unique
   constraint sesuai dokumen.
4. Buat model dengan $fillable, casts() (enum, date, encrypted), relasi,
   dan scope yang jelas dibutuhkan (active, overdue, dll).
5. Buat factory dengan state yang berguna (Customer: active, isolated,
   pending, terminated; Invoice: paid, overdue, cancelled).
6. Buat seeder: 1 admin, 1 kasir, 1 teknisi, 4 paket, 1 router, 30 pelanggan
   dengan subscription, dan template pesan WhatsApp default.
7. Test: relasi model, cast enum, password router terenkripsi di database,
   unique constraint invoice per periode.

Rencana dulu, tunggu persetujuan. Akhiri dengan migrate:fresh --seed dan
seluruh test.
```

## Kriteria selesai
- `php artisan migrate:fresh --seed` berhasil
- Test model hijau
- `docs/03-database.md` sesuai dengan migration

## Commit
`feat(db): skema database, model, enum, factory, dan seeder`
