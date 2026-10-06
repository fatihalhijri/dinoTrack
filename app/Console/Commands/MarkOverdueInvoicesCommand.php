<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Invoices\MarkOverdueInvoices;
use App\Concerns\ResolvesCommandDate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('billing:mark-overdue {--date= : Tanggal simulasi YYYY-MM-DD (tidak boleh di production)}')]
#[Description('Ubah tagihan unpaid yang lewat jatuh tempo menjadi overdue')]
class MarkOverdueInvoicesCommand extends Command
{
    use ResolvesCommandDate;

    public function handle(MarkOverdueInvoices $markOverdue): int
    {
        try {
            $date = $this->resolveDate();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $marked = $markOverdue->handle($date);

        $this->info(sprintf('Tagihan %s: %d ditandai overdue.', $date->toDateString(), $marked));

        return self::SUCCESS;
    }
}
