<?php

declare(strict_types=1);

use App\Actions\Network\ActivateCustomer;
use App\Actions\Network\ApplyCustomerProfile;
use App\Actions\Network\IsolateCustomer;
use App\Enums\IsolationReason;
use App\Exceptions\RouterCommandException;
use App\Exceptions\RouterUnreachableException;
use App\Exceptions\SecretNotFoundException;
use App\Jobs\ActivateCustomerJob;
use App\Jobs\ApplyCustomerProfileJob;
use App\Jobs\DisableCustomerSecretJob;
use App\Jobs\IsolateCustomerJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;

/**
 * Setiap job router dengan pelanggan yang memenuhi syarat dan cara menjalankan handle()-nya.
 *
 * @return array<string, array{Closure(): array{ShouldQueue, Closure(ShouldQueue): void}, string}>
 */
function routerJobs(): array
{
    return [
        'isolir' => [function (): array {
            $customer = customerOnProfile(Customer::factory()->active());
            invoiceDueAt($customer, today()->subDays(10)->toDateString());

            return [
                new IsolateCustomerJob($customer, IsolationReason::Overdue, today()),
                fn (IsolateCustomerJob $job) => $job->handle(app(IsolateCustomer::class)),
            ];
        }, 'customer.isolation_failed'],
        'aktivasi' => [fn (): array => [
            new ActivateCustomerJob(customerOnProfile(Customer::factory()->isolated())),
            fn (ActivateCustomerJob $job) => $job->handle(app(ActivateCustomer::class)),
        ], 'customer.activation_failed'],
        'pasang profil' => [fn (): array => [
            new ApplyCustomerProfileJob(customerOnProfile(Customer::factory()->active())),
            fn (ApplyCustomerProfileJob $job) => $job->handle(app(ApplyCustomerProfile::class)),
        ], 'customer.profile_failed'],
    ];
}

it('mencoba ulang 3 kali dengan jeda 30 detik, 2 menit, lalu 10 menit', function (Closure $make) {
    [$job] = $make();

    expect($job->tries)->toBe(4)
        ->and($job->backoff)->toBe([30, 120, 600]);
})->with(routerJobs());

it('melempar ulang galat router tidak terjangkau agar job dicoba ulang', function (Closure $make) {
    fakeNetwork()->failWith(new RouterUnreachableException('timeout'));
    [$job, $handle] = $make();
    $job->withFakeQueueInteractions();

    expect(fn () => $handle($job))->toThrow(RouterUnreachableException::class);

    $job->assertNotFailed();
})->with(routerJobs());

it('langsung gagal tanpa dicoba ulang jika mengulang tidak akan membantu', function (Closure $make, string $failedAction, Throwable $failure) {
    fakeNetwork()->failWith($failure);
    [$job, $handle] = $make();
    $job->withFakeQueueInteractions();

    $handle($job);

    $job->assertFailedWith($failure::class);
})->with(routerJobs())->with([
    'secret tidak ada' => fn () => new SecretNotFoundException('no such item'),
    'perintah ditolak router' => fn () => new RouterCommandException('input does not match any value of profile'),
]);

it('menandai pelanggan untuk ditinjau admin setelah semua percobaan habis', function (Closure $make, string $failedAction) {
    [$job] = $make();
    $this->travelTo('2026-10-20 03:00');

    $job->failed(new RouterUnreachableException('Router Pusat (10.0.0.1:8728) tidak bisa dijangkau: timeout'));

    $customer = $job->customer->fresh();
    expect($customer->network_error_at->toDateTimeString())->toBe('2026-10-20 03:00:00')
        ->and($customer->network_error)->toContain('tidak bisa dijangkau')
        ->and(Customer::query()->hasNetworkError()->pluck('id')->all())->toBe([$customer->id]);
    $log = ActivityLog::query()->where('action', $failedAction)->sole();
    expect($log->subject_id)->toBe($customer->id)
        ->and($log->user_id)->toBeNull();
})->with(routerJobs());

it('menandai pelanggan saat penonaktifan secret gagal total', function () {
    $job = new DisableCustomerSecretJob(Customer::factory()->terminated()->create());

    $job->failed(new SecretNotFoundException('no such item'));

    expect($job->customer->fresh()->network_error_at)->not->toBeNull();
});

it('hanya mengantrekan satu job router yang sama untuk satu pelanggan', function () {
    Queue::fake();
    $customer = Customer::factory()->isolated()->create();
    $other = Customer::factory()->isolated()->create();

    ActivateCustomerJob::dispatch($customer);
    ActivateCustomerJob::dispatch($customer);
    ActivateCustomerJob::dispatch($other);
    IsolateCustomerJob::dispatch($customer, IsolationReason::Overdue, today());
    IsolateCustomerJob::dispatch($customer, IsolationReason::Overdue, today());

    Queue::assertPushed(ActivateCustomerJob::class, 2);
    Queue::assertPushed(IsolateCustomerJob::class, 1);
});

it('tidak membuang permintaan admin karena job otomatis untuk pelanggan yang sama masih antre', function () {
    Queue::fake();
    $customer = Customer::factory()->isolated()->create();

    ActivateCustomerJob::dispatch($customer);
    ActivateCustomerJob::dispatch($customer, true);
    IsolateCustomerJob::dispatch($customer, IsolationReason::Overdue, today());
    IsolateCustomerJob::dispatch($customer, IsolationReason::Manual, today());

    Queue::assertPushed(ActivateCustomerJob::class, 2);
    Queue::assertPushed(IsolateCustomerJob::class, 2);
});
