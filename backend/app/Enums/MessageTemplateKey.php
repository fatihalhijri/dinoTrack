<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageTemplateKey: string
{
    case InvoiceIssued = 'invoice_issued';
    case ReminderBeforeDue = 'reminder_before_due';
    case ReminderDue = 'reminder_due';
    case Isolated = 'isolated';
    case PaymentReceived = 'payment_received';

    public function label(): string
    {
        return match ($this) {
            self::InvoiceIssued => 'Tagihan terbit',
            self::ReminderBeforeDue => 'Pengingat sebelum jatuh tempo',
            self::ReminderDue => 'Pengingat hari jatuh tempo',
            self::Isolated => 'Pemberitahuan isolir',
            self::PaymentReceived => 'Konfirmasi pembayaran',
        };
    }

    /**
     * Isi template bawaan, di-seed oleh MessageTemplateSeeder dan bisa diubah admin.
     */
    public function defaultBody(): string
    {
        return match ($this) {
            self::InvoiceIssued => "Halo {nama},\n\nTagihan internet Anda nomor {nomor_invoice} sebesar {total} sudah terbit dan jatuh tempo pada {jatuh_tempo}.\n\nBayar dengan QRIS melalui tautan berikut:\n{link_bayar}\n\nTerima kasih.",
            self::ReminderBeforeDue => "Halo {nama},\n\nKami mengingatkan tagihan {nomor_invoice} sebesar {total} akan jatuh tempo pada {jatuh_tempo}.\n\nBayar melalui:\n{link_bayar}",
            self::ReminderDue => "Halo {nama},\n\nHari ini ({jatuh_tempo}) adalah batas pembayaran tagihan {nomor_invoice} sebesar {total}. Agar layanan tidak terputus, segera bayar melalui:\n{link_bayar}",
            self::Isolated => "Halo {nama},\n\nLayanan internet Anda sementara dinonaktifkan karena tagihan {nomor_invoice} sebesar {total} belum dibayar. Layanan aktif kembali otomatis beberapa saat setelah pembayaran:\n{link_bayar}",
            self::PaymentReceived => "Halo {nama},\n\nPembayaran tagihan {nomor_invoice} sebesar {total} sudah kami terima. Terima kasih telah berlangganan.",
        };
    }
}
