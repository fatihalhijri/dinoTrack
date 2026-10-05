# 04 — Aturan Bisnis

> **Sesuaikan dokumen ini dengan kebijakan usahamu sebelum Tahap 04.**
> Nilai default di bawah disimpan di tabel `settings` agar bisa diubah admin.

## Pengaturan default

| Key | Default | Arti |
|---|---|---|
| `billing.due_days` | 7 | Jatuh tempo = tanggal terbit + N hari |
| `billing.grace_days` | 3 | Toleransi setelah jatuh tempo sebelum isolir |
| `billing.reminder_days_before` | 3 | Pengingat dikirim H-N jatuh tempo |
| `billing.penalty_amount` | 0 | **Tidak dipakai di v1.** Denda belum didukung; `invoices.penalty` selalu 0 |
| `billing.prorate_first_month` | true | Periode pertama dihitung prorata; jika false, tagihan pertama = harga paket penuh |
| `billing.auto_isolate` | true | Isolir otomatis aktif |
| `billing.auto_activate` | true | Aktivasi otomatis setelah lunas (hanya untuk isolir otomatis) |

## Siklus tagihan

1. Setiap langganan punya `billing_day` (1–28) yang diisi saat mendaftar dan
   **bebas dari tanggal pasang**. Input 29–31 dibulatkan ke 28 agar ada di
   setiap bulan.
2. Tagihan periode berikutnya **terbit pada `billing_day`** dan menagih bulan
   yang akan dipakai (bayar di depan).
3. Periode: `billing_day` bulan ini s.d. sehari sebelum `billing_day` bulan depan.
4. Satu subscription hanya boleh punya **satu** invoice per periode.
5. Pelanggan berstatus `terminated` atau `pending` tidak ditagih.
6. Generator tagihan bersifat **catch-up**: menagih semua periode yang sudah
   dimulai tetapi belum punya invoice, bukan hanya yang jatuh tagih hari ini.
   Aman dijalankan ulang (dijaga unique `subscription_id` + `period_start`)
   sehingga server yang mati sehari tidak menghilangkan tagihan.
7. Nomor invoice `INV/YYYY/MM/NNNNN` (5 digit); urutan di-reset tiap bulan.
8. Diskon belum ada di v1: `invoices.discount` selalu 0.

## Aktivasi pelanggan baru

- Teknisi (atau admin) mendaftarkan pelanggan: status `pending`, belum ditagih.
- **Admin atau kasir** menandai pelanggan "terpasang". Teknisi tidak boleh
  melakukan ini. Pada saat itu, dalam satu transaksi:
  1. status menjadi `active` dan `installed_at` diisi;
  2. secret PPPoE diaktifkan di router;
  3. tagihan pertama langsung terbit (prorata jika berlaku), tanpa menunggu
     scheduler.
- Internet langsung aktif tanpa menunggu tagihan pertama dibayar; tagihan
  mengikuti jatuh tempo dan toleransi seperti biasa.

## Prorata periode pertama

Periode pertama berjalan dari tanggal pasang sampai sehari sebelum
`billing_day` berikutnya. Jika tanggal pasang sama dengan `billing_day`,
periode pertama adalah periode penuh dan tidak ada prorata.

Jika `billing.prorate_first_month = true`:

```
tagihan pertama = harga_paket × (jumlah hari dari tanggal pasang
                  s.d. akhir periode pertama) ÷ jumlah hari periode penuh
```

Dibulatkan **ke atas ke kelipatan Rp100**. Contoh: paket Rp150.000, periode
penuh 30 hari, dipakai 12 hari → 150.000 × 12 ÷ 30 = 60.000.

Jika `false`, tagihan pertama sebesar harga paket penuh.

## Status invoice

```
unpaid ──(lewat due_at)──> overdue
unpaid / overdue ──(lunas)──> paid
unpaid / overdue ──(dibatalkan admin)──> cancelled
```

- `paid` dan `cancelled` adalah status akhir, tidak bisa berubah lagi.
- Pembayaran harus **sama dengan** total invoice. Pembayaran sebagian, bayar
  di muka beberapa bulan, kelebihan bayar, dan saldo deposit tidak didukung di v1.
- **Satu pembayaran = satu invoice.** Pelanggan yang menunggak beberapa bulan
  membayar tiap invoice sendiri-sendiri.
- Denda tidak dipakai di v1: `invoices.penalty` selalu 0 dan tidak ada logika
  denda saat invoice menjadi `overdue`.
- Biaya gateway QRIS ditanggung ISP. Total invoice = harga paket, tanpa biaya
  admin untuk pelanggan, dan fee tidak dicatat.

## Link tagihan dan charge QRIS

- Link tagihan (signed URL) tidak kedaluwarsa selama invoice belum `paid`
  atau `cancelled`.
- Charge QRIS baru dibuat hanya jika charge sebelumnya sudah `expired` atau
  `failed`. Membuka halaman berulang kali memakai ulang charge `pending`
  yang masih berlaku.

## Pembayaran anomali

Pembayaran yang masuk tetapi tidak bisa diterapkan secara normal: dibayar
dua kali (misalnya tunai lalu QRIS), charge kedaluwarsa tetapi tetap dibayar,
invoice sudah `paid` atau `cancelled`, atau nominal tidak cocok.

- Uang tetap dicatat sebagai `payments` dengan `review_status = needs_review`
  dan `review_note` berisi alasannya.
