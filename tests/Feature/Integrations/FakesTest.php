<?php

declare(strict_types=1);

use App\Enums\PaymentChargeStatus;
use App\Exceptions\MessageSendException;
use App\Exceptions\RouterUnreachableException;
use App\Models\Customer;
use App\Models\Invoice;
use PHPUnit\Framework\AssertionFailedError;
use Tests\Fakes\FakeMessageSender;
use Tests\Fakes\FakeNetworkController;
use Tests\Fakes\FakePaymentGateway;

it('merekam panggilan beserta argumennya', function () {
    $network = new FakeNetworkController;
    $customer = new Customer;

    $network->isolate($customer);
    $network->activate($customer, 'HOME-20');

    $network->assertCalled('isolate', 1);
    $network->assertCalled('activate');
    $network->assertNotCalled('disableSecret');
    expect($network->calls('activate')[0]['args'])->toBe([$customer, 'HOME-20']);
});

it('gagal pada assertNotCalled bila metode ternyata dipanggil', function () {
    $network = new FakeNetworkController;
    $network->isolate(new Customer);

    $network->assertNotCalled('isolate');
})->throws(AssertionFailedError::class);

it('melempar exception pada setiap panggilan saat diatur gagal', function () {
    $network = (new FakeNetworkController)->failWith(new RouterUnreachableException('timeout'));

    expect(fn () => $network->isolate(new Customer))->toThrow(RouterUnreachableException::class)
        ->and(fn () => $network->isolate(new Customer))->toThrow(RouterUnreachableException::class);

    $network->assertCalled('isolate', 2);
});

it('hanya gagal n kali lalu kembali normal untuk menguji retry', function () {
    $sender = (new FakeMessageSender)->failTimes(2, new MessageSendException('gateway down'));

    expect(fn () => $sender->send('628111', 'a'))->toThrow(MessageSendException::class)
        ->and(fn () => $sender->send('628111', 'a'))->toThrow(MessageSendException::class);

    $result = $sender->send('628111', 'a');

    expect($result->success)->toBeTrue()
        ->and($sender->sentMessages())->toHaveCount(3);
});

it('berhenti gagal setelah stopFailing', function () {
    $sender = (new FakeMessageSender)->failWith(new MessageSendException('down'));
    $sender->stopFailing();

    expect($sender->send('628111', 'a')->success)->toBeTrue();
});

it('FakeMessageSender menyimpan nomor dan isi pesan', function () {
    $sender = new FakeMessageSender;

    $sender->send('628123456789', 'Tagihan terbit');

    expect($sender->sentMessages())->toBe([
        ['phone' => '628123456789', 'message' => 'Tagihan terbit'],
    ]);
});

it('FakePaymentGateway membuat charge QRIS berurutan dengan order_id unik', function () {
    $gateway = new FakePaymentGateway;
    $invoice = (new Invoice)->forceFill(['id' => 7, 'total' => 150000]);

    $first = $gateway->createQrisCharge($invoice);
    $second = $gateway->createQrisCharge($invoice);

    expect($first->orderId)->not->toBe($second->orderId)
        ->and($first->amount)->toBe(150000)
        ->and($first->status)->toBe(PaymentChargeStatus::Pending);
});

it('FakePaymentGateway mengikuti hasil notifikasi yang diatur', function () {
    $gateway = new FakePaymentGateway;
    $gateway->signatureValid = false;

    expect($gateway->verifyNotification([]))->toBeFalse();

    $notification = $gateway->parseNotification(['order_id' => 'INV-1', 'gross_amount' => 5000]);

    expect($notification->orderId)->toBe('INV-1')
        ->and($notification->grossAmount)->toBe(5000)
        ->and($notification->isSettled())->toBeTrue()
        ->and($gateway->checkStatus('INV-1')->status)->toBe(PaymentChargeStatus::Pending);
});
