<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Payment;

beforeEach(fn () => $this->travelTo('2026-10-20 10:00'));

it('mengunduh rincian pembayaran sesuai rentang tanggal', function () {
    Payment::factory()->cash()->create(['reference' => 'DI-DALAM', 'paid_at' => '2026-10-05 10:00']);
    Payment::factory()->cash()->create(['reference' => 'DI-LUAR', 'paid_at' => '2026-09-30 10:00']);

    $response = $this->actingAs(userWithRole(Role::Admin))
        ->get(route('reports.export.payments', ['from' => '2026-10-01', 'to' => '2026-10-31']));

    $response->assertOk()
        ->assertDownload('pembayaran-2026-10-01-2026-10-31.csv');
    expect($response->streamedContent())
        ->toContain('DI-DALAM')
        ->not->toContain('DI-LUAR');
});

it('mewajibkan rentang tanggal untuk ekspor pembayaran', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('reports.export.payments'))
        ->assertSessionHasErrors(['from' => 'Tanggal awal wajib diisi.', 'to' => 'Tanggal akhir wajib diisi.']);
});

it('mengunduh tunggakan dan rekap pendapatan dengan nama file bertanggal', function (string $routeName, array $query, string $filename) {
    $this->actingAs(userWithRole(Role::Admin))
        ->get(route($routeName, $query))
        ->assertOk()
        ->assertDownload($filename);
})->with([
    'tunggakan' => ['reports.export.outstanding', [], 'tunggakan-2026-10-20.csv'],
    'pendapatan' => ['reports.export.revenue', ['year' => 2025], 'pendapatan-2025.csv'],
]);
