<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Invoices\GenerateMonthlyInvoices;
use App\Concerns\ResolvesCommandDate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('billing:generate-invoices {--date= : Tanggal simulasi YYYY-MM-DD (tidak boleh di production)}')]
#[Description('Terbitkan tagihan untuk semua periode yang sudah dimulai dan belum ditagih')]
class GenerateInvoicesCommand extends Command
{
    use ResolvesCommandDate;

    public function handle(GenerateMonthlyInvoices $generateInvoices): int
    {
        try {
            $date = $this->resolveDate();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $result = $generateInvoices->handle($date);

        $this->info(sprintf(
            'Tagihan %s: %d dibuat, %d dilewati, %d gagal.',
            $date->toDateString(),
            $result['created'],
            $result['skipped'],
            $result['failed'],
        ));

        if ($result['failed'] > 0) {
            $this->error('Subscription gagal (detail di log): '.implode(', ', $result['failed_subscription_ids']));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
