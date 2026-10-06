<?php

declare(strict_types=1);

namespace App\Actions\Routers;

use App\Contracts\NetworkController;
use App\Exceptions\RouterUnreachableException;
use App\Models\Router;
use App\Models\User;
use App\Support\ActivityLogger;

/**
 * Dijalankan sinkron (bukan job) karena admin menunggu hasilnya di layar.
 */
final class TestRouterConnection
{
    public function __construct(
        private readonly NetworkController $network,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Router $router, ?User $by = null): bool
    {
        $error = null;

        try {
            $isConnected = $this->network->testConnection($router);
        } catch (RouterUnreachableException $exception) {
            $isConnected = false;
            $error = $exception->getMessage();
        }

        if ($isConnected) {
            $router->update(['last_connected_at' => now()]);
        }

        $this->logger->log('router.connection_tested', $router, $by, [
            'success' => $isConnected,
            'error' => $error,
        ]);

        return $isConnected;
    }
}
