<?php

declare(strict_types=1);

namespace App\Actions\Routers;

use App\Models\Router;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CreateRouter
{
    public const array FIELDS = ['name', 'host', 'port', 'username', 'password', 'use_ssl', 'isolation_profile', 'is_active'];

    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{name: string, host: string, port: int, username: string, password: string, use_ssl: bool, isolation_profile: string, is_active: bool}  $attributes
     */
    public function handle(array $attributes, ?User $by = null): Router
    {
        $attributes = Arr::only($attributes, self::FIELDS);

        return DB::transaction(function () use ($attributes, $by): Router {
            $router = Router::query()->create($attributes);

            $this->logger->log('router.created', $router, $by, Arr::except($attributes, ['password']));

            return $router;
        });
    }
}
