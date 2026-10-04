# Tahap 07 — Notifikasi WhatsApp

**Tujuan:** pesan otomatis untuk tagihan terbit, pengingat, isolir, dan
konfirmasi pembayaran, tanpa duplikat dan aman dari blokir.

## Prompt

```
Tahap 07: notifikasi WhatsApp. Baca docs/05-integrasi.md bagian WhatsApp dan
docs/04-aturan-bisnis.md bagian Pesan WhatsApp.

Kerjakan:
1. FonnteMessageSender (implementasi MessageSender) memakai HTTP client
   Laravel, dengan pemilihan driver dari config agar driver resmi bisa
   ditambahkan nanti.
2. MessageTemplateRenderer: isi placeholder {nama}, {nomor_invoice}, {total}
   (format Rupiah), {jatuh_tempo} (format tanggal Indonesia), {link_bayar}
   (signed URL ke halaman tagihan publik, berlaku 30 hari).
3. Job SendWhatsAppMessage: tulis message_logs (queued → sent/failed),
   rate limit (misalnya maks 1 pesan per 5 detik lewat RateLimited
   middleware), retry terbatas.
4. Action NotifyCustomer(customer, templateKey, invoice) yang mencegah
   pesan ganda untuk kejadian + invoice yang sama.
5. Sambungkan ke: invoice terbit (Tahap 04), pembayaran diterima (Tahap 05),
   isolir berhasil (Tahap 06).
6. Command billing:send-reminders (09:00) untuk H-N dan hari jatuh tempo.
7. Halaman tagihan publik (Blade, mobile-first) di signed URL: rincian
   tagihan, tombol bayar QRIS yang memanggil CreateQrisCharge, tampilan QR,
   dan status yang diperbarui berkala (polling ringan).
8. Test: placeholder terisi benar, tidak ada pesan ganda, kegagalan provider
   tercatat, pengingat hanya untuk invoice yang belum lunas, signed URL
   kedaluwarsa ditolak.

Rencana dulu, tunggu persetujuan.
```

## Commit
`feat(notification): notifikasi WhatsApp dan halaman tagihan publik`
