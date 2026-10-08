<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Notifications\SendInvoiceReminders;
use App\Concerns\ResolvesCommandDate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('billing:send-reminders {--date= : Tanggal simulasi YYYY-MM-DD (tidak boleh di production)}')]
#[Description('Kirim pengingat WhatsApp H-N dan hari jatuh tempo untuk tagihan yang belum lunas')]
class SendRemindersCommand extends Command
{
    use ResolvesCommandDate;

    public function handle(SendInvoiceReminders $sendReminders): int
    {
        try {
            $date = $this->resolveDate();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $result = $sendReminders->handle($date);

        $this->info(sprintf(
            'Pengingat %s: %d dijadwalkan, %d dilewati, %d gagal.',
            $date->toDateString(),
            $result['queued'],
            $result['skipped'],
            $result['failed'],
        ));

        if ($result['failed'] > 0) {
            $this->error('Invoice gagal (detail di log): '.implode(', ', $result['failed_invoice_ids']));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
