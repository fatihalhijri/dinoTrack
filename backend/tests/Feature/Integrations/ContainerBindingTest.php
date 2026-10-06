<?php

declare(strict_types=1);

use App\Contracts\MessageSender;
use App\Contracts\NetworkController;
use App\Contracts\PaymentGateway;
use App\Services\Messaging\FonnteMessageSender;
use App\Services\Messaging\LogMessageSender;
use App\Services\Network\MikrotikNetworkController;
use App\Services\Payment\MidtransPaymentGateway;
use Tests\Fakes\FakeMessageSender;
use Tests\Fakes\FakeNetworkController;
use Tests\Fakes\FakePaymentGateway;

it('memasang router dan WhatsApp palsu untuk setiap test agar tidak menghubungi layanan sungguhan', function () {
    expect(app(NetworkController::class))->toBeInstanceOf(FakeNetworkController::class)
        ->and(app(MessageSender::class))->toBeInstanceOf(FakeMessageSender::class);
});

it('me-resolve setiap interface ke implementasi aslinya', function () {
    $this->app->forgetInstance(NetworkController::class);
    $this->app->forgetInstance(MessageSender::class);

    expect(app(PaymentGateway::class))->toBeInstanceOf(MidtransPaymentGateway::class)
        ->and(app(NetworkController::class))->toBeInstanceOf(MikrotikNetworkController::class)
        ->and(app(MessageSender::class))->toBeInstanceOf(FonnteMessageSender::class);
});

it('memakai driver WhatsApp log jika dipilih di config', function () {
    $this->app->forgetInstance(MessageSender::class);
    config(['services.whatsapp.driver' => 'log']);

    expect(app(MessageSender::class))->toBeInstanceOf(LogMessageSender::class);
});

it('mengganti implementasi dengan fake lewat container', function () {
    $this->app->instance(PaymentGateway::class, $payment = new FakePaymentGateway);
    $this->app->instance(NetworkController::class, $network = new FakeNetworkController);
    $this->app->instance(MessageSender::class, $messages = new FakeMessageSender);

    expect(app(PaymentGateway::class))->toBe($payment)
        ->and(app(NetworkController::class))->toBe($network)
        ->and(app(MessageSender::class))->toBe($messages);
});

it('menolak driver WhatsApp yang tidak dikenal', function () {
    $this->app->forgetInstance(MessageSender::class);
    config(['services.whatsapp.driver' => 'tidak-ada']);

    app(MessageSender::class);
})->throws(InvalidArgumentException::class, 'Driver WhatsApp tidak dikenal');
