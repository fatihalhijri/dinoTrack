<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MessageTemplateKey;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessageTemplate;
use Carbon\CarbonImmutable;
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
     * Contoh nilai setiap placeholder untuk pratinjau di halaman pengaturan template,
     * diformat sama dengan pesan sungguhan (tanggal panjang, Rupiah tanpa spasi).
     *
     * @return array<string, string>
     */
    public function examples(CarbonImmutable $today, int $dueDays): array
    {
        return [
            '{nama}' => 'Budi Santoso',
            '{nomor_invoice}' => 'INV/'.$today->format('Y/m').'/00001',
            '{total}' => Money::format(150000),
            '{jatuh_tempo}' => $today->addDays($dueDays)->translatedFormat('j F Y'),
            '{link_bayar}' => url('tagihan/1').'?signature=contoh',
        ];
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
