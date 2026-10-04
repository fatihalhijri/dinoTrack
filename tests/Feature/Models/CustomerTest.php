<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\IsolationReason;
use App\Models\Customer;
use App\Models\Router;
use App\Models\Subscription;
use Illuminate\Database\UniqueConstraintViolationException;

it('membaca status dan alasan isolir sebagai enum', function () {
    $customer = Customer::factory()->isolated(IsolationReason::Manual)->create();

    $fresh = $customer->fresh();

    expect($fresh->status)->toBe(CustomerStatus::Isolated)
        ->and($fresh->isolation_reason)->toBe(IsolationReason::Manual);
});

it('menyerialisasi tanggal pasang tanpa jam dan zona waktu', function () {
    $customer = Customer::factory()->create(['installed_at' => '2026-02-09']);

    expect($customer->fresh()->toArray()['installed_at'])->toBe('2026-02-09');
});

it('mengambil paket dari subscription aktif dan mengabaikan riwayat', function () {
    $customer = Customer::factory()->create();
    Subscription::factory()->for($customer)->ended()->create();
    $current = Subscription::factory()->for($customer)->create();

    expect($customer->activeSubscription->is($current))->toBeTrue()
        ->and($customer->subscriptions)->toHaveCount(2);
});

it('hanya menagih pelanggan active dan isolated', function () {
    $active = Customer::factory()->active()->create();
    $isolated = Customer::factory()->isolated()->create();
    Customer::factory()->pending()->create();
    Customer::factory()->terminated()->create();

    expect(Customer::billable()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$active->id, $isolated->id])->sort()->values()->all());
});

it('menolak username PPPoE ganda pada router yang sama', function () {
    $router = Router::factory()->create();
    Customer::factory()->for($router)->create(['pppoe_username' => 'budi']);

    Customer::factory()->for($router)->create(['pppoe_username' => 'budi']);
})->throws(UniqueConstraintViolationException::class);

it('mengizinkan username PPPoE yang sama di router lain', function () {
    Customer::factory()->create(['pppoe_username' => 'budi']);

    $other = Customer::factory()->create(['pppoe_username' => 'budi']);

    expect($other->exists)->toBeTrue();
});

it('menyimpan pelanggan yang dihapus sebagai soft delete', function () {
    $customer = Customer::factory()->create();

    $customer->delete();

    $this->assertSoftDeleted($customer);
});

it('membuat subscription yang mengikuti status pelanggan lewat factory', function () {
    $pending = Customer::factory()->pending()->withSubscription()->create();
    $terminated = Customer::factory()->terminated()->withSubscription()->create();

    expect($pending->activeSubscription->starts_at)->toBeNull()
        ->and($terminated->activeSubscription)->toBeNull()
        ->and($terminated->subscriptions()->first()->ends_at)->not->toBeNull();
});
