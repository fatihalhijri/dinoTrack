<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoices;

use App\Models\Invoice;
use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class ReissueInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice && (bool) $this->user()?->can('reissue', $invoice);
    }

    /**
     * `package_id` hanya diisi jika pembatalan karena salah input paket.
     *
     * @return array<string, array<int, Exists|string>>
     */
    public function rules(): array
    {
        return [
            'package_id' => ['nullable', 'integer', Rule::exists(Package::class, 'id')],
        ];
    }

    /**
     * Nilai siap pakai untuk ReissueInvoice; input form selalu berupa string.
     */
    public function correctedPackageId(): ?int
    {
        return $this->filled('package_id') ? $this->integer('package_id') : null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'package_id' => 'paket koreksi',
        ];
    }
}
