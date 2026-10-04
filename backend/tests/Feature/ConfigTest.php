<?php

declare(strict_types=1);

it('memakai zona waktu dan locale Indonesia', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(config('app.locale'))->toBe('id')
        ->and(config('app.faker_locale'))->toBe('id_ID');
});

it('memakai Midtrans sandbox bila bukan production', function () {
    expect(config('services.midtrans.is_production'))->toBeFalse()
        ->and(config('services.midtrans.base_url'))->toBe('https://api.sandbox.midtrans.com');
});

it('memilih Fonnte sebagai driver WhatsApp bawaan', function () {
    expect(config('services.whatsapp.driver'))->toBe('fonnte');
});
