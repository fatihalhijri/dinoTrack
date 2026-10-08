# Tahap F05 — Tagihan

**Tujuan:** daftar dan detail tagihan, termasuk link bayar, riwayat QRIS,
pembatalan, terbit ulang, dan kirim ulang WhatsApp.

## Prompt

```
Tahap F05: tagihan. Baca CLAUDE.md (aturan frontend), docs/08 bagian
`invoices/index` dan `invoices/show`, docs/04-aturan-bisnis.md (status
invoice, link tagihan), dan app/Http/Controllers/InvoiceController.

Kerjakan:
1. pages/invoices/index.tsx:
   - FilterBar: pencarian, status, periode (input month → YYYY-MM)
   - kolom nomor, pelanggan, periode, jatuh tempo, total, status
   - jika customer_id terisi: chip "Tagihan milik <kode – nama>" dengan
     tombol hapus filter. Backend: tambah prop `customer: {id, code, name}
     | null` (celah G5) + test + docs/08
2. pages/invoices/show.tsx:
   - rincian: nomor, pelanggan, periode, terbit, jatuh tempo, item, subtotal,
     total, status (+ alasan batal)
   - salin payment_link (use-clipboard) hanya untuk invoice unpaid/overdue
   - riwayat charge QRIS (percobaan, order_id, status, kedaluwarsa)
   - daftar pembayaran (sederhana; dialog catat pembayaran di F06)
   - pesan WA untuk invoice ini
   - tampilan cetak sederhana (utility print:)
3. Aksi:
   - Batalkan (invoices.cancel, alasan min 5)
   - Terbit ulang (invoices.cancel, paket koreksi opsional → redirect ke
     invoice baru)
   - Kirim ulang WA (invoices.resend; respons 429 throttle ditampilkan
     sebagai toast)
   Hanya untuk invoice unpaid/overdue.

Test (perluas InvoiceControllerTest): komponen ada; packages null tanpa
invoices.cancel; prop customer sesuai filter.
Rencana dulu, tunggu persetujuan.
```

## Kriteria selesai
- Aksi tagihan berjalan dari UI dan tersembunyi untuk invoice lunas/batal
- Kontrak docs/08 diperbarui (prop `customer`)
- Test hijau

## Commit
`feat(invoice): halaman daftar dan detail tagihan`
