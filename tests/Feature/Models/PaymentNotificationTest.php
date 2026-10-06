<?php

declare(strict_types=1);

use App\Models\PaymentNotification;

it('menghapus notifikasi lewat masa retensi dan menyimpan notifikasi valid yang belum diproses', function () {
    $this->travelTo('2026-01-01 08:00');
    $oldInvalid = PaymentNotification::factory()->create(['signature_valid' => false]);
    $oldProcessed = PaymentNotification::factory()->create(['processed_at' => now()]);
    $oldUnprocessed = PaymentNotification::factory()->create();

    $this->travelTo('2026-11-01 08:00');
    $recentInvalid = PaymentNotification::factory()->create(['signature_valid' => false]);

    $this->travelTo('2026-11-15 08:00');
    $this->artisan('model:prune', ['--model' => [PaymentNotification::class]])->assertSuccessful();

    // Retensi: signature salah 30 hari, valid yang sudah diproses 365 hari.
    expect(PaymentNotification::query()->pluck('id')->sort()->values()->all())
        ->toBe([$oldProcessed->id, $oldUnprocessed->id, $recentInvalid->id]);

    $this->travelTo('2027-01-02 08:00');
    $this->artisan('model:prune', ['--model' => [PaymentNotification::class]])->assertSuccessful();

    expect(PaymentNotification::query()->pluck('id')->all())->toBe([$oldUnprocessed->id]);
});
