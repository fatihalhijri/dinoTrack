<?php

declare(strict_types=1);

use App\Support\Money;

it('memformat rupiah dengan pemisah ribuan titik', function () {
    expect(Money::format(150000))->toBe('Rp150.000')
        ->and(Money::format(1250000))->toBe('Rp1.250.000')
        ->and(Money::format(999))->toBe('Rp999');
});

it('memformat nol sebagai Rp0', function () {
    expect(Money::format(0))->toBe('Rp0');
});

it('memformat nilai negatif dengan tanda minus di depan', function () {
    expect(Money::format(-5000))->toBe('-Rp5.000');
});

it('membulatkan ke atas ke kelipatan Rp100', function (int $input, int $expected) {
    expect(Money::ceilToHundreds($input))->toBe($expected);
})->with([
    'sudah kelipatan 100' => [60000, 60000],
    'lebih sedikit 1 rupiah' => [60001, 60100],
    'tepat di bawah kelipatan' => [60099, 60100],
    'nol' => [0, 0],
    'di bawah 100' => [1, 100],
]);
