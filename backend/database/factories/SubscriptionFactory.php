<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'package_id' => Package::factory(),
            'price' => fn (array $attributes): int => Package::query()->whereKey($attributes['package_id'])->firstOrFail()->price,
            'billing_day' => fake()->numberBetween(1, 28),
            'starts_at' => now()->subMonths(3)->toDateString(),
            'ends_at' => null,
            'next_package_id' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'ends_at' => now()->subDay()->toDateString(),
        ]);
    }

    public function withNextPackage(?Package $package = null): static
    {
        return $this->state(fn (array $attributes) => [
            'next_package_id' => $package ?? Package::factory(),
        ]);
    }
}
