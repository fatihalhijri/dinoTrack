<?php

declare(strict_types=1);

use App\Enums\PaymentChargeStatus;
use App\Models\Invoice;
use App\Models\PaymentCharge;
use Illuminate\Database\UniqueConstraintViolationException;

it('menolak nomor percobaan charge yang sama untuk satu invoice', function () {
    $invoice = Invoice::factory()->create();
    PaymentCharge::factory()->for($invoice)->expired()->create(['attempt' => 1]);

    PaymentCharge::factory()->for($invoice)->create(['attempt' => 1, 'order_id' => 'LAIN-1']);
})->throws(UniqueConstraintViolationException::class);

it('membentuk order_id dari nomor invoice tanpa garis miring dan nomor percobaan', function () {
    $invoice = Invoice::factory()->create(['number' => 'INV/2026/10/00001']);

    $charge = PaymentCharge::factory()->for($invoice)->create(['attempt' => 2]);

    expect($charge->order_id)->toBe('INV20261000001-2');
});

it('hanya mengambil charge yang masih pending', function () {
    $pending = PaymentCharge::factory()->create();
    PaymentCharge::factory()->expired()->create();
    PaymentCharge::factory()->settled()->create();

    expect(PaymentCharge::pending()->pluck('id')->all())->toBe([$pending->id])
        ->and($pending->fresh()->status)->toBe(PaymentChargeStatus::Pending);
});
