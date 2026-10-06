<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Router;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Password router tidak pernah dikirim ke frontend; form ubah mengosongkannya.
 *
 * @mixin Router
 */
class RouterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'use_ssl' => $this->use_ssl,
            'isolation_profile' => $this->isolation_profile,
            'is_active' => $this->is_active,
            'last_connected_at' => $this->last_connected_at?->toIso8601String(),
            'customers_count' => $this->whenCounted('customers'),
        ];
    }
}
