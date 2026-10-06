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

Batas yang bisa diisi admin (keputusan 2026-10-06): `due_days` 1–31,
`grace_days` 0–30, `reminder_days_before` 0 sampai kurang dari `due_days`
(pengingat pada atau sebelum hari terbit tidak pernah terkirim tepat waktu).
Perubahan berlaku untuk proses berikutnya; invoice yang sudah terbit tidak
dihitung ulang.

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
   - Periode berikutnya dihitung dari `period_end` invoice terakhir
     subscription itu (termasuk invoice `cancelled`). Periode yang dibatalkan
     tidak ditagih ulang otomatis; admin bisa menerbitkannya ulang (lihat
     "Status invoice").
   - Subscription aktif yang **belum punya invoice sama sekali** (data lama
     atau migrasi) hanya ditagih periode berjalan, tidak ditagih mundur sampai
     tanggal mulainya. Pelanggan baru tidak terpengaruh karena tagihan
     pertamanya terbit saat aktivasi.
   - Invoice terbit pada hari generator benar-benar membuatnya (`issued_at`),
     sehingga invoice catch-up tetap mendapat jatuh tempo penuh:
     `due_at = issued_at + due_days`.
7. Nomor invoice `INV/YYYY/MM/NNNNN` (5 digit); urutan di-reset tiap bulan.
   Tahun dan bulan mengikuti `issued_at`.
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
- Tanggal pasang default hari ini. Boleh diisi mundur, tetapi tidak di masa
  depan dan tidak sebelum tanggal pendaftaran pelanggan. Jika tanggal pasang
  mundur melewati `billing_day`, periode berikutnya ikut ditagih saat itu juga.
- Aktivasi ditolak jika router pelanggan nonaktif.
- Status dan tagihan pertama langsung berubah dalam transaksi; pengaktifan
  secret di router dijalankan lewat job yang dicoba ulang (Tahap 06).

## Prorata periode pertama

Periode pertama berjalan dari tanggal pasang sampai sehari sebelum
`billing_day` berikutnya. Jika tanggal pasang sama dengan `billing_day`,
periode pertama adalah periode penuh dan tidak ada prorata.

Jika `billing.prorate_first_month = true`:

```
tagihan pertama = harga_paket × (jumlah hari dari tanggal pasang
                  s.d. akhir periode pertama) ÷ jumlah hari periode penuh
```

"Periode penuh" adalah periode utuh (`billing_day` s.d. sehari sebelum
`billing_day` berikutnya) yang memuat tanggal pasang. Contoh: `billing_day`
10, pasang 25 Oktober → periode pertama 25 Okt–9 Nov (16 hari), periode
penuh 10 Okt–9 Nov (31 hari).

Dibulatkan **ke atas ke kelipatan Rp100**, dan tidak pernah melebihi harga
paket sebulan. Contoh: paket Rp150.000, periode penuh 30 hari, dipakai 12
hari → 150.000 × 12 ÷ 30 = 60.000.

Jika `false`, tagihan pertama sebesar harga paket penuh.

## Status invoice

```
unpaid ──(lewat due_at)──> overdue
unpaid / overdue ──(lunas)──> paid
unpaid / overdue ──(dibatalkan admin)──> cancelled
```

- `paid` dan `cancelled` adalah status akhir, tidak bisa berubah lagi.
- Invoice menjadi `overdue` sehari setelah jatuh tempo (`due_at < hari ini`);
  pada hari `due_at` masih `unpaid` dan masih bisa dibayar. Ditandai oleh
  scheduler harian 01:00.
- Pembatalan wajib menyertakan alasan (minimal 5 karakter) dan dicatat di log.
- **Terbit ulang** (admin): periode yang invoice-nya `cancelled` bisa diterbitkan
  ulang selama belum ada invoice aktif untuk periode itu. Invoice baru terbit
  hari ini (nomor dan jatuh tempo baru), nominal dihitung ulang dari
  subscription saat ini (termasuk prorata periode pertama). Invoice lama tetap
  `cancelled`. Boleh untuk pelanggan `terminated`.
- Jika pembatalan karena salah input paket, admin menyertakan **paket
  koreksi**: paket dan harga subscription langsung dikoreksi untuk periode itu
  dan seterusnya. Ini pengecualian dari aturan "Ganti paket" (yang selalu
  mulai periode berikutnya) dan hanya untuk koreksi salah input.
