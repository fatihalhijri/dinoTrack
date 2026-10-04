# 04 — Aturan Bisnis

> **Sesuaikan dokumen ini dengan kebijakan usahamu sebelum Tahap 04.**
> Nilai default di bawah disimpan di tabel `settings` agar bisa diubah admin.

## Pengaturan default

| Key | Default | Arti |
|---|---|---|
| `billing.due_days` | 7 | Jatuh tempo = tanggal terbit + N hari |
| `billing.grace_days` | 3 | Toleransi setelah jatuh tempo sebelum isolir |
| `billing.reminder_days_before` | 3 | Pengingat dikirim H-N jatuh tempo |
| `billing.penalty_amount` | 0 | Denda tetap (rupiah) jika telat; 0 = tanpa denda |
| `billing.prorate_first_month` | true | Bulan pertama dihitung prorata |
| `billing.auto_isolate` | true | Isolir otomatis aktif |
| `billing.auto_activate` | true | Aktivasi otomatis setelah lunas |

## Siklus tagihan

1. Setiap pelanggan punya `billing_day` (1–28) = tanggal pasang. Tanggal 29–31
   dibulatkan ke 28 agar ada di setiap bulan.
2. Tagihan periode berikutnya **terbit pada `billing_day`** dan menagih bulan
   yang akan dipakai (bayar di depan).
3. Periode: `billing_day` bulan ini s.d. sehari sebelum `billing_day` bulan depan.
4. Satu subscription hanya boleh punya **satu** invoice per periode.
5. Pelanggan berstatus `terminated` atau `pending` tidak ditagih.

## Prorata bulan pertama

Jika `billing.prorate_first_month = true`:

```
tagihan pertama = harga_paket × (jumlah hari dari tanggal pasang
                  s.d. akhir periode pertama) ÷ jumlah hari periode
```

Dibulatkan **ke atas ke kelipatan Rp100**. Contoh: paket Rp150.000, periode
30 hari, dipakai 12 hari → 150.000 × 12 ÷ 30 = 60.000.

## Status invoice

```
unpaid ──(lewat due_at)──> overdue
unpaid / overdue ──(lunas)──> paid
unpaid / overdue ──(dibatalkan admin)──> cancelled
```

- `paid` dan `cancelled` adalah status akhir, tidak bisa berubah lagi.
- Pembayaran harus **sama dengan** total invoice. Pembayaran sebagian tidak
  didukung di v1.
- Denda (jika > 0) ditambahkan sekali saat invoice menjadi `overdue`.

## Status pelanggan

```
pending ──(aktivasi pertama)──> active
active ──(tunggakan lewat toleransi)──> isolated
isolated ──(semua tagihan lunas)──> active
active / isolated ──(berhenti)──> terminated
```

- **Isolir otomatis**: pelanggan `active` yang punya invoice `overdue` dengan
  `due_at + grace_days < hari ini`.
- **Aktivasi otomatis**: setelah pembayaran, jika pelanggan `isolated` dan
  **tidak ada lagi** invoice `unpaid`/`overdue` yang lewat toleransi.
- Isolir/aktivasi manual oleh admin wajib menyertakan alasan dan tercatat di log.
- Jika perintah ke router gagal, status di database **tidak berubah**; job
  dicoba ulang. Setelah semua percobaan gagal, admin diberi tanda (log + flag).

## Ganti paket

- Diatur lewat `subscriptions.next_package_id`.
- Berlaku saat tagihan periode berikutnya dibuat; profil PPPoE diubah saat itu.

## Berhenti berlangganan

- Pelanggan `terminated` tidak ditagih lagi; invoice `unpaid` yang tersisa
  tetap ada untuk ditagih.
- Secret PPPoE di router dinonaktifkan (bukan dihapus).

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
