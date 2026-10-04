# Tahap 05 — Pembayaran Manual & QRIS

**Tujuan:** pembayaran manual oleh kasir dan pembayaran QRIS otomatis lewat
Midtrans, dengan webhook yang aman dan idempotent.

**Prasyarat:** akun sandbox Midtrans, Server Key sandbox sudah diisi di `.env`
(jangan ditempel ke chat).

## Prompt

```
Tahap 05: pembayaran. Baca docs/05-integrasi.md bagian Payment Gateway,
docs/04-aturan-bisnis.md bagian status invoice, dan docs/03-database.md
(payment_charges, payments, payment_notifications).

Sebelum menulis kode, cek dokumentasi resmi Midtrans Core API untuk QRIS dan
HTTP notification, lalu sebutkan jika ada yang berbeda dari docs/05-integrasi.md.

Kerjakan:
1. MidtransPaymentGateway (implementasi PaymentGateway) memakai HTTP client
   Laravel: createQrisCharge, verifyNotification, parseNotification,
   checkStatus. Timeout dan error handling yang jelas.
2. Action CreateQrisCharge: pakai ulang charge pending yang belum kedaluwarsa,
   kalau tidak ada buat baru dengan order_id sesuai format dokumen.
3. Action RecordManualPayment (kasir): nominal harus sama dengan total,
   invoice harus unpaid/overdue.
4. Action MarkInvoicePaid (dipakai bersama oleh manual & QRIS) dalam
   DB::transaction dengan lockForUpdate: invoice paid, simpan payment, catat
   log, dispatch konfirmasi WA, dan jika pelanggan isolated serta tidak ada
   tunggakan lain → dispatch ActivateCustomerJob (job boleh masih kosong).
5. Webhook controller POST /webhooks/payments/midtrans:
   - simpan payload ke payment_notifications
   - verifikasi signature (tolak 403 jika salah)
   - cocokkan gross_amount dengan charge
   - idempotent: notifikasi ganda tidak membuat payment ganda
   - respons 200 cepat; proses lewat ProcessGatewayNotification
   - kecualikan dari CSRF di bootstrap/app.php, beri rate limit
6. Command billing:reconcile-payments (tiap jam) yang memanggil checkStatus
   untuk charge pending, menangani webhook yang terlewat.
7. Test: signature salah, nominal tidak cocok, notifikasi ganda, status
   expire, invoice sudah lunas, race dua notifikasi bersamaan, pembayaran
   manual, aktivasi terpicu hanya bila tidak ada tunggakan lain.
   Gunakan Http::fake, jangan panggil Midtrans sungguhan.

Rencana dulu, tunggu persetujuan.
```

## Uji manual setelah tahap ini
1. Jalankan `php artisan serve` dan ngrok/cloudflared
2. Set Notification URL di dashboard sandbox Midtrans ke URL tunnel
3. Buat charge, bayar lewat simulator sandbox, pastikan invoice berubah lunas

## Commit
`feat(payment): pembayaran manual, QRIS Midtrans, dan webhook`
