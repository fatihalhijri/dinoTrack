<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\MessageTemplateKey;
use App\Models\Invoice;
use App\Support\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pengingat harian 09:00: H-`reminder_days_before` (`reminder_before_due`) dan hari jatuh tempo
 * (`reminder_due`) untuk invoice yang belum lunas. Tanpa catch-up: pengingat yang terlewat karena
 * server mati tidak dikirim belakangan. Aman dijalankan ulang karena NotifyCustomer menolak pesan ganda.
 */
final class SendInvoiceReminders
{
    public function __construct(
        private readonly NotifyCustomer $notify,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @return array{queued: int, skipped: int, failed: int, failed_invoice_ids: list<int>}
     */
    public function handle(CarbonImmutable $today): array
    {
        $result = ['queued' => 0, 'skipped' => 0, 'failed' => 0, 'failed_invoice_ids' => []];
        $daysBefore = $this->settings->reminderDaysBefore();

        // Jika 0, H-N sama dengan hari jatuh tempo dan cukup satu pengingat.
        if ($daysBefore > 0) {
            $this->remind($today->addDays($daysBefore), MessageTemplateKey::ReminderBeforeDue, $result);
        }

        $this->remind($today, MessageTemplateKey::ReminderDue, $result);

        return $result;
    }

    /**
     * Invoice pelanggan `terminated` tetap diingatkan karena masih ditagih (docs/04).
     *
     * @param  array{queued: int, skipped: int, failed: int, failed_invoice_ids: list<int>}  $result
     */
    private function remind(CarbonImmutable $dueAt, MessageTemplateKey $key, array &$result): void
    {
        Invoice::query()
            ->outstanding()
            ->whereDate('due_at', $dueAt->toDateString())
            ->with('customer')
            ->chunkById(100, function (Collection $invoices) use ($key, &$result): void {
                foreach ($invoices as $invoice) {
                    try {
                        $messageLog = $this->notify->handle($invoice->customer, $key, $invoice);
                    } catch (Throwable $exception) {
                        Log::error('Gagal menjadwalkan pengingat tagihan.', [
                            'invoice_id' => $invoice->id,
                            'template_key' => $key->value,
                            'exception' => $exception,
                        ]);
                        $result['failed']++;
                        $result['failed_invoice_ids'][] = $invoice->id;

                        continue;
                    }

                    $messageLog === null ? $result['skipped']++ : $result['queued']++;
                }
            });
    }
}
