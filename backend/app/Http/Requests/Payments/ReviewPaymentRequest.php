<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Actions\Payments\ResolvePayment;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Menandai pembayaran anomali sudah ditinjau. Syarat status `needs_review` dijaga ResolvePayment.
 */
class ReviewPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('payment');

        return $payment instanceof Payment && (bool) $this->user()?->can('review', $payment);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'review_note' => ['required', 'string', 'min:'.ResolvePayment::MIN_NOTE_LENGTH, 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'review_note' => 'catatan tinjauan',
        ];
    }
}
