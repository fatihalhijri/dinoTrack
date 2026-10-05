<?php

declare(strict_types=1);

use App\Actions\Payments\RecordManualPayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;
use App\Jobs\SendPaymentConfirmationJob;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

it('mencatat pembayaran tunai atau transfer dari kasir dan melunasi invoice', function (PaymentMethod $method) {
    Queue::fake([SendPaymentConfirmationJob::class]);
    $this->travelTo('2026-10-12 14:00');
    $kasir = User::factory()->create();
    $invoice = Invoice::factory()->create(['issued_at' => '2026-10-10', 'total' => 150_000]);

    $payment = app(RecordManualPayment::class)->handle($invoice, $method, 150_000, $kasir, notes: '  Dibayar di loket  ');

    expect($payment->fresh())
        ->invoice_id->toBe($invoice->id)
        ->method->toBe($method)
        ->amount->toBe(150000)
        ->paid_at->equalTo(now())->toBeTrue()
        ->received_by->toBe($kasir->id)
        ->notes->toBe('Dibayar di loket')
        ->payment_charge_id->toBeNull()
        ->review_status->toBe(PaymentReviewStatus::None);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(ActivityLog::query()->where('action', 'payment.received')->sole()->user_id)->toBe($kasir->id);
    Queue::assertPushed(SendPaymentConfirmationJob::class);
})->with([PaymentMethod::Cash, PaymentMethod::Transfer]);

it('mencatat tanggal bayar mundur yang diisi kasir', function () {
    $this->travelTo('2026-10-12 14:00');
    $invoice = Invoice::factory()->create(['issued_at' => '2026-10-10']);

    app(RecordManualPayment::class)->handle($invoice, PaymentMethod::Cash, $invoice->total, User::factory()->create(), CarbonImmutable::parse('2026-10-10'));

    expect($invoice->fresh()->paid_at->toDateString())->toBe('2026-10-10')
        ->and(Payment::query()->sole()->paid_at->toDateString())->toBe('2026-10-10');
});

it('menolak pembayaran manual yang tidak sesuai aturan', function (Closure $arrange, string $message) {
    $this->travelTo('2026-10-12 14:00');
    [$invoice, $method, $amount, $paidAt] = $arrange();

    expect(fn () => app(RecordManualPayment::class)->handle($invoice, $method, $amount, User::factory()->create(), $paidAt))
        ->toThrow(ValidationException::class, $message);

    expect(Payment::query()->count())->toBe(0);
})->with([
    'nominal kurang' => [fn () => [Invoice::factory()->create(['total' => 150_000]), PaymentMethod::Cash, 100_000, null], 'harus sama dengan total tagihan Rp150.000'],
    'nominal lebih' => [fn () => [Invoice::factory()->create(['total' => 150_000]), PaymentMethod::Cash, 300_000, null], 'harus sama dengan total tagihan Rp150.000'],
    'invoice sudah lunas' => [fn () => [Invoice::factory()->paid()->create(), PaymentMethod::Cash, 150_000, null], 'Tagihan sudah lunas.'],
    'invoice dibatalkan' => [fn () => [Invoice::factory()->cancelled()->create(), PaymentMethod::Cash, 150_000, null], 'Tagihan sudah dibatalkan.'],
    'metode QRIS' => [fn () => [Invoice::factory()->create(), PaymentMethod::Qris, 150_000, null], 'dicatat otomatis oleh payment gateway'],
    'tanggal di masa depan' => [fn () => [Invoice::factory()->create(), PaymentMethod::Cash, 150_000, CarbonImmutable::parse('2026-10-13')], 'tidak boleh di masa depan'],
    'sebelum tagihan terbit' => [fn () => [Invoice::factory()->create(['issued_at' => '2026-10-10']), PaymentMethod::Cash, 150_000, CarbonImmutable::parse('2026-10-09')], 'sebelum tanggal terbit tagihan'],
]);