- Jika invoice yang dibatalkan membuat pelanggan yang diisolir otomatis
  (`isolation_reason = overdue`) tidak lagi punya tunggakan lewat toleransi,
  pelanggan diaktifkan otomatis, sama seperti setelah pembayaran (diterapkan
  di Tahap 06).
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
  atau `cancelled`. Signed URL dibuat tanpa masa berlaku; link invoice
  `paid` menampilkan "Lunas" (bukti bayar) dan link invoice `cancelled`
  menampilkan "Dibatalkan", keduanya tanpa tombol bayar (keputusan 2026-10-06).
  Link yang tidak bertanda tangan atau diubah ditolak (403). Mengganti
  `APP_KEY` membatalkan semua link yang sudah terkirim.
- Halaman tagihan (`GET /tagihan/{id}`, Blade, tanpa session) menampilkan
  rincian invoice, nama dan kode pelanggan (tanpa nomor HP/alamat), dan tombol
  bayar. Charge QRIS **tidak** dibuat saat halaman dibuka (pratinjau link
  WhatsApp ikut membukanya), tetapi saat tombol ditekan. Status diperbarui
  dengan polling ringan tiap 5 detik selama QR tampil (hanya membaca database).
  Dibatasi 120 tampilan/menit per IP dan 10 permintaan QRIS/menit per IP per
  invoice.
- Charge QRIS baru dibuat hanya jika charge sebelumnya sudah `expired` atau
  `failed`. Membuka halaman berulang kali memakai ulang charge `pending`
  yang masih berlaku (sisa waktu lebih dari 1 menit).
- Charge `pending` yang waktunya sudah habis dicek dulu ke gateway: jika
  ternyata sudah dibayar, invoice langsung lunas dan tidak ada charge baru.
- QRIS berlaku 15 menit. Membatalkan invoice atau mencatat pembayaran tunai
  tidak membatalkan charge di gateway; jika QR itu tetap dibayar, pembayarannya
  menjadi anomali.
- Invoice milik pelanggan `terminated` tetap bisa dibayar lewat QRIS.

## Pembayaran manual

- Dicatat kasir atau admin dengan metode `cash` atau `transfer` (QRIS hanya
  dicatat otomatis oleh gateway). Nominal harus sama dengan total, invoice
  harus `unpaid`/`overdue`, catatan opsional.
- Tanggal bayar default saat ini. Boleh mundur (uang diterima kemarin baru
  dicatat hari ini), tetapi tidak di masa depan dan tidak sebelum tanggal
  terbit invoice.

## Pembayaran anomali

Pembayaran QRIS yang masuk tetapi tidak bisa diterapkan secara normal:
invoice sudah `paid` (misalnya dibayar tunai lalu QRIS, atau dua charge
dibayar) atau `cancelled`, atau nominal tidak cocok dengan charge/invoice.

Charge yang sudah `expired`/`failed` di database tetapi tetap dibayar **bukan**
anomali selama invoice masih `unpaid`/`overdue` dan nominalnya cocok:
pembayarannya diterapkan normal (keputusan 2026-10-05; pelanggan sudah membayar
dengan benar dan tidak boleh tetap terisolir).

- Uang tetap dicatat sebagai `payments` dengan `review_status = needs_review`
  dan `review_note` berisi alasannya.
- Status invoice **tidak berubah** dan tidak ada aktivasi otomatis.
- Admin meninjau dan menandai `resolved` dengan catatan (minimal 5 karakter).
  Catatan ditambahkan di bawah alasan anomali di `review_note` (alasan asli
  tetap terbaca) dan dicatat `payment.resolved`. Pengembalian dana dilakukan
  manual di luar sistem.
- Webhook tetap merespons 200 dan notifikasi mentah tetap tersimpan di
  `payment_notifications`.
- Notifikasi refund/chargeback dari gateway hanya dicatat
  (`payment.gateway_reversal`); data pembayaran dan invoice tidak berubah karena
  pengembalian dana di v1 ditangani manual.

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
  `isolation_reason = overdue`, `billing.auto_activate = true`, dan **tidak ada
  lagi** invoice `unpaid`/`overdue` yang lewat toleransi
  (`due_at + grace_days < hari ini`). Tagihan lain yang belum lewat toleransi
  tidak menghalangi aktivasi.
