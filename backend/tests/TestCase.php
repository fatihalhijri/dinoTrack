<?php

declare(strict_types=1);

namespace Tests;

use App\Contracts\NetworkController;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Fakes\FakeNetworkController;

abstract class TestCase extends BaseTestCase
{
    /**
     * Router palsu dipasang untuk semua test: dengan queue `sync`, job router yang ikut ter-dispatch
     * (aktivasi, ganti paket, pembayaran) tidak boleh menghubungi router sungguhan.
     * Test yang perlu memeriksa panggilan memakai helper `fakeNetwork()`.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(NetworkController::class, new FakeNetworkController);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
