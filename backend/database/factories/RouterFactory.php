<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Router>
 */
class RouterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Router '.fake()->city(),
            'host' => fake()->localIpv4(),
            'port' => 8728,
            'username' => 'billing',
            'password' => fake()->password(12, 20),
            'use_ssl' => false,
            'isolation_profile' => 'ISOLIR',
            'is_active' => true,
            'last_connected_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
