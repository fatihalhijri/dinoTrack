<?php

declare(strict_types=1);

use App\Actions\Customers\DeleteCustomer;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Validation\ValidationException;

it('soft delete pelanggan pending yang belum punya tagihan dan mengakhiri subscription-nya', function () {
    $customer = Customer::factory()->pending()->withSubscription()->create();
    $subscription = $customer->activeSubscription;

    app(DeleteCustomer::class)->handle($customer);

    $this->assertSoftDeleted($customer);
    expect($subscription->fresh()->ends_at?->toDateString())->toBe(today()->toDateString());
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'customer.deleted', 'subject_id' => $customer->id]);
});

it('menolak menghapus pelanggan yang sudah terpasang meskipun belum punya tagihan', function (Closure $makeCustomer) {
    /** @var Customer $customer */
    $customer = $makeCustomer();

    expect(fn () => app(DeleteCustomer::class)->handle($customer))
        ->toThrow(ValidationException::class, 'Hanya pelanggan yang belum terpasang');

    $this->assertNotSoftDeleted($customer);
})->with([
    'active' => [fn () => Customer::factory()->active()->withSubscription()->create()],
    'isolated' => [fn () => Customer::factory()->isolated()->withSubscription()->create()],
    'terminated' => [fn () => Customer::factory()->terminated()->withSubscription()->create()],
]);

it('menolak menghapus pelanggan pending yang punya tagihan dari langganan sebelumnya', function () {
    $customer = Customer::factory()->pending()->create();
    Invoice::factory()->for(Subscription::factory()->for($customer)->ended())->paid()->create();
    Subscription::factory()->for($customer)->create(['starts_at' => null]);

    expect(fn () => app(DeleteCustomer::class)->handle($customer))
        ->toThrow(ValidationException::class, 'sudah punya tagihan');

    $this->assertNotSoftDeleted($customer);
});
