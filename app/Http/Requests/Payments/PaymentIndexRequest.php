<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Concerns\IndexQueryRules;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentIndexRequest extends FormRequest
{
    use IndexQueryRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', Payment::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->indexRules(),
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'review_status' => ['nullable', Rule::enum(PaymentReviewStatus::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'customer_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array{search: string|null, method: PaymentMethod|null, review_status: PaymentReviewStatus|null, from: CarbonImmutable|null, to: CarbonImmutable|null, customer_id: int|null}
     */
    public function filters(): array
    {
        return [
            'search' => $this->searchTerm(),
            'method' => $this->enum('method', PaymentMethod::class),
            'review_status' => $this->enum('review_status', PaymentReviewStatus::class),
            'from' => $this->filled('from') ? CarbonImmutable::parse($this->string('from')->toString()) : null,
            'to' => $this->filled('to') ? CarbonImmutable::parse($this->string('to')->toString()) : null,
            'customer_id' => $this->filled('customer_id') ? $this->integer('customer_id') : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->indexAttributes(),
            'method' => 'metode pembayaran',
            'review_status' => 'status tinjauan',
            'from' => 'tanggal awal',
            'to' => 'tanggal akhir',
            'customer_id' => 'pelanggan',
        ];
    }
}
