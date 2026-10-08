<?php

declare(strict_types=1);

use App\Actions\Customers\ReactivateCustomer;
use App\Enums\CustomerStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Package;
use Illuminate\Validation\ValidationException;

it('mengembalikan pelanggan berhenti ke pending dengan subscription baru dan riwayat tetap', function () {
    $customer = Customer::factory()->terminated()->withSubscription()->create(['code' => 'PLG-000007']);
    $oldSubscription = $customer->subscriptions()->sole();
    $package = Package::factory()->create(['price' => 200_000]);

    app(ReactivateCustomer::class)->handle($customer, $package->id, 30);

    expect($customer->fresh())
        ->code->toBe('PLG-000007')
        ->status->toBe(CustomerStatus::Pending)
        ->installed_at->toBeNull()
        ->terminated_at->toBeNull()
        ->and($customer->subscriptions()->count())->toBe(2)
        ->and($oldSubscription->fresh()->ends_at)->not->toBeNull()
        ->and($customer->activeSubscription()->sole())
        ->package_id->toBe($package->id)
        ->price->toBe(200_000)
        ->billing_day->toBe(28)
        ->starts_at->toBeNull();

    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'customer.reactivated', 'subject_id' => $customer->id]);
});

it('menolak mengaktifkan kembali pelanggan yang belum berhenti', function (Closure $makeCustomer) {
    app(ReactivateCustomer::class)->handle($makeCustomer(), Package::factory()->create()->id, 10);
})->with([
    'pending' => [fn () => Customer::factory()->pending()->withSubscription()->create()],
    'active' => [fn () => Customer::factory()->active()->withSubscription()->create()],
    'isolated' => [fn () => Customer::factory()->isolated()->withSubscription()->create()],
])->throws(ValidationException::class, 'Hanya pelanggan yang sudah berhenti');

it('menolak paket yang nonaktif', function () {
    $customer = Customer::factory()->terminated()->withSubscription()->create();

    expect(fn () => app(ReactivateCustomer::class)->handle($customer, Package::factory()->inactive()->create()->id, 10))
        ->toThrow(ValidationException::class, 'Paket sudah nonaktif');

    expect($customer->fresh()->status)->toBe(CustomerStatus::Terminated);
});
