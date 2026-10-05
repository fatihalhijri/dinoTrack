<?php

declare(strict_types=1);

namespace App\Actions\Routers;

use App\Models\Router;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteRouter
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Pelanggan yang di-soft-delete tetap dihitung karena foreign key-nya masih ada.
     */
    public function handle(Router $router, ?User $by = null): void
    {
        if ($router->customers()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'router' => 'Router masih dipakai pelanggan dan tidak bisa dihapus. Nonaktifkan router sebagai gantinya.',
            ]);
        }

        DB::transaction(function () use ($router, $by): void {
            $router->delete();
            $this->logger->log('router.deleted', $router, $by, ['name' => $router->name, 'host' => $router->host]);
        });
    }
}
