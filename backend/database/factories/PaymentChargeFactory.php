<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentChargeStatus;
use App\Models\Invoice;
use App\Models\PaymentCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentCharge>
 */
class PaymentChargeFactory extends Factory
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
            'attempt' => 1,
            'gateway' => 'midtrans',
            'order_id' => fn (array $attributes): string => str_replace('/', '', Invoice::query()->whereKey($attributes['invoice_id'])->firstOrFail()->number)
                .'-'.$attributes['attempt'],
            'amount' => fn (array $attributes): int => Invoice::query()->whereKey($attributes['invoice_id'])->firstOrFail()->total,
            'qr_string' => fake()->sha256(),
            'qr_url' => fake()->url(),
            'status' => PaymentChargeStatus::Pending,
            'expires_at' => now()->addMinutes(15),
            'raw_response' => null,
        ];
    }

    public function settled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentChargeStatus::Settled,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentChargeStatus::Failed,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentChargeStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
