# Tahap F06 — Pembayaran

**Tujuan:** kasir mencatat pembayaran tunai/transfer dengan cepat, dan admin
meninjau pembayaran anomali.

## Prompt

```
Tahap F06: pembayaran. Baca CLAUDE.md (aturan frontend), docs/08 bagian
`invoices/show` (aksi catat pembayaran) dan `payments/index`,
docs/04-aturan-bisnis.md (pembayaran anomali), dan
app/Http/Requests/Payments.

Kerjakan:
1. Dialog "Catat pembayaran" di invoices/show (payments.record, invoice
   unpaid/overdue): metode tunai/transfer (radio), nominal terkunci = total
   (ditampilkan Rupiah), tanggal bayar (default hari ini), catatan.
2. pages/payments/index.tsx:
   - FilterBar: pencarian, metode, status tinjauan, rentang tanggal
     (from/to)
   - kolom tanggal, invoice, pelanggan, metode, nominal, diterima oleh,
     status tinjauan; baris needs_review disorot
   - dialog "Tandai selesai ditinjau" (payments.review, review_note min 5)
     dengan alasan anomali ditampilkan
3. Badge "Perlu tinjauan" pada pembayaran anomali di invoices/show dan
   customers/show.
4. Backend (celah G6): filter customer_id di /payments + prop
   `customer: {id, code, name} | null`, tautan "lihat semua pembayaran" di
   customers/show; test + docs/08.

Test (perluas PaymentControllerTest dan InvoicePaymentControllerTest):
komponen ada; kasir bisa mencatat tetapi tidak meninjau; filter customer_id.
Rencana dulu, tunggu persetujuan.
```

## Kriteria selesai
- Kasir mencatat pembayaran dalam satu dialog tanpa mengetik nominal
- Admin bisa menyelesaikan tinjauan anomali
- Test hijau

## Commit
`feat(payment): pencatatan dan daftar pembayaran`
