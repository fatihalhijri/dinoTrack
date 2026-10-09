<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Payment;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo('2026-10-20 10:00'));

it('menampilkan pendapatan, umur tunggakan, dan pergerakan pelanggan', function () {
    Payment::factory()->cash()->create(['amount' => 150_000, 'paid_at' => '2026-03-05 10:00']);

    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('reports.index', ['year' => 2026, 'from' => '2026-10-01', 'to' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index', true)
            ->where('year', 2026)
            ->has('revenue', 12)
            ->where('revenue.2.total', 150_000)
            ->where('revenue.2.by_method.cash', 150_000)
            ->has('aging', 3)
            ->where('aging.0.bucket', '0-7')
            ->where('movement.from', '2026-10-01')
            ->where('movement.to', '2026-10-20')
            ->where('methods', [
                ['value' => 'qris', 'label' => 'QRIS'],
                ['value' => 'cash', 'label' => 'Tunai'],
                ['value' => 'transfer', 'label' => 'Transfer bank'],
            ]));
});

it('memakai tahun berjalan dan awal bulan ini sebagai default laporan', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('year', 2026)
            ->where('from', '2026-10-01')
            ->where('to', '2026-10-20')
            ->etc());
});

it('menolak rentang pergerakan pelanggan yang terbalik', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('reports.index', ['from' => '2026-10-20', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');
});

it('menampilkan daftar tunggakan dengan umur dan pencarian pelanggan', function () {
    $budi = customerOnProfile(Customer::factory()->active()->state(['name' => 'Budi Santoso']));
    invoiceDueAt($budi, '2026-10-10');
    invoiceDueAt(customerOnProfile(Customer::factory()->active()->state(['name' => 'Sari'])), '2026-10-01');
    invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-25', InvoiceStatus::Unpaid);

    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('reports.outstanding', ['search' => 'Budi']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/outstanding', true)
            ->has('invoices.data', 1)
            ->where('invoices.data.0.customer_name', 'Budi Santoso')
            ->where('invoices.data.0.age_days', 10)
            ->where('filters', ['search' => 'Budi']));
});

it('mengirim daftar tunggakan berhalaman dengan bentuk data, links, meta, dan label status', function () {
    invoiceDueAt(customerOnProfile(Customer::factory()->isolated()), '2026-10-01');

    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('reports.outstanding', ['per_page' => 10]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices', fn (Assert $invoices) => $invoices
                ->has('data', 1, fn (Assert $invoice) => $invoice
                    ->where('age_days', 19)
                    ->where('status', 'overdue')
                    ->where('status_label', 'Lewat jatuh tempo')
                    ->where('customer_status', 'isolated')
                    ->where('customer_status_label', 'Diisolir')
                    ->etc())
                ->has('links', fn (Assert $links) => $links->hasAll(['first', 'last', 'prev', 'next']))
                ->has('meta', fn (Assert $meta) => $meta
                    ->where('per_page', 10)
                    ->hasAll(['current_page', 'from', 'last_page', 'path', 'to', 'total'])
                    ->has('links.0', fn (Assert $link) => $link->hasAll(['url', 'label', 'page', 'active'])))));
});

it('menolak kasir dan teknisi membuka halaman laporan', function (Role $role, string $route) {
    $this->actingAs(userWithRole($role))
        ->get(route($route))
        ->assertForbidden();
})->with([Role::Kasir, Role::Teknisi])->with(['reports.index', 'reports.outstanding']);
