<?php

declare(strict_types=1);

use App\Contracts\NetworkController;
use App\Enums\CustomerStatus;
use App\Exceptions\RouterUnreachableException;
use App\Exceptions\SecretNotFoundException;
use App\Jobs\DisableCustomerSecretJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Support\ActivityLogger;
use Tests\Fakes\FakeNetworkController;

it('menonaktifkan secret PPPoE lewat NetworkController dan mencatat aktivitas', function () {
    $network = new FakeNetworkController;
    $customer = Customer::factory()->terminated()->create();

    (new DisableCustomerSecretJob($customer))->handle($network, app(ActivityLogger::class));

    $network->assertCalled('disableSecret', 1);
    expect($network->calls('disableSecret')[0]['args'][0]->is($customer))->toBeTrue();
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'customer.secret_disabled', 'subject_id' => $customer->id]);
});

it('tidak menonaktifkan secret jika pelanggan sudah diaktifkan kembali saat job berjalan', function () {
    $network = new FakeNetworkController;
    $customer = Customer::factory()->terminated()->create();
    $job = new DisableCustomerSecretJob($customer);

    $customer->update(['status' => CustomerStatus::Active, 'terminated_at' => null]);
    $job->handle($network, app(ActivityLogger::class));

    $network->assertNotCalled('disableSecret');
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'customer.secret_disable_skipped', 'subject_id' => $customer->id]);
    $this->assertDatabaseMissing(ActivityLog::class, ['action' => 'customer.secret_disabled']);
});

it('melempar ulang galat router tidak terjangkau agar job dicoba ulang', function () {
    $network = (new FakeNetworkController)->failWith(new RouterUnreachableException('timeout'));
    $job = (new DisableCustomerSecretJob(Customer::factory()->terminated()->create()))->withFakeQueueInteractions();

    expect(fn () => $job->handle($network, app(ActivityLogger::class)))->toThrow(RouterUnreachableException::class);

    $job->assertNotFailed();
    $this->assertDatabaseMissing(ActivityLog::class, ['action' => 'customer.secret_disabled']);
});

it('langsung gagal tanpa dicoba ulang jika secret tidak ditemukan di router', function () {
    $network = (new FakeNetworkController)->failWith(new SecretNotFoundException('no such item'));
    $job = (new DisableCustomerSecretJob(Customer::factory()->terminated()->create()))->withFakeQueueInteractions();

    $job->handle($network, app(ActivityLogger::class));

    $job->assertFailedWith(SecretNotFoundException::class);
    $this->assertDatabaseMissing(ActivityLog::class, ['action' => 'customer.secret_disabled']);
});

it('mencatat kegagalan di activity log setelah semua percobaan habis', function () {
    $customer = Customer::factory()->terminated()->create();

    (new DisableCustomerSecretJob($customer))->failed(new RouterUnreachableException('timeout'));

    $log = ActivityLog::query()->where('action', 'customer.secret_disable_failed')->sole();
    expect($log->subject_id)->toBe($customer->id)
        ->and($log->user_id)->toBeNull()
        ->and($log->properties)->toBe(['error' => 'timeout']);
});

it('memakai NetworkController dari container saat dijalankan queue', function () {
    $network = new FakeNetworkController;
    $this->app->instance(NetworkController::class, $network);

    DisableCustomerSecretJob::dispatch(Customer::factory()->terminated()->create());

    $network->assertCalled('disableSecret', 1);
});
