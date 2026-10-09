<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\CustomerStatus;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Baris daftar tunggakan dari `ReportService::outstandingInvoicesQuery()` (kolom pelanggan dan
 * `age_days` hasil join, bukan relasi).
 *
 * @mixin Invoice
 */
class OutstandingInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $customerStatus = CustomerStatus::from((string) $this->getAttribute('customer_status'));

        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'customer_code' => $this->getAttribute('customer_code'),
            'customer_name' => $this->getAttribute('customer_name'),
            'customer_status' => $customerStatus->value,
            'customer_status_label' => $customerStatus->label(),
            'due_at' => $this->due_at->toDateString(),
            'age_days' => (int) $this->getAttribute('age_days'),
            'total' => $this->total,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
        ];
    }
}
