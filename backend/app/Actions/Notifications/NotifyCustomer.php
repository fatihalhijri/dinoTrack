<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Support\MessageTemplateRenderer;
use Illuminate\Support\Facades\DB;

/**
 * Menjadwalkan satu pesan WhatsApp untuk kejadian + invoice (docs/04 "Pesan WhatsApp"): body
 * dirender saat ini dan disimpan di `message_logs` berstatus `queued`, lalu dikirim
 * SendWhatsAppMessage. Pesan untuk kejadian yang sama pada invoice yang sama tidak dibuat lagi
 * selama yang lama masih `queued` atau sudah `sent`; yang `failed` boleh dicoba lewat kejadian berikutnya.
 * Kirim ulang manual (`$force`) hanya ditahan oleh pesan yang masih `queued`.
 */
final class NotifyCustomer
{
    public function __construct(
        private readonly MessageTemplateRenderer $renderer,
    ) {}

    /**
     * @return MessageLog|null null jika sudah pernah dikirim (atau masih antre) atau template dinonaktifkan
     */
    public function handle(Customer $customer, MessageTemplateKey $key, Invoice $invoice, bool $force = false): ?MessageLog
    {
        return DB::transaction(function () use ($customer, $key, $invoice, $force): ?MessageLog {
            // Lock pelanggan (M11) menyerialkan notifikasi untuk pelanggan yang sama; pengecekan
            // memakai locking read agar tidak membaca snapshot lama (P10).
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            $alreadyNotified = MessageLog::query()
                ->where('invoice_id', $invoice->id)
                ->where('template_key', $key)
                ->whereIn('status', $force ? [MessageStatus::Queued] : [MessageStatus::Queued, MessageStatus::Sent])
                ->lockForUpdate()
                ->first(['id']) !== null;

            if ($alreadyNotified) {
                return null;
            }

            $body = $this->renderer->render($key, $customer, $invoice);

            if ($body === null) {
                return null;
            }

            $messageLog = MessageLog::query()->create([
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'template_key' => $key,
                'phone' => $customer->phone,
                'body' => $body,
                'status' => MessageStatus::Queued,
            ]);

            SendWhatsAppMessage::dispatch($messageLog)->afterCommit();

            return $messageLog;
        });
    }
}
