<?php

declare(strict_types=1);

use App\Actions\Network\ActivateCustomerManually;
use App\Actions\Network\IsolateCustomerManually;
use App\Enums\CustomerStatus;
use App\Enums\IsolationReason;
use App\Jobs\ActivateCustomerJob;
use App\Jobs\IsolateCustomerJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => $this->travelTo('2026-10-20 10:00'));

it('admin mengisolir pelanggan aktif dengan alasan lewat job router', function () {
    $network = fakeNetwork();
    $admin = User::factory()->create();
    $customer = customerOnProfile(Customer::factory()->active());

    app(IsolateCustomerManually::class)->handle($customer, '  Penyalahgunaan jaringan  ', $admin);

    $network->assertCalled('isolate', 1);
    expect($customer->fresh()->isolation_reason)->toBe(IsolationReason::Manual);
    $requested = ActivityLog::query()->where('action', 'customer.isolation_requested')->sole();
    expect($requested->user_id)->toBe($admin->id)
        ->and($requested->properties)->toBe(['reason' => 'Penyalahgunaan jaringan']);
});

it('mewajibkan alasan minimal 5 karakter untuk isolir dan buka isolir manual', function (Closure $action) {
    Queue::fake();

    expect(fn () => $action(' abc '))->toThrow(ValidationException::class, 'Alasan wajib diisi minimal 5 karakter.');

    Queue::assertNothingPushed();
})->with([
    'isolir' => fn (string $reason) => app(IsolateCustomerManually::class)->handle(Customer::factory()->active()->create(), $reason, User::factory()->create()),
    'buka isolir' => fn (string $reason) => app(ActivateCustomerManually::class)->handle(Customer::factory()->isolated()->create(), $reason, User::factory()->create()),
]);

it('menolak isolir manual untuk status yang tidak sah', function (Closure $factory, string $message) {
    Queue::fake();

    expect(fn () => app(IsolateCustomerManually::class)->handle($factory()->create(), 'Alasan isolir', User::factory()->create()))
        ->toThrow(ValidationException::class, $message);

    Queue::assertNothingPushed();
})->with([
    'belum terpasang' => [fn () => Customer::factory()->pending(), 'Pelanggan belum terpasang.'],
    'berhenti' => [fn () => Customer::factory()->terminated(), 'Pelanggan sudah berhenti berlangganan.'],
    'sudah isolir manual' => [fn () => Customer::factory()->isolated(IsolationReason::Manual), 'Pelanggan sudah diisolir manual.'],
]);

it('admin membuka isolir dan diberi tahu jika pelanggan masih menunggak lewat toleransi', function (?string $dueAt, bool $expectedWarning) {
    $network = fakeNetwork();
    $customer = customerOnProfile(Customer::factory()->isolated(IsolationReason::Overdue));

    if ($dueAt !== null) {
        invoiceDueAt($customer, $dueAt);
    }

    $hasArrears = app(ActivateCustomerManually::class)->handle($customer, 'Janji bayar Jumat', User::factory()->create());

    expect($hasArrears)->toBe($expectedWarning)
        ->and($customer->fresh()->status)->toBe(CustomerStatus::Active);
    $network->assertCalled('activate', 1);
    expect(ActivityLog::query()->where('action', 'customer.activation_requested')->sole()->properties['has_arrears_past_grace'])->toBe($expectedWarning);
})->with([
    'masih menunggak' => ['2026-10-16', true],
    'tanpa tunggakan' => [null, false],
]);

it('menolak buka isolir untuk pelanggan yang tidak sedang diisolir', function () {
    Queue::fake();

    expect(fn () => app(ActivateCustomerManually::class)->handle(Customer::factory()->active()->create(), 'Buka isolir', User::factory()->create()))
        ->toThrow(ValidationException::class, 'Pelanggan tidak sedang diisolir.');

    Queue::assertNothingPushed();
});

it('tidak menjalankan job router jika transaksi pemanggil dibatalkan', function () {
    // Queue::fake() mengabaikan afterCommit, jadi dipakai queue sync sungguhan.
    $processed = [];
    Queue::before(function (JobProcessing $event) use (&$processed): void {
        $processed[] = $event->job->resolveName();
    });
    $admin = User::factory()->create();
    $active = customerOnProfile(Customer::factory()->active());
    $isolated = customerOnProfile(Customer::factory()->isolated());

    try {
        DB::transaction(function () use ($active, $isolated, $admin): void {
            app(IsolateCustomerManually::class)->handle($active, 'Alasan isolir', $admin);
            app(ActivateCustomerManually::class)->handle($isolated, 'Alasan buka', $admin);

            throw new RuntimeException('Simulasi galat.');
        });
    } catch (RuntimeException) {
    }

    expect($processed)->not->toContain(IsolateCustomerJob::class)
        ->and($processed)->not->toContain(ActivateCustomerJob::class);
});
