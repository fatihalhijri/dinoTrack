<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentCharge;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

it('menolak tagihan ganda untuk subscription dan periode yang sama', function () {
    $subscription = Subscription::factory()->create();
    Invoice::factory()->for($subscription)->create(['period_start' => '2026-10-05']);

    Invoice::factory()->for($subscription)->create(['period_start' => '2026-10-05']);
})->throws(UniqueConstraintViolationException::class);

it('mengizinkan tagihan periode berbeda untuk subscription yang sama', function () {
    $subscription = Subscription::factory()->create();
    Invoice::factory()->for($subscription)->create(['period_start' => '2026-09-05']);

    Invoice::factory()->for($subscription)->create(['period_start' => '2026-10-05']);

    expect($subscription->invoices()->count())->toBe(2);
});

it('mengikuti pelanggan dari subscription-nya', function () {
    $invoice = Invoice::factory()->create();

    expect($invoice->customer->is($invoice->subscription->customer))->toBeTrue();
});

it('membaca status sebagai enum, uang sebagai integer, dan tanggal sebagai Carbon', function () {
    $invoice = Invoice::factory()->overdue()->create(['subtotal' => 150_000, 'total' => 150_000]);

    $fresh = $invoice->fresh();

    expect($fresh->status)->toBe(InvoiceStatus::Overdue)
        ->and($fresh->total)->toBe(150_000)
        ->and($fresh->due_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('mengisi diskon dan denda 0 pada invoice baru', function () {
    $invoice = new Invoice;

    expect($invoice->discount)->toBe(0)
        ->and($invoice->penalty)->toBe(0);
});

it('hanya menganggap unpaid dan overdue sebagai tagihan terutang', function () {
    $unpaid = Invoice::factory()->create();
    $overdue = Invoice::factory()->overdue()->create();
    Invoice::factory()->paid()->create();
    Invoice::factory()->cancelled()->create();

    expect(Invoice::outstanding()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$unpaid->id, $overdue->id])->sort()->values()->all())
        ->and(Invoice::overdue()->pluck('id')->all())->toBe([$overdue->id]);
});

it('memuat item, charge, dan pembayaran miliknya', function () {
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->for($invoice)->create();
    PaymentCharge::factory()->for($invoice)->create();
    Payment::factory()->for($invoice)->create();

    expect($invoice->items)->toHaveCount(1)
        ->and($invoice->paymentCharges)->toHaveCount(1)
        ->and($invoice->payments)->toHaveCount(1);
});

it('ikut menghapus item ketika invoice dihapus', function () {
    $item = InvoiceItem::factory()->create();

    $item->invoice->delete();

    $this->assertModelMissing($item);
});
