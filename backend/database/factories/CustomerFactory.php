<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Enums\IsolationReason;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('PLG-######'),
            'name' => fake()->name(),
            'phone' => fake()->numerify('628##########'),
            'address' => fake()->address(),
            'odp' => fake()->bothify('ODP-??-##'),
            'latitude' => fake()->latitude(-8, -6),
            'longitude' => fake()->longitude(106, 112),
            'router_id' => Router::factory(),
            'pppoe_username' => fake()->unique()->userName(),
            'status' => CustomerStatus::Active,
            'installed_at' => now()->subMonths(3)->toDateString(),
            'isolated_at' => null,
            'isolation_reason' => null,
            'terminated_at' => null,
            'notes' => null,
        ];
    }

    /** Terdaftar tetapi belum dipasang, sehingga belum ditagih. */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::Pending,
            'installed_at' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::Active,
        ]);
    }

    public function isolated(IsolationReason $reason = IsolationReason::Overdue): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::Isolated,
            'isolated_at' => now(),
            'isolation_reason' => $reason,
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::Terminated,
            'terminated_at' => now(),
        ]);
    }

    /**
     * Subscription mengikuti status pelanggan: pending belum punya tanggal mulai,
     * terminated sudah berakhir.
     */
    public function withSubscription(?Package $package = null): static
    {
        $subscription = Subscription::factory()
            ->state(fn (array $attributes, ?Model $customer): array => $customer instanceof Customer ? [
                'starts_at' => $customer->installed_at?->toDateString(),
                'ends_at' => $customer->terminated_at?->toDateString(),
            ] : []);

        if ($package !== null) {
            $subscription = $subscription->for($package);
        }

        return $this->has($subscription, 'subscriptions');
    }
}
