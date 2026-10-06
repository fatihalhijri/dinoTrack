<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pembayaran tunai/transfer oleh kasir. Kecocokan nominal, status invoice, dan batas bawah
 * tanggal bayar (tanggal terbit tagihan) dijaga di RecordManualPayment.
 */
class RecordManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('invoice') instanceof Invoice && (bool) $this->user()?->can('create', Payment::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)->only([PaymentMethod::Cash, PaymentMethod::Transfer])],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Bukan method(): nama itu milik Request (metode HTTP).
     */
    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->string('method')->toString());
    }

    public function amount(): int
    {
        return $this->integer('amount');
    }

    /**
     * Kosong atau hari ini berarti saat ini; tanggal lampau dicatat pada awal hari itu.
     */
    public function paidAt(): ?CarbonImmutable
    {
        if (! $this->filled('paid_at')) {
            return null;
        }

        $date = CarbonImmutable::parse($this->string('paid_at')->toString())->startOfDay();

        return $date->isToday() ? null : $date;
    }

    public function notes(): ?string
    {
        return $this->filled('notes') ? $this->string('notes')->toString() : null;
    }

    /**
     * Pesan bawaan before_or_equal menampilkan parameter mentah ("today").
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paid_at.before_or_equal' => 'Tanggal bayar tidak boleh di masa depan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'method' => 'metode pembayaran',
            'amount' => 'nominal',
            'paid_at' => 'tanggal bayar',
            'notes' => 'catatan',
        ];
    }
}
