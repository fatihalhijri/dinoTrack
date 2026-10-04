# Tahap 04 — Tagihan Otomatis

**Tujuan:** tagihan bulanan terbit otomatis setiap hari sesuai aturan,
tanpa duplikat, dengan prorata dan penomoran yang benar.

> Pastikan `docs/04-aturan-bisnis.md` sudah sesuai kebijakan usahamu.

## Prompt

```
Tahap 04: tagihan otomatis. Baca docs/04-aturan-bisnis.md dengan teliti
(siklus tagihan, prorata, status invoice) dan docs/02-arsitektur.md (jadwal).

Kerjakan:
1. SettingsRepository untuk membaca pengaturan billing.* dari tabel settings
   dengan nilai default dan cache.
2. InvoiceNumberGenerator: format INV/YYYY/MM/NNNNN, urutan per bulan,
   aman dari race condition (lock).
3. ProrataCalculator (unit murni) sesuai rumus di dokumen, dibulatkan ke
   atas kelipatan Rp100.
4. Action GenerateInvoiceForSubscription dan GenerateMonthlyInvoices:
   - hanya pelanggan active/isolated
   - satu invoice per periode (hormati unique constraint, tangani dengan rapi)
   - terapkan next_package_id lalu kosongkan
   - dispatch notifikasi invoice_issued (job boleh masih kosong)
5. Action MarkOverdueInvoices: ubah unpaid → overdue, tambahkan denda jika
   penalty_amount > 0 (hanya sekali).
6. Action CancelInvoice dengan alasan wajib.
7. Console command billing:generate-invoices dan billing:mark-overdue dengan
   opsi --date untuk simulasi, daftarkan di routes/console.php sesuai jadwal
   di dokumen (withoutOverlapping, onOneServer).
8. Test dengan travelTo(): akhir bulan, billing_day 28, Februari, prorata,
   tidak ada duplikat saat dijalankan dua kali, pelanggan terminated tidak
   ditagih, ganti paket, denda hanya sekali.

Rencana dulu. Jika ada aturan di dokumen yang ambigu, tanyakan sebelum menulis kode.
```

## Kriteria selesai
- Menjalankan generator dua kali di hari yang sama tidak membuat duplikat
- Semua kasus tanggal tepi tertutup test

## Commit
`feat(invoice): generate tagihan bulanan, prorata, dan status overdue`
