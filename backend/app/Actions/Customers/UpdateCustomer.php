<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PhoneNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Router, username PPPoE, dan tanggal tagih hanya bisa diubah selama `pending`: setelah
 * terpasang, perubahan itu berarti memindahkan secret di router dan menggeser periode tagihan.
 */
final class UpdateCustomer
{
    private const array PENDING_ONLY_FIELDS = [
        'router_id' => 'Router hanya bisa diubah selama pelanggan belum terpasang.',
        'pppoe_username' => 'Username PPPoE hanya bisa diubah selama pelanggan belum terpasang.',
        'billing_day' => 'Tanggal tagih hanya bisa diubah selama pelanggan belum terpasang.',
    ];

    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Angka boleh berupa string karena `validated()` tidak mengubah tipe input form.
     *
     * @param  array{name: string, phone: string, address: string, odp?: string|null, latitude?: float|string|null, longitude?: float|string|null, router_id: int|string, pppoe_username: string, notes?: string|null, billing_day?: int|string}  $attributes
     */
    public function handle(Customer $customer, array $attributes, ?User $by = null): Customer
    {
        $routerId = (int) $attributes['router_id'];
        $fields = [
            ...Arr::only($attributes, CreateCustomer::CUSTOMER_FIELDS),
            'router_id' => $routerId,
            'phone' => PhoneNumber::normalize($attributes['phone']),
        ];
        $billingDay = isset($attributes['billing_day'])
            ? min((int) $attributes['billing_day'], Subscription::MAX_BILLING_DAY)
            : null;

        return DB::transaction(function () use ($customer, $fields, $routerId, $attributes, $billingDay, $by): Customer {
            // Status dibaca ulang di bawah lock agar tidak balapan dengan aktivasi atau berhenti.
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $subscription = $customer->activeSubscription;

            $this->ensureAllowedChanges($customer, $subscription, $routerId, $attributes['pppoe_username'], $billingDay);

            $customer->fill($fields);
            $changes = $this->logger->pendingChanges($customer);

            if ($subscription !== null && $billingDay !== null) {
                $subscription->billing_day = $billingDay;
                $changes = [...$changes, ...$this->logger->pendingChanges($subscription)];
                $subscription->save();
            }

            if ($changes === []) {
                return $customer;
            }

            $customer->save();
            $this->logger->log('customer.updated', $customer, $by, ['changes' => $changes]);

            return $customer;
        });
    }

    private function ensureAllowedChanges(Customer $customer, ?Subscription $subscription, int $routerId, string $pppoeUsername, ?int $billingDay): void
    {
        $isRouterChanged = $routerId !== $customer->router_id;

        if ($customer->status !== CustomerStatus::Pending) {
            $changed = array_filter([
                'router_id' => $isRouterChanged,
                'pppoe_username' => $pppoeUsername !== $customer->pppoe_username,
                'billing_day' => $billingDay !== null && $billingDay !== $subscription?->billing_day,
            ]);

            if ($changed !== []) {
                throw ValidationException::withMessages(array_intersect_key(self::PENDING_ONLY_FIELDS, $changed));
            }
        }

        if ($isRouterChanged && ! Router::query()->whereKey($routerId)->sharedLock()->value('is_active')) {
            throw ValidationException::withMessages(['router_id' => 'Router sudah nonaktif dan tidak bisa dipilih.']);
        }
    }
}
