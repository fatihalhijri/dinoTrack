<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PhoneNumber;
use App\Support\SequenceGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mendaftarkan pelanggan baru berstatus `pending` (belum ditagih) beserta subscription-nya.
 * Tanggal mulai diisi saat pelanggan ditandai terpasang.
 */
final class CreateCustomer
{
    public const array CUSTOMER_FIELDS = [
        'name', 'phone', 'address', 'odp', 'latitude', 'longitude', 'router_id', 'pppoe_username', 'notes',
    ];

    public function __construct(
        private readonly SequenceGenerator $sequence,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Angka boleh berupa string karena `validated()` tidak mengubah tipe input form.
     *
     * @param  array{name: string, phone: string, address: string, odp?: string|null, latitude?: float|string|null, longitude?: float|string|null, router_id: int|string, pppoe_username: string, notes?: string|null, package_id: int|string, billing_day: int|string}  $attributes
     */
    public function handle(array $attributes, ?User $by = null): Customer
    {
        $packageId = (int) $attributes['package_id'];
        $routerId = (int) $attributes['router_id'];
        $billingDay = min((int) $attributes['billing_day'], Subscription::MAX_BILLING_DAY);

        return DB::transaction(function () use ($attributes, $packageId, $routerId, $billingDay, $by): Customer {
            // Shared lock agar paket/router tidak dinonaktifkan di tengah pendaftaran.
            $package = Package::query()->sharedLock()->findOrFail($packageId);
            $router = Router::query()->sharedLock()->findOrFail($routerId);

            if (! $package->is_active) {
                throw ValidationException::withMessages(['package_id' => 'Paket sudah nonaktif dan tidak bisa dipilih.']);
            }

            if (! $router->is_active) {
                throw ValidationException::withMessages(['router_id' => 'Router sudah nonaktif dan tidak bisa dipilih.']);
            }

            $customer = Customer::query()->create([
                ...Arr::only($attributes, self::CUSTOMER_FIELDS),
                'router_id' => $router->id,
                'phone' => PhoneNumber::normalize($attributes['phone']),
                'code' => sprintf('PLG-%06d', $this->sequence->next('customer')),
                'status' => CustomerStatus::Pending,
            ]);

            $subscription = $customer->subscriptions()->create([
                'package_id' => $package->id,
                'price' => $package->price,
                'billing_day' => $billingDay,
                'starts_at' => null,
            ]);

            $this->logger->log('customer.created', $customer, $by, [
                'code' => $customer->code,
                'package_id' => $package->id,
                'price' => $subscription->price,
                'billing_day' => $subscription->billing_day,
            ]);

            return $customer;
        });
    }
}