- **Isolir manual** oleh admin mengisi `isolation_reason = manual`. Isolir
  manual **tidak** dibuka oleh pembayaran; hanya admin yang bisa membukanya.
- Isolir/aktivasi manual oleh admin wajib menyertakan alasan (minimal 5
  karakter) dan tercatat di log.
- Pelanggan yang sedang diisolir otomatis boleh diisolir manual: alasannya
  berubah menjadi `manual` tanpa perintah router baru, sehingga pembayaran
  tidak lagi membukanya.
- Admin boleh membuka isolir apa pun, termasuk pelanggan yang masih
  menunggak. Jika tunggakannya masih lewat toleransi, isolir otomatis
  berikutnya (01:15) akan mengisolirnya lagi; admin diberi peringatan saat
  membuka isolir (keputusan 2026-10-05).
- Syarat isolir dan aktivasi otomatis dicek ulang saat job berjalan: pelanggan
  yang membayar sebelum job berjalan tidak diisolir. Jika pembayaran masuk
  tepat saat router sedang mengisolir, status tetap ditulis `isolated` (sesuai
  router) lalu aktivasi langsung dijadwalkan.
- Jika perintah ke router gagal, status di database **tidak berubah**; job
  dicoba ulang. Setelah semua percobaan gagal, admin diberi tanda: log error,
  activity log, dan `customers.network_error_at`.

## Halaman isolir

- `GET /isolir`, tujuan redirect Mikrotik. Tanpa session dan cookie karena
  setiap request HTTP perangkat yang diisolir diarahkan ke sini.
- Menampilkan nama usaha (`business.name`, default `APP_NAME`), pesan isolir,
  cara bayar, dan kontak WA admin (`business.whatsapp`) bila diisi.
- Pelanggan **tidak** dikenali dari IP: lalu lintas pelanggan biasanya
  di-masquerade, IP lewat query string mudah dipalsukan, dan setiap tampilan
  akan memanggil router.
- Cek tagihan memakai kode pelanggan + 4 digit terakhir nomor WhatsApp. Semua
  kegagalan memakai pesan yang sama. Dibatasi 20 cek/menit per IP dan 10
  cek/jam per kode pelanggan; tampilan biasa 120/menit per IP.
- Hasil cek tagihan menampilkan tombol "Bayar" per tagihan yang mengarah ke
  halaman tagihan publik.

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
  Username PPPoE pelanggan yang dihapus boleh dipakai lagi untuk pendaftaran
  yang benar (keputusan 2026-10-06).

## Hapus master data

- Paket yang pernah dirujuk subscription (aktif, riwayat, atau
  `next_package_id`) tidak bisa dihapus; nonaktifkan sebagai gantinya.
- Router yang masih punya pelanggan (termasuk yang di-soft-delete) tidak bisa
  dihapus; nonaktifkan sebagai gantinya.

## Laporan

Disetujui 2026-10-06. Semua tanggal mengikuti zona waktu aplikasi (`Asia/Jakarta`).

- **Pendapatan** = pembayaran normal (`review_status = none`) menurut bulan
  `paid_at` (basis kas), dipisah per metode. Pembayaran yang dicatat mundur
  masuk ke bulan uangnya diterima. Pembayaran anomali (`needs_review` dan
  `resolved`) bukan pendapatan karena dikembalikan manual; dashboard
  menampilkan jumlah dan nominal yang masih `needs_review`.
- **Tunggakan** = invoice `unpaid`/`overdue` dengan `due_at < hari ini`
  (termasuk yang belum sempat ditandai overdue dan invoice pelanggan
  `terminated`). Umur = hari sejak jatuh tempo, dikelompokkan 0–7, 8–30, dan
  lebih dari 30 hari. Invoice yang jatuh tempo hari ini belum tunggakan.
- **Jatuh tempo minggu ini** = invoice `unpaid`/`overdue` dengan `due_at` hari
  ini s.d. H+6 (bukan minggu kalender).
