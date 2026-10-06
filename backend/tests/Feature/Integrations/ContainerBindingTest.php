<?php

declare(strict_types=1);

use App\Contracts\MessageSender;
use App\Contracts\NetworkController;
use App\Contracts\PaymentGateway;
use App\Exceptions\NotImplementedException;
use App\Services\Messaging\FonnteMessageSender;
use App\Services\Network\MikrotikNetworkController;
use App\Services\Payment\MidtransPaymentGateway;
use Tests\Fakes\FakeMessageSender;
use Tests\Fakes\FakeNetworkController;
use Tests\Fakes\FakePaymentGateway;

it('memasang router palsu untuk setiap test agar tidak menghubungi router sungguhan', function () {
    expect(app(NetworkController::class))->toBeInstanceOf(FakeNetworkController::class);
});

it('me-resolve setiap interface ke implementasi aslinya', function () {
    $this->app->forgetInstance(NetworkController::class);

    expect(app(PaymentGateway::class))->toBeInstanceOf(MidtransPaymentGateway::class)
        ->and(app(NetworkController::class))->toBeInstanceOf(MikrotikNetworkController::class)
        ->and(app(MessageSender::class))->toBeInstanceOf(FonnteMessageSender::class);
});

it('melempar NotImplementedException dari implementasi yang masih stub', function () {
    expect(fn () => app(MessageSender::class)->send('628123456789', 'Halo'))
        ->toThrow(NotImplementedException::class);
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
    config(['services.whatsapp.driver' => 'tidak-ada']);

    app(MessageSender::class);
})->throws(InvalidArgumentException::class, 'Driver WhatsApp tidak dikenal');
