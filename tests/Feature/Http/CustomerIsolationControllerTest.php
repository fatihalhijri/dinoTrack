<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\IsolationReason;
use App\Enums\Role;
use App\Models\Customer;

beforeEach(fn () => $this->travelTo('2026-10-20 10:00'));

it('admin mengisolir pelanggan manual dengan alasan', function () {
    $network = fakeNetwork();
    $customer = customerOnProfile(Customer::factory()->active());

    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('customers.isolate', $customer), ['reason' => 'Penyalahgunaan jaringan'])
        ->assertRedirect(route('customers.show', $customer));

    $network->assertCalled('isolate', 1);
    expect($customer->refresh()->isolation_reason)->toBe(IsolationReason::Manual);
});

it('mewajibkan alasan isolir manual minimal 5 karakter', function () {
    $customer = customerOnProfile(Customer::factory()->active());

    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('customers.isolate', $customer), ['reason' => 'abc'])
        ->assertSessionHasErrors(['reason' => 'Alasan minimal berisi 5 karakter.']);
});

it('memperingatkan admin saat membuka isolir pelanggan yang masih menunggak', function (?string $dueAt, string $type) {
    fakeNetwork();
    $customer = customerOnProfile(Customer::factory()->isolated());

    if ($dueAt !== null) {
        invoiceDueAt($customer, $dueAt);
    }

    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('customers.release', $customer), ['reason' => 'Janji bayar besok'])
        ->assertRedirect(route('customers.show', $customer))
        ->assertInertiaFlash('toast.type', $type);

    expect($customer->refresh()->status)->toBe(CustomerStatus::Active);
})->with([
    'masih menunggak lewat toleransi' => ['2026-10-01', 'warning'],
    'tanpa tunggakan' => [null, 'success'],
]);
