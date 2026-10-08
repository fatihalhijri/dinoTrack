<?php

declare(strict_types=1);

namespace App\Actions\Routers;

use App\Models\Router;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateRouter
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Password kosong berarti password lama dipertahankan, karena password tidak pernah
     * dikirim balik ke form.
     *
     * @param  array{name?: string, host?: string, port?: int, username?: string, password?: string|null, use_ssl?: bool, isolation_profile?: string, is_active?: bool}  $attributes
     */
    public function handle(Router $router, array $attributes, ?User $by = null): Router
    {
        $attributes = Arr::only($attributes, CreateRouter::FIELDS);

        if (blank($attributes['password'] ?? null)) {
            unset($attributes['password']);
        }

        return DB::transaction(function () use ($router, $attributes, $by): Router {
            $router->fill($attributes);
            $changes = $this->logger->pendingChanges($router);

            if ($changes === []) {
                return $router;
            }

            $router->save();
            $this->logger->log('router.updated', $router, $by, ['changes' => $changes]);

            return $router;
        });
    }
}
