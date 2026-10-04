<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentCharge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'payment_charge_id' => null,
            'method' => PaymentMethod::Cash,
            'amount' => fn (array $attributes): int => Invoice::query()->whereKey($attributes['invoice_id'])->firstOrFail()->total,
            'paid_at' => now(),
            'reference' => null,
            'received_by' => User::factory(),
            'notes' => null,
            'review_status' => PaymentReviewStatus::None,
            'review_note' => null,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => PaymentMethod::Cash,
        ]);
    }

    /** Pembayaran QRIS lewat gateway, lengkap dengan charge yang sudah lunas untuk invoice yang sama. */
    public function qris(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => PaymentMethod::Qris,
            'payment_charge_id' => fn (array $attributes): int => PaymentCharge::factory()->settled()
                ->create(['invoice_id' => $attributes['invoice_id']])->id,
            'reference' => fake()->uuid(),
            'received_by' => null,
        ]);
    }

    public function needsReview(string $note = 'Invoice sudah lunas sebelumnya'): static
    {
        return $this->state(fn (array $attributes) => [
            'review_status' => PaymentReviewStatus::NeedsReview,
            'review_note' => $note,
        ]);
    }
}