- Status invoice **tidak berubah** dan tidak ada aktivasi otomatis.
- Admin meninjau dan menandai `resolved`. Pengembalian dana dilakukan manual
  di luar sistem.
- Webhook tetap merespons 200 dan notifikasi mentah tetap tersimpan di
  `payment_notifications`.

## Status pelanggan

```
pending ──(aktivasi pertama)──> active
active ──(tunggakan lewat toleransi)──> isolated
isolated ──(semua tagihan lunas)──> active
active / isolated ──(berhenti)──> terminated
terminated ──(berlangganan lagi)──> pending
```

- `pending → active` dilakukan admin atau kasir (lihat "Aktivasi pelanggan baru").
- Router, username PPPoE, dan `billing_day` hanya bisa diubah selama pelanggan
  `pending`. Setelah terpasang, perubahan itu berarti memindahkan secret di
  router dan menggeser periode tagihan, sehingga ditolak. Data identitas
  (nama, nomor WA, alamat, ODP, koordinat, catatan) selalu bisa diubah admin.
- Pelanggan baru dan ganti paket hanya boleh memilih paket dan router yang
  aktif. Paket yang dinonaktifkan tetap berlaku untuk subscription yang sudah
  memakainya.
- **Isolir otomatis**: pelanggan `active` yang punya invoice `overdue` dengan
  `due_at + grace_days < hari ini`. Mengisi `isolation_reason = overdue`.
  `grace_days` default 3 dan bisa diubah admin menjadi 0 untuk isolir tepat
  setelah jatuh tempo.
- **Aktivasi otomatis**: setelah pembayaran, jika pelanggan `isolated` dengan
  `isolation_reason = overdue` dan **tidak ada lagi** invoice `unpaid`/`overdue`
  yang lewat toleransi.
- **Isolir manual** oleh admin mengisi `isolation_reason = manual`. Isolir
  manual **tidak** dibuka oleh pembayaran; hanya admin yang bisa membukanya.
- Isolir/aktivasi manual oleh admin wajib menyertakan alasan dan tercatat di log.
- Jika perintah ke router gagal, status di database **tidak berubah**; job
  dicoba ulang. Setelah semua percobaan gagal, admin diberi tanda (log + flag).

## Ganti paket

- Diatur lewat `subscriptions.next_package_id`.
- Berlaku saat tagihan periode berikutnya dibuat; profil PPPoE diubah saat itu.
- Selalu mulai periode berikutnya, tanpa prorata dan tanpa tagihan selisih.
- Jika pelanggan sedang `isolated` saat pergantian, hanya `subscriptions` yang
  berubah. Profil router baru diganti saat pelanggan diaktifkan, sehingga
  profil isolir tidak tertimpa.
- Pelanggan `pending` belum pernah ditagih, sehingga paketnya diganti langsung
  (`package_id` dan harga terkunci ikut berganti), bukan lewat `next_package_id`.
- Rencana ganti paket bisa dibatalkan (`next_package_id` dikosongkan). Memilih
  paket yang sama dengan paket sekarang ditolak; pelanggan `terminated` tidak
  bisa ganti paket.

## Berhenti berlangganan

- Hanya pelanggan `active` atau `isolated` yang bisa diberhentikan. Status,
  `terminated_at`, dan subscription (`ends_at` = hari berhenti, rencana ganti
  paket dibatalkan) langsung berubah, meskipun router sedang tidak bisa
  dijangkau, agar tagihan berhenti. Alasan berhenti opsional dan dicatat di log.
- Pelanggan `terminated` tidak ditagih lagi; invoice `unpaid` yang tersisa
  tetap ada untuk ditagih.
- Secret PPPoE di router dinonaktifkan (bukan dihapus).
- Pelanggan `terminated` bisa diaktifkan kembali: status kembali ke `pending`
  dengan subscription baru, kode pelanggan dan riwayat tetap. Lalu mengikuti
  alur "Aktivasi pelanggan baru".
- Invoice sisa tetap bisa dibayar lewat link, tetapi pembayarannya tidak
  mengaktifkan layanan.
- Secret dinonaktifkan lewat job yang dicoba ulang. Saat job berjalan, status
  dicek ulang: jika pelanggan sudah diaktifkan kembali, secret tidak disentuh.
- Pelanggan yang sudah punya invoice tidak boleh dihapus (soft delete hanya
  untuk salah input); gunakan `terminated`.
- Soft delete hanya untuk pelanggan `pending` (salah input atau batal pasang)
  yang belum punya invoice; subscription-nya ikut diakhiri. Pelanggan yang
  pernah terpasang harus diberhentikan agar secret di router dinonaktifkan.

## Hapus master data

- Paket yang pernah dirujuk subscription (aktif, riwayat, atau
  `next_package_id`) tidak bisa dihapus; nonaktifkan sebagai gantinya.
- Router yang masih punya pelanggan (termasuk yang di-soft-delete) tidak bisa
  dihapus; nonaktifkan sebagai gantinya.

## Pesan WhatsApp

| Kejadian | Template | Waktu |
|---|---|---|
| Tagihan terbit | `invoice_issued` | saat invoice dibuat |
| Pengingat | `reminder_before_due` | 09:00 pada H-`reminder_days_before` |
| Jatuh tempo | `reminder_due` | 09:00 pada hari `due_at` |
| Diisolir | `isolated` | setelah isolir berhasil |
| Pembayaran diterima | `payment_received` | setelah pembayaran tercatat |

Pesan tidak dikirim ganda untuk kejadian yang sama pada invoice yang sama
(cek `message_logs`).
