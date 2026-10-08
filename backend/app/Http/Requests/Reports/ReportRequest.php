<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Concerns\IndexQueryRules;
use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Parameter halaman laporan: tahun pendapatan, rentang pergerakan pelanggan (default tahun
 * berjalan dan awal bulan ini s.d. hari ini), serta pencarian dan halaman daftar tunggakan.
 */
class ReportRequest extends FormRequest
{
    use IndexQueryRules;

    public const int MIN_YEAR = 2000;

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
            ...$this->indexRules(),
            'year' => ['nullable', 'integer', 'between:'.self::MIN_YEAR.','.(today()->year + 1)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function year(): int
    {
        return $this->filled('year') ? $this->integer('year') : today()->year;
    }

    public function from(): CarbonImmutable
    {
        return $this->filled('from') ? CarbonImmutable::parse($this->string('from')->toString()) : today()->startOfMonth();
    }

    public function to(): CarbonImmutable
    {
        return $this->filled('to') ? CarbonImmutable::parse($this->string('to')->toString()) : today();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->indexAttributes(),
            'year' => 'tahun',
            'from' => 'tanggal awal',
            'to' => 'tanggal akhir',
        ];
    }
}
