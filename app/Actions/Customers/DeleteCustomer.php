<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soft delete hanya untuk salah input atau batal pasang. Pelanggan yang pernah terpasang
 * (secret PPPoE-nya aktif di router) atau sudah punya invoice harus diberhentikan agar
 * secret dinonaktifkan dan riwayat tagihannya tetap utuh.
 */
final class DeleteCustomer
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Customer $customer, ?User $by = null): void
    {
        DB::transaction(function () use ($customer, $by): void {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            if ($customer->status !== CustomerStatus::Pending) {
                throw ValidationException::withMessages([
                    'customer' => 'Hanya pelanggan yang belum terpasang yang bisa dihapus. Berhentikan langganannya sebagai gantinya.',
                ]);
            }

            if ($customer->invoices()->exists()) {
                throw ValidationException::withMessages([
                    'customer' => 'Pelanggan yang sudah punya tagihan tidak bisa dihapus. Berhentikan langganannya sebagai gantinya.',
                ]);
            }

            // Subscription diakhiri agar tidak ada lagi subscription aktif milik pelanggan terhapus.
            $customer->activeSubscription()->update([
                'ends_at' => today()->toDateString(),
                'next_package_id' => null,
            ]);

            $customer->delete();
            $this->logger->log('customer.deleted', $customer, $by, ['code' => $customer->code, 'name' => $customer->name]);
        });
    }
}
