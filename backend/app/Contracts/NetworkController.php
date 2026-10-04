<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Customer;
use App\Models\Router;

interface NetworkController
{
    public function testConnection(Router $router): bool;

    public function isolate(Customer $customer): void;

    public function activate(Customer $customer, string $profile): void;

    public function disableSecret(Customer $customer): void;

    public function isOnline(Customer $customer): bool;
}
