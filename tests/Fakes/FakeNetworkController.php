<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\NetworkController;
use App\Models\Customer;
use App\Models\Router;

final class FakeNetworkController implements NetworkController
{
    use RecordsCalls;

    public bool $connectable = true;

    public bool $online = false;

    public function testConnection(Router $router): bool
    {
        $this->record(__FUNCTION__, [$router]);

        return $this->connectable;
    }

    public function isolate(Customer $customer): void
    {
        $this->record(__FUNCTION__, [$customer]);
    }

    public function activate(Customer $customer, string $profile): void
    {
        $this->record(__FUNCTION__, [$customer, $profile]);
    }

    public function disableSecret(Customer $customer): void
    {
        $this->record(__FUNCTION__, [$customer]);
    }

    public function isOnline(Customer $customer): bool
    {
        $this->record(__FUNCTION__, [$customer]);

        return $this->online;
    }
}
