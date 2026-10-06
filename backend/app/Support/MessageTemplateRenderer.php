<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MessageTemplateKey;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Log;

/**
 * Mengisi placeholder template WhatsApp. Placeholder yang tidak dikenal dibiarkan apa adanya
 * agar salah ketik admin terlihat di pesan, bukan hilang diam-diam.
 */
final class MessageTemplateRenderer
{
    /**
     * Placeholder yang dikenali beserta artinya (ditampilkan di halaman pengaturan template).
     *
     * @var array<string, string>
     */
    public const array PLACEHOLDERS = [
        '{nama}' => 'Nama pelanggan',
        '{nomor_invoice}' => 'Nomor tagihan',
        '{total}' => 'Total tagihan, contoh Rp150.000',
        '{jatuh_tempo}' => 'Tanggal jatuh tempo, contoh 17 Oktober 2026',
        '{link_bayar}' => 'Link halaman tagihan dan pembayaran QRIS',
    ];

    /**
     * @return string|null null jika template dinonaktifkan admin atau belum ada (seeder belum dijalankan)
     */
    public function render(MessageTemplateKey $key, Customer $customer, Invoice $invoice): ?string
    {
        $template = MessageTemplate::query()->where('key', $key)->first();

        if ($template === null) {
            Log::warning('Template WhatsApp tidak ditemukan; pesan tidak dikirim. Jalankan MessageTemplateSeeder.', ['key' => $key->value]);

            return null;
        }

        if (! $template->is_active) {
            return null;
        }

        return strtr($template->body, $this->placeholders($customer, $invoice));
    }

    /**
     * @return array<string, string>
     */
    private function placeholders(Customer $customer, Invoice $invoice): array
    {
        return [
            '{nama}' => $customer->name,
            '{nomor_invoice}' => $invoice->number,
            '{total}' => Money::format($invoice->total),
            '{jatuh_tempo}' => $invoice->due_at->translatedFormat('j F Y'),
            '{link_bayar}' => InvoicePaymentLink::for($invoice),
        ];
    }
}
