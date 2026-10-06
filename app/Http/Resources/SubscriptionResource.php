<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package' => $this->whenLoaded('package', fn (): array => self::packageSummary($this->package)),
            'next_package' => $this->whenLoaded('nextPackage', fn (): ?array => $this->nextPackage === null ? null : self::packageSummary($this->nextPackage)),
            'price' => $this->price,
            'billing_day' => $this->billing_day,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
        ];
    }

    /**
     * @return array{id: int, name: string, speed_label: string}
     */
    private static function packageSummary(Package $package): array
    {
        return ['id' => $package->id, 'name' => $package->name, 'speed_label' => $package->speed_label];
    }
}
