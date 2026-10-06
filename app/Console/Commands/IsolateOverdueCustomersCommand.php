<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Network\IsolateOverdueCustomers;
use App\Concerns\ResolvesCommandDate;
use App\Support\SettingsRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('billing:isolate-overdue {--date= : Tanggal simulasi YYYY-MM-DD (tidak boleh di production)}')]
#[Description('Jadwalkan isolir pelanggan yang tunggakannya lewat masa toleransi')]
class IsolateOverdueCustomersCommand extends Command
{
    use ResolvesCommandDate;

    public function handle(IsolateOverdueCustomers $isolateOverdue, SettingsRepository $settings): int
    {
        try {
            $date = $this->resolveDate();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        if (! $settings->autoIsolate()) {
            $this->info('Isolir otomatis dimatikan (billing.auto_isolate); tidak ada yang dijadwalkan.');

            return self::SUCCESS;
        }

        $queued = $isolateOverdue->handle($date);

        $this->info(sprintf('Isolir %s: %d pelanggan dijadwalkan.', $date->toDateString(), $queued));

        return self::SUCCESS;
    }
}