- **Pergerakan pelanggan** dalam rentang tanggal (inklusif), dihitung sebagai
  jumlah pelanggan berbeda dari `activity_logs` karena kolom di `customers`
  ditimpa saat status berubah lagi:
  - *baru*: aktivasi dengan tanggal pasang di dalam rentang (termasuk tanggal
    pasang mundur dan pemasangan ulang setelah berhenti);
  - *berhenti*: diberhentikan di dalam rentang;
  - *terisolir*: diisolir dari status `active` di dalam rentang. Isolir manual
    atas isolir otomatis hanya mengganti alasan dan tidak dihitung.
  Data yang dibuat tanpa Action (misalnya `DemoSeeder`) tidak punya log
  sehingga tidak muncul di laporan ini.
- **Ringkasan dashboard** (pendapatan bulan ini, tunggakan, jumlah pelanggan
  per status, jatuh tempo minggu ini, pembayaran perlu tinjauan, pelanggan
  dengan galat router) di-cache 5 menit dan dihitung ulang setelah ada
  perubahan pembayaran atau invoice. Perubahan status pelanggan menunggu cache
  habis.
- **Ekspor CSV**: rincian pembayaran per rentang tanggal bayar (termasuk
  anomali, dibedakan kolom status tinjauan), daftar tunggakan, dan rekap
  pendapatan 12 bulan. Format UTF-8 dengan BOM dan pemisah `;` (Excel
  berlocale Indonesia); nominal berupa angka rupiah tanpa "Rp".

## Pesan WhatsApp

| Kejadian | Template | Waktu |
|---|---|---|
| Tagihan terbit | `invoice_issued` | saat invoice dibuat |
| Pengingat | `reminder_before_due` | 09:00 pada H-`reminder_days_before` |
| Jatuh tempo | `reminder_due` | 09:00 pada hari `due_at` |
| Diisolir | `isolated` | setelah isolir berhasil |
| Pembayaran diterima | `payment_received` | setelah pembayaran tercatat |

Pesan tidak dikirim ganda untuk kejadian yang sama pada invoice yang sama
(cek `message_logs`): pesan baru tidak dibuat selama yang lama masih `queued`
atau sudah `sent`. Pesan yang `failed` tidak menghalangi kejadian berikutnya.

- **Tagihan terbit**: dilewati jika invoice sudah lunas/dibatalkan sebelum
  pesan dijadwalkan.
- **Pengingat**: untuk invoice `unpaid`/`overdue` dengan `due_at` tepat
  H-`reminder_days_before` atau hari ini, termasuk invoice pelanggan
  `terminated` yang masih ditagih. Jika `reminder_days_before = 0`, hanya
  pengingat hari jatuh tempo yang dikirim. Pengingat yang terlewat karena
  server mati tidak dikirim belakangan.
- **Diisolir**: hanya untuk isolir otomatis (`isolation_reason = overdue`),
  karena isolir manual bisa bukan karena tagihan. Invoice yang disebut adalah
  tunggakan lewat toleransi dengan `due_at` paling lama. Dilewati jika
  pelanggan sudah aktif kembali atau tunggakannya sudah lunas. Isolir ulang
  untuk tunggakan yang sama tidak mengirim pesan lagi.
- **Pembayaran diterima**: hanya untuk pembayaran normal (bukan anomali).
- Template yang dinonaktifkan admin tidak dikirim (tidak tercatat di
  `message_logs`).
- Paling banyak satu pesan per 5 detik (`WHATSAPP_SECONDS_PER_MESSAGE`) untuk
  seluruh aplikasi agar nomor pengirim tidak diblokir.
- Penolakan provider (nomor tidak valid, token salah, kuota habis, perangkat
  terputus) langsung dicatat `failed` tanpa dicoba ulang; galat jaringan atau
  server provider dicoba ulang hingga 3 kali.
- **Kirim ulang tagihan** oleh kasir/admin (`invoices.resend`): hanya invoice
  `unpaid`/`overdue`, memakai template `invoice_issued`. Pesan lama yang sudah
  `sent` tidak menghalangi, tetapi pesan yang masih `queued` menolak kiriman
  baru (mencegah klik ganda); template nonaktif ditolak dengan pesan. Maksimal
  6 permintaan per menit per pengguna, dicatat `invoice.resent`.
