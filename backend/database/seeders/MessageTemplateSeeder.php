<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Template WhatsApp default. Template yang sudah diubah admin tidak ditimpa.
 */
class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MessageTemplateKey::cases() as $key) {
            MessageTemplate::query()->firstOrCreate(['key' => $key], [
                'body' => $this->defaultBody($key),
                'is_active' => true,
            ]);
        }
    }

    private function defaultBody(MessageTemplateKey $key): string
    {
        return match ($key) {
            MessageTemplateKey::InvoiceIssued => "Halo {nama},\n\nTagihan internet Anda nomor {nomor_invoice} sebesar {total} sudah terbit dan jatuh tempo pada {jatuh_tempo}.\n\nBayar dengan QRIS melalui tautan berikut:\n{link_bayar}\n\nTerima kasih.",
            MessageTemplateKey::ReminderBeforeDue => "Halo {nama},\n\nKami mengingatkan tagihan {nomor_invoice} sebesar {total} akan jatuh tempo pada {jatuh_tempo}.\n\nBayar melalui:\n{link_bayar}",
            MessageTemplateKey::ReminderDue => "Halo {nama},\n\nHari ini ({jatuh_tempo}) adalah batas pembayaran tagihan {nomor_invoice} sebesar {total}. Agar layanan tidak terputus, segera bayar melalui:\n{link_bayar}",
            MessageTemplateKey::Isolated => "Halo {nama},\n\nLayanan internet Anda sementara dinonaktifkan karena tagihan {nomor_invoice} sebesar {total} belum dibayar. Layanan aktif kembali otomatis beberapa saat setelah pembayaran:\n{link_bayar}",
            MessageTemplateKey::PaymentReceived => "Halo {nama},\n\nPembayaran tagihan {nomor_invoice} sebesar {total} sudah kami terima. Terima kasih telah berlangganan.",
        };
    }
}
