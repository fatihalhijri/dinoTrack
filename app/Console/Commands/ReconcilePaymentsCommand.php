<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payments\ReconcilePendingCharges;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:reconcile-payments')]
#[Description('Cek status charge QRIS pending ke payment gateway untuk menangkap webhook yang terlewat')]
class ReconcilePaymentsCommand extends Command
{
    public function handle(ReconcilePendingCharges $reconcile): int
    {
        $result = $reconcile->handle();

        $this->info(sprintf('Rekonsiliasi pembayaran: %d charge dicek, %d gagal.', $result['checked'], $result['failed']));

        if ($result['failed'] > 0) {
            $this->error('Charge gagal (detail di log): '.implode(', ', $result['failed_charge_ids']));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
