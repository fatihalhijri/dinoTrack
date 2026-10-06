<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'odp' => $this->odp,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'router' => $this->whenLoaded('router', fn (): array => ['id' => $this->router->id, 'name' => $this->router->name]),
            'pppoe_username' => $this->pppoe_username,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'isolation_reason' => $this->isolation_reason?->value,
            'isolation_reason_label' => $this->isolation_reason?->label(),
            'installed_at' => $this->installed_at?->toDateString(),
            'isolated_at' => $this->isolated_at?->toIso8601String(),
            'terminated_at' => $this->terminated_at?->toIso8601String(),
            'network_error_at' => $this->network_error_at?->toIso8601String(),
            'network_error' => $this->network_error,
            'notes' => $this->notes,
            'subscription' => SubscriptionResource::make($this->whenLoaded('activeSubscription')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
