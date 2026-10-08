<?php

declare(strict_types=1);

use App\Support\ProrataCalculator;

it('menghitung prorata sesuai contoh di docs/04', function () {
    expect(ProrataCalculator::calculate(150_000, 12, 30))->toBe(60_000);
});

it('membulatkan ke atas ke kelipatan Rp100', function (int $price, int $used, int $full, int $expected) {
    expect(ProrataCalculator::calculate($price, $used, $full))->toBe($expected);
})->with([
    '16 dari 31 hari' => [150_000, 16, 31, 77_500],   // 77.419,35
    '1 dari 31 hari' => [150_000, 1, 31, 4_900],      // 4.838,70
    '5 dari 28 hari (Februari)' => [200_000, 5, 28, 35_800], // 35.714,28
    'hasil tepat kelipatan 100' => [300_000, 15, 30, 150_000],
]);

it('mengembalikan harga penuh untuk periode penuh', function () {
    expect(ProrataCalculator::calculate(150_000, 31, 31))->toBe(150_000);
});

it('tidak melebihi harga sebulan meskipun pembulatan Rp100 melewatinya', function () {
    // 1.050 × 30 ÷ 31 = 1.016,13 → dibulatkan 1.100, lalu dibatasi harga sebulan.
    expect(ProrataCalculator::calculate(1_050, 30, 31))->toBe(1_050);
});
