<?php

declare(strict_types=1);

namespace Tests;

use App\Contracts\MessageSender;
use App\Contracts\NetworkController;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Fakes\FakeMessageSender;
use Tests\Fakes\FakeNetworkController;

abstract class TestCase extends BaseTestCase
{
    /**
     * Router dan WhatsApp palsu dipasang untuk semua test: dengan queue `sync`, job router dan
     * notifikasi yang ikut ter-dispatch (aktivasi, tagihan terbit, pembayaran) tidak boleh
     * menghubungi layanan sungguhan. Test yang perlu memeriksa panggilan memakai helper
     * `fakeNetwork()` / `fakeMessages()`.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(NetworkController::class, new FakeNetworkController);
        $this->app->instance(MessageSender::class, new FakeMessageSender);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
