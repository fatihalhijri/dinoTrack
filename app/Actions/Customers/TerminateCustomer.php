<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\CustomerStatus;
use App\Jobs\DisableCustomerSecretJob;
use App\Models\Customer;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Berhenti berlangganan adalah keputusan administratif: status dan subscription langsung
 * berubah agar tagihan berhenti meskipun router sedang tidak bisa dijangkau. Secret PPPoE
 * dinonaktifkan lewat job yang dicoba ulang. Invoice yang belum dibayar tetap ada.
 */
final class TerminateCustomer
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Customer $customer, ?User $by = null, ?string $reason = null): Customer
    {
        return DB::transaction(function () use ($customer, $by, $reason): Customer {
            // Dikunci agar dua permintaan berhenti yang bersamaan tidak sama-sama lolos.
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            if (! in_array($customer->status, [CustomerStatus::Active, CustomerStatus::Isolated], true)) {
                throw ValidationException::withMessages([
                    'status' => $customer->status === CustomerStatus::Pending
                        ? 'Pelanggan yang belum terpasang tidak bisa diberhentikan. Hapus data pelanggan jika batal pasang.'
                        : 'Pelanggan sudah berhenti berlangganan.',
                ]);
            }

            $previousStatus = $customer->status;

            $customer->update([
                'status' => CustomerStatus::Terminated,
                'terminated_at' => now(),
                'isolated_at' => null,
                'isolation_reason' => null,
            ]);

            $customer->activeSubscription()->update([
                'ends_at' => today()->toDateString(),
                'next_package_id' => null,
            ]);

            $this->logger->log('customer.terminated', $customer, $by, [
                'previous_status' => $previousStatus->value,
                'reason' => $reason,
            ]);

            DisableCustomerSecretJob::dispatch($customer)->afterCommit();

            return $customer;
        });
    }
}
