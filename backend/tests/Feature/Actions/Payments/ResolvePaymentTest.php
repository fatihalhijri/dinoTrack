<?php

declare(strict_types=1);

use App\Actions\Payments\ResolvePayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentReviewStatus;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('menandai pembayaran anomali sudah ditinjau tanpa menghapus alasan anomalinya', function () {
    $this->travelTo('2026-10-06 14:30');
    $invoice = Invoice::factory()->cancelled()->create();
    $payment = Payment::factory()->qris()->needsReview('Invoice sudah dibatalkan')->for($invoice)->create();
    $admin = User::factory()->create(['name' => 'Admin Satu']);

    app(ResolvePayment::class)->handle($payment, '  Dana dikembalikan via transfer  ', $admin);

    expect($payment->refresh())
        ->review_status->toBe(PaymentReviewStatus::Resolved)
        ->review_note->toBe("Invoice sudah dibatalkan\n\nDitinjau Admin Satu (2026-10-06 14:30): Dana dikembalikan via transfer");
    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Cancelled);
    $log = ActivityLog::query()->where('action', 'payment.resolved')->sole();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->properties)->toBe(['note' => 'Dana dikembalikan via transfer']);
});

it('menolak meninjau pembayaran yang tidak perlu tinjauan', function (Closure $payment) {
    app(ResolvePayment::class)->handle($payment(), 'Sudah dicek', User::factory()->create());
})->with([
    'pembayaran normal' => [fn () => Payment::factory()->cash()->create()],
    'sudah ditinjau' => [fn () => Payment::factory()->qris()->create(['review_status' => PaymentReviewStatus::Resolved])],
])->throws(ValidationException::class, 'Hanya pembayaran yang perlu tinjauan yang bisa ditandai sudah ditinjau.');

it('mewajibkan catatan tinjauan minimal 5 karakter', function () {
    app(ResolvePayment::class)->handle(Payment::factory()->qris()->needsReview()->create(), ' ok ', User::factory()->create());
})->throws(ValidationException::class, 'Catatan tinjauan minimal 5 karakter.');
