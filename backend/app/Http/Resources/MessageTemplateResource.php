<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MessageTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MessageTemplate
 */
class MessageTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key->value,
            'label' => $this->key->label(),
            'body' => $this->body,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
