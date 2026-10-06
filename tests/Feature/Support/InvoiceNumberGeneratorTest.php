<?php

declare(strict_types=1);

use App\Support\InvoiceNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

it('membuat nomor INV/YYYY/MM/NNNNN berurutan dalam satu bulan', function () {
    $numbers = app(InvoiceNumberGenerator::class);
    $october = CarbonImmutable::parse('2026-10-10');

    expect($numbers->next($october))->toBe('INV/2026/10/00001')
        ->and($numbers->next($october->addDays(5)))->toBe('INV/2026/10/00002');
});

it('mengulang urutan dari 1 setiap bulan terbit', function () {
    $numbers = app(InvoiceNumberGenerator::class);
    $numbers->next(CarbonImmutable::parse('2026-10-31'));
    $numbers->next(CarbonImmutable::parse('2026-10-31'));

    expect($numbers->next(CarbonImmutable::parse('2026-11-01')))->toBe('INV/2026/11/00001')
        ->and($numbers->next(CarbonImmutable::parse('2027-01-01')))->toBe('INV/2027/01/00001');
});

it('melanjutkan urutan bulan yang sudah berjalan', function () {
    DB::table('sequences')->insert(['key' => 'invoice:2026-10', 'last_value' => 41]);

    expect(app(InvoiceNumberGenerator::class)->next(CarbonImmutable::parse('2026-10-20')))->toBe('INV/2026/10/00042');
});
