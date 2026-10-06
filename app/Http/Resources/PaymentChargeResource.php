<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PaymentCharge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Respons mentah gateway (`raw_response`) tidak dikirim ke frontend.
 *
 * @mixin PaymentCharge
 */
class PaymentChargeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attempt' => $this->attempt,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
