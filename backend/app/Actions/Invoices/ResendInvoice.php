<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Actions\Notifications\NotifyCustomer;
use App\Enums\InvoiceStatus;
use App\Enums\MessageTemplateKey;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kasir mengirim ulang pesan tagihan (`invoice_issued`) ke WhatsApp pelanggan, misalnya karena
 * pesan lama terhapus. Pesan yang sudah terkirim tidak menghalangi, tetapi pesan yang masih
 * antre menolak kiriman baru agar klik ganda tidak mengirim dua pesan.
 */
final class ResendInvoice
{
    public function __construct(
        private readonly NotifyCustomer $notify,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Invoice $invoice, User $by): MessageLog
    {
        if (! in_array($invoice->status, InvoiceStatus::outstanding(), true)) {
            throw ValidationException::withMessages(['invoice' => 'Hanya tagihan yang belum dibayar yang bisa dikirim ulang.']);
        }

        $isTemplateActive = MessageTemplate::query()
            ->where('key', MessageTemplateKey::InvoiceIssued)
            ->where('is_active', true)
            ->exists();

        if (! $isTemplateActive) {
            throw ValidationException::withMessages([
                'invoice' => 'Template pesan "'.MessageTemplateKey::InvoiceIssued->label().'" sedang nonaktif.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $by): MessageLog {
            $messageLog = $this->notify->handle($invoice->customer, MessageTemplateKey::InvoiceIssued, $invoice, force: true);

            if ($messageLog === null) {
                throw ValidationException::withMessages(['invoice' => 'Pesan tagihan sebelumnya masih dalam antrean pengiriman.']);
            }

            $this->logger->log('invoice.resent', $invoice, $by, ['message_log_id' => $messageLog->id]);

            return $messageLog;
        });
    }
}
