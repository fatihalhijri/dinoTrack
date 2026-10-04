<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = today()->startOfMonth();

        return [
            'number' => 'INV/'.$periodStart->format('Y/m').'/'.fake()->unique()->numerify('#####'),
            'subscription_id' => Subscription::factory(),
            'customer_id' => fn (array $attributes): int => Subscription::query()->whereKey($attributes['subscription_id'])->firstOrFail()->customer_id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodStart->addMonth()->subDay()->toDateString(),
            'issued_at' => $periodStart->toDateString(),
            'due_at' => $periodStart->addDays(7)->toDateString(),
            'subtotal' => fn (array $attributes): int => Subscription::query()->whereKey($attributes['subscription_id'])->firstOrFail()->price,
            'discount' => 0,
            'penalty' => 0,
            'total' => fn (array $attributes): int => $attributes['subtotal'],
            'status' => InvoiceStatus::Unpaid,
            'paid_at' => null,
            'cancelled_at' => null,
            'cancelled_reason' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    /** Periode bulan lalu yang jatuh temponya sudah lewat. */
    public function overdue(): static
    {
        $periodStart = today()->subMonth()->startOfMonth();

        return $this->state(fn (array $attributes) => [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodStart->addMonth()->subDay()->toDateString(),
            'issued_at' => $periodStart->toDateString(),
            'due_at' => $periodStart->addDays(7)->toDateString(),
            'status' => InvoiceStatus::Overdue,
        ]);
    }

    public function cancelled(string $reason = 'Salah input'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_reason' => $reason,
        ]);
    }
}
