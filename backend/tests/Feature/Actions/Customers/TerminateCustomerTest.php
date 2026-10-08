<?php

declare(strict_types=1);

use App\Actions\Customers\TerminateCustomer;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\IsolationReason;
use App\Jobs\DisableCustomerSecretJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

it('memberhentikan pelanggan, mengakhiri subscription, dan menjadwalkan penonaktifan secret', function (Closure $makeCustomer) {
    $this->freezeSecond();
    Queue::fake([DisableCustomerSecretJob::class]);
    $admin = User::factory()->create();
    /** @var Customer $customer */
    $customer = $makeCustomer();
    $subscription = $customer->activeSubscription;
    $subscription->update(['next_package_id' => Package::factory()->create()->id]);

    app(TerminateCustomer::class)->handle($customer, $admin, 'Pindah rumah');

    expect($customer->fresh())
        ->status->toBe(CustomerStatus::Terminated)
        ->terminated_at->equalTo(now())->toBeTrue()
        ->isolated_at->toBeNull()
        ->isolation_reason->toBeNull()
        ->and($subscription->fresh())
        ->ends_at->toDateString()->toBe(today()->toDateString())
        ->next_package_id->toBeNull();

    Queue::assertPushed(DisableCustomerSecretJob::class, fn (DisableCustomerSecretJob $job) => $job->customer->is($customer));

    $log = ActivityLog::query()->where('action', 'customer.terminated')->sole();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->properties)->toEqual(['previous_status' => $customer->status->value, 'reason' => 'Pindah rumah']);
})->with([
    'active' => [fn () => Customer::factory()->active()->withSubscription()->create()],
    'isolated' => [fn () => Customer::factory()->isolated(IsolationReason::Overdue)->withSubscription()->create()],
]);

it('membiarkan invoice yang belum dibayar tetap ada', function () {
    Queue::fake([DisableCustomerSecretJob::class]);
    $customer = Customer::factory()->active()->create();
    $invoice = Invoice::factory()->for(Subscription::factory()->for($customer))->create();

    app(TerminateCustomer::class)->handle($customer);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('menolak memberhentikan pelanggan pending atau yang sudah berhenti', function (Closure $makeCustomer, string $message) {
    Queue::fake([DisableCustomerSecretJob::class]);
    /** @var Customer $customer */
    $customer = $makeCustomer();
    $status = $customer->status;

    expect(fn () => app(TerminateCustomer::class)->handle($customer))
        ->toThrow(ValidationException::class, $message);

    expect($customer->fresh()->status)->toBe($status);
    Queue::assertNotPushed(DisableCustomerSecretJob::class);
})->with([
    'pending' => [fn () => Customer::factory()->pending()->withSubscription()->create(), 'belum terpasang'],
    'terminated' => [fn () => Customer::factory()->terminated()->withSubscription()->create(), 'sudah berhenti'],
]);
