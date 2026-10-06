<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice' => $this->whenLoaded('invoice', fn (): array => [
                'id' => $this->invoice->id,
                'number' => $this->invoice->number,
                'status' => $this->invoice->status->value,
                'customer' => $this->invoice->relationLoaded('customer') ? [
                    'id' => $this->invoice->customer->id,
                    'code' => $this->invoice->customer->code,
                    'name' => $this->invoice->customer->name,
                ] : null,
            ]),
            'order_id' => $this->whenLoaded('paymentCharge', fn (): ?string => $this->paymentCharge?->order_id),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'amount' => $this->amount,
            'paid_at' => $this->paid_at->toIso8601String(),
            'reference' => $this->reference,
            'received_by' => $this->whenLoaded('receivedBy', fn (): ?array => $this->receivedBy === null ? null : [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->name,
            ]),
            'notes' => $this->notes,
            'review_status' => $this->review_status->value,
            'review_status_label' => $this->review_status->label(),
            'review_note' => $this->review_note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
