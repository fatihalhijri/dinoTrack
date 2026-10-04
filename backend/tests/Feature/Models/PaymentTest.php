<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('menandai pembayaran normal tanpa tinjauan secara default', function () {
    $payment = Payment::create([
        'invoice_id' => Invoice::factory()->create()->id,
        'method' => PaymentMethod::Cash,
        'amount' => 150_000,
        'paid_at' => now(),
    ]);

    expect($payment->review_status)->toBe(PaymentReviewStatus::None)
        ->and($payment->fresh()->review_status)->toBe(PaymentReviewStatus::None);
});

it('menolak referensi gateway yang sama dua kali', function () {
    Payment::factory()->qris()->create(['reference' => 'trx-123']);

    Payment::factory()->qris()->create(['reference' => 'trx-123']);
})->throws(UniqueConstraintViolationException::class);

it('mengizinkan banyak pembayaran manual tanpa referensi', function () {
    Payment::factory()->cash()->count(2)->create(['reference' => null]);

    expect(Payment::whereNull('reference')->count())->toBe(2);
});

it('mengizinkan pembayaran anomali tambahan pada invoice yang sama', function () {
    $invoice = Invoice::factory()->paid()->create();
    Payment::factory()->for($invoice)->create();

    Payment::factory()->for($invoice)->qris()->needsReview()->create();

    expect($invoice->payments()->count())->toBe(2)
        ->and(Payment::needsReview()->count())->toBe(1);
});

it('mencatat kasir yang menerima pembayaran manual', function () {
    $cashier = User::factory()->create();

    $payment = Payment::factory()->cash()->for($cashier, 'receivedBy')->create();

    expect($payment->receivedBy->is($cashier))->toBeTrue()
        ->and($cashier->receivedPayments()->count())->toBe(1);
});

it('menghubungkan pembayaran QRIS dengan charge milik invoice yang sama', function () {
    $payment = Payment::factory()->qris()->create();

    expect($payment->method)->toBe(PaymentMethod::Qris)
        ->and($payment->paymentCharge->invoice_id)->toBe($payment->invoice_id);
});
