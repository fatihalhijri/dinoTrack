<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rentang tanggal bayar (inklusif) untuk ekspor rincian pembayaran.
 */
class ExportPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permission::ReportsView->value);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('from')->toString());
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('to')->toString());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from' => 'tanggal awal',
            'to' => 'tanggal akhir',
        ];
    }
}
