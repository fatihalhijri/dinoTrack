<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Contracts\NetworkController;
use App\Exceptions\NotImplementedException;
use App\Models\Customer;
use App\Models\Router;

final class MikrotikNetworkController implements NetworkController
{
    public function testConnection(Router $router): bool
    {
        throw NotImplementedException::for(self::class, __FUNCTION__);
    }

    public function isolate(Customer $customer): void
    {
        throw NotImplementedException::for(self::class, __FUNCTION__);
    }

    public function activate(Customer $customer, string $profile): void
    {
        throw NotImplementedException::for(self::class, __FUNCTION__);
    }

    public function disableSecret(Customer $customer): void
    {
        throw NotImplementedException::for(self::class, __FUNCTION__);
    }

    public function isOnline(Customer $customer): bool
    {
        throw NotImplementedException::for(self::class, __FUNCTION__);
    }
}
