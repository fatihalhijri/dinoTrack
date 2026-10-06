<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer' => $this->whenLoaded('customer', fn (): array => [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'name' => $this->customer->name,
                'status' => $this->customer->status->value,
            ]),
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'issued_at' => $this->issued_at->toDateString(),
            'due_at' => $this->due_at->toDateString(),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'penalty' => $this->penalty,
            'total' => $this->total,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_reason' => $this->cancelled_reason,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'payment_charges' => PaymentChargeResource::collection($this->whenLoaded('paymentCharges')),
        ];
    }
}
