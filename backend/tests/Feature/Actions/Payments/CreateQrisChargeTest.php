<?php

declare(strict_types=1);

use App\Actions\Payments\CreateQrisCharge;
use App\Contracts\PaymentGateway;
use App\Data\GatewayNotification;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentChargeStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;
use App\Models\PaymentCharge;
use Illuminate\Validation\ValidationException;
use Tests\Fakes\FakePaymentGateway;

function fakeGateway(): FakePaymentGateway
{
    $gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $gateway);

    return $gateway;
}

function invoiceToPay(): Invoice
{
    return Invoice::factory()->create(['number' => 'INV/2026/10/00001', 'total' => 150_000]);
}

it('membuat charge QRIS pertama dengan order_id dari nomor invoice', function () {
    $this->freezeSecond();
    $gateway = fakeGateway();
    $invoice = invoiceToPay();

    $charge = app(CreateQrisCharge::class)->handle($invoice);

    expect($charge->fresh())
        ->invoice_id->toBe($invoice->id)
        ->attempt->toBe(1)
        ->gateway->toBe('midtrans')
        ->order_id->toBe('INV20261000001-1')
        ->amount->toBe(150000)
        ->status->toBe(PaymentChargeStatus::Pending)
        ->qr_url->toBe('https://fake.test/qr/1.png')
        ->expires_at->equalTo(now()->addMinutes(15))->toBeTrue();
    expect($gateway->calls('createQrisCharge')[0]['args'][1])->toBe('INV20261000001-1');
});

it('memakai ulang charge pending yang masih berlaku', function () {
    $gateway = fakeGateway();
    $existing = PaymentCharge::factory()->for(invoiceToPay())->create(['expires_at' => now()->addMinutes(10)]);

    $charge = app(CreateQrisCharge::class)->handle($existing->invoice);

    expect($charge->id)->toBe($existing->id)
        ->and(PaymentCharge::query()->count())->toBe(1);
    $gateway->assertNothingCalled();
});

it('membuat percobaan baru setelah charge sebelumnya kedaluwarsa atau gagal', function (string $state) {
    fakeGateway();
    $invoice = invoiceToPay();
    PaymentCharge::factory()->for($invoice)->{$state}()->create();

    $charge = app(CreateQrisCharge::class)->handle($invoice);

    expect($charge->attempt)->toBe(2)
        ->and($charge->order_id)->toBe('INV20261000001-2');
})->with(['expired', 'failed']);

it('menolak membuat charge untuk invoice yang sudah lunas atau dibatalkan', function (string $state, string $message) {
    $gateway = fakeGateway();
    $invoice = Invoice::factory()->{$state}()->create();

    expect(fn () => app(CreateQrisCharge::class)->handle($invoice))->toThrow(ValidationException::class, $message);

    $gateway->assertNothingCalled();
})->with([
    'lunas' => ['paid', 'Tagihan sudah lunas.'],
    'dibatalkan' => ['cancelled', 'Tagihan sudah dibatalkan.'],
]);

it('tidak membuat charge baru jika charge yang waktunya habis ternyata sudah dibayar', function () {
    $gateway = fakeGateway();
    $old = PaymentCharge::factory()->for(invoiceToPay())->create(['expires_at' => now()->subMinute()]);
    $gateway->respondWith(gatewayNotification($old));

    expect(fn () => app(CreateQrisCharge::class)->handle($old->invoice))->toThrow(ValidationException::class, 'Tagihan sudah lunas.');

    expect($old->invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(PaymentCharge::query()->count())->toBe(1);
    $gateway->assertNotCalled('createQrisCharge');
});

it('membuat charge baru jika charge yang waktunya habis memang sudah kedaluwarsa di gateway', function () {
    $gateway = fakeGateway();
    $old = PaymentCharge::factory()->for(invoiceToPay())->create(['expires_at' => now()->subMinute()]);
    $gateway->respondWith(gatewayNotification($old, PaymentChargeStatus::Expired, transactionStatus: 'expire'));

    $charge = app(CreateQrisCharge::class)->handle($old->invoice);

    expect($charge->attempt)->toBe(2)
        ->and($old->fresh()->status)->toBe(PaymentChargeStatus::Expired);
});

it('memakai ulang charge yang waktunya hampir habis jika gateway masih menganggapnya pending', function () {
    $gateway = fakeGateway();
    $old = PaymentCharge::factory()->for(invoiceToPay())->create(['expires_at' => now()->addSeconds(30)]);
    $gateway->respondWith(new GatewayNotification($old->order_id, PaymentChargeStatus::Pending, $old->amount, transactionStatus: 'pending'));

    expect(app(CreateQrisCharge::class)->handle($old->invoice)->id)->toBe($old->id);

    $gateway->assertCalled('checkStatus', 1);
    $gateway->assertNotCalled('createQrisCharge');
});

it('menandai percobaan gagal saat gateway galat sehingga percobaan berikutnya memakai order_id baru', function () {
    $gateway = fakeGateway()->failTimes(1, new PaymentGatewayException('Midtrans tidak bisa dihubungi.'));
    $invoice = invoiceToPay();

    expect(fn () => app(CreateQrisCharge::class)->handle($invoice))->toThrow(PaymentGatewayException::class);

    expect(PaymentCharge::query()->sole()->status)->toBe(PaymentChargeStatus::Failed);

    $charge = app(CreateQrisCharge::class)->handle($invoice);

    expect($charge->order_id)->toBe('INV20261000001-2');
    $gateway->assertCalled('createQrisCharge', 2);
});

it('menggagalkan percobaan tanpa QR yang ditinggalkan proses sebelumnya', function () {
    fakeGateway();
    $abandoned = PaymentCharge::factory()->for(invoiceToPay())->create(['qr_string' => null, 'qr_url' => null, 'expires_at' => null]);

    $charge = app(CreateQrisCharge::class)->handle($abandoned->invoice);

    expect($abandoned->fresh()->status)->toBe(PaymentChargeStatus::Failed)
        ->and($charge->attempt)->toBe(2);
});
