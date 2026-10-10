<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoices;

use App\Actions\Invoices\CancelInvoice;
use App\Models\Invoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice && (bool) $this->user()?->can('cancel', $invoice);
    }

    /**
     * Batas atas mengikuti kolom `invoices.cancelled_reason` (VARCHAR 255).
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:'.CancelInvoice::MIN_REASON_LENGTH, 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => 'alasan pembatalan',
        ];
    }
}
