<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Package
 */
class PackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'speed_label' => $this->speed_label,
            'price' => $this->price,
            'mikrotik_profile' => $this->mikrotik_profile,
            'is_active' => $this->is_active,
            'description' => $this->description,
            'subscriptions_count' => $this->whenCounted('subscriptions'),
        ];
    }
}
