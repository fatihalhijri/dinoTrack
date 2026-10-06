<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MessageLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MessageLog
 */
class MessageLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'template_key' => $this->template_key?->value,
            'template_label' => $this->template_key?->label(),
            'phone' => $this->phone,
            'body' => $this->body,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'error' => $this->error,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
