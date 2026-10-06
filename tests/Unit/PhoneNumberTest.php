<?php

declare(strict_types=1);

use App\Support\PhoneNumber;

it('menormalisasi nomor WhatsApp ke format 62xxx', function (string $input, string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'awalan 08' => ['081234567890', '6281234567890'],
    'awalan +62' => ['+6281234567890', '6281234567890'],
    'sudah 62' => ['6281234567890', '6281234567890'],
    'dengan spasi' => ['0812 3456 7890', '6281234567890'],
    'dengan tanda hubung' => ['0812-3456-7890', '6281234567890'],
    'dengan titik' => ['0812.3456.7890', '6281234567890'],
    '+62 dengan kurung dan spasi' => ['+62 (812) 3456-7890', '6281234567890'],
    'spasi di ujung' => ['  081234567890  ', '6281234567890'],
]);

it('mengembalikan bentuk bersih untuk format yang tidak dikenal agar ditolak validasi', function (string $input, string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'tanpa awalan 0' => ['812 3456 7890', '81234567890'],
    'kode negara lain' => ['+1 555-0100', '+15550100'],
    'nomor telepon rumah' => ['021-5551234', '0215551234'],
    'kosong' => ['', ''],
]);
